<?php

namespace Tests\Feature;

use App\Models\Apartado;
use App\Models\ApartadoDetalle;
use App\Models\Cliente;
use App\Models\Libro;
use App\Models\Movimiento;
use App\Models\SubInventario;
use App\Models\Venta;
use App\Services\InventoryStockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InventoryStockServiceTest extends TestCase
{
    use RefreshDatabase;

    private InventoryStockService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(InventoryStockService::class);
    }

    public function test_subinventory_sale_never_changes_general_stock(): void
    {
        $libro = $this->book(stock: 20, subStock: 8);
        $sub = $this->subinventory();
        $sub->libros()->attach($libro->id, ['cantidad' => 8]);
        $venta = $this->sale('subinventario', $sub->id);
        $this->movement($venta, $libro, 3);

        DB::transaction(fn () => $this->service->deductSale($venta));

        $this->assertSame(20, $libro->fresh()->stock);
        $this->assertSame(5, $libro->fresh()->stock_subinventario);
        $this->assertDatabaseHas('subinventario_libro', ['subinventario_id' => $sub->id, 'libro_id' => $libro->id, 'cantidad' => 5]);

        DB::transaction(fn () => $this->service->restoreSale($venta));

        $this->assertSame(20, $libro->fresh()->stock);
        $this->assertSame(8, $libro->fresh()->stock_subinventario);
        $this->assertDatabaseHas('subinventario_libro', ['subinventario_id' => $sub->id, 'libro_id' => $libro->id, 'cantidad' => 8]);
    }

    public function test_general_sale_cannot_consume_stock_reserved_by_apartados(): void
    {
        $libro = $this->book(stock: 10);
        $apartado = $this->apartado('general');
        $this->detail($apartado, $libro, 4);
        DB::transaction(fn () => $this->service->reserveApartado($apartado));

        $venta = $this->sale('general');
        $this->movement($venta, $libro, 7);

        $this->expectException(\DomainException::class);
        DB::transaction(fn () => $this->service->deductSale($venta));
    }

    public function test_subinventory_apartado_reserve_and_cancel_are_symmetric(): void
    {
        $libro = $this->book(stock: 12, subStock: 6);
        $sub = $this->subinventory();
        $sub->libros()->attach($libro->id, ['cantidad' => 6]);
        $apartado = $this->apartado('subinventario', $sub->id);
        $this->detail($apartado, $libro, 2);

        DB::transaction(fn () => $this->service->reserveApartado($apartado));
        $this->assertSame(12, $libro->fresh()->stock);
        $this->assertSame(4, $libro->fresh()->stock_subinventario);
        $this->assertSame(2, $libro->fresh()->stock_apartado);

        DB::transaction(fn () => $this->service->releaseApartado($apartado));
        $this->assertSame(12, $libro->fresh()->stock);
        $this->assertSame(6, $libro->fresh()->stock_subinventario);
        $this->assertSame(0, $libro->fresh()->stock_apartado);
        $this->assertDatabaseHas('subinventario_libro', ['subinventario_id' => $sub->id, 'libro_id' => $libro->id, 'cantidad' => 6]);
    }

    public function test_liquidating_subinventory_apartado_does_not_touch_general_stock(): void
    {
        $libro = $this->book(stock: 9, subStock: 5);
        $sub = $this->subinventory();
        $sub->libros()->attach($libro->id, ['cantidad' => 5]);
        $apartado = $this->apartado('subinventario', $sub->id);
        $this->detail($apartado, $libro, 2);

        DB::transaction(fn () => $this->service->reserveApartado($apartado));
        DB::transaction(fn () => $this->service->consumeApartado($apartado));

        $this->assertSame(9, $libro->fresh()->stock);
        $this->assertSame(3, $libro->fresh()->stock_subinventario);
        $this->assertSame(0, $libro->fresh()->stock_apartado);
    }

    public function test_cancelling_unpaid_installment_sale_does_not_create_stock(): void
    {
        $libro = $this->book(stock: 10);
        $venta = $this->sale('general');
        $venta->update(['es_a_plazos' => true, 'estado_pago' => 'pendiente', 'total' => 100, 'total_pagado' => 0]);
        $this->movement($venta, $libro, 3);

        $this->withSession($this->adminSession())->post(route('ventas.cancelar', $venta))->assertRedirect();

        $this->assertSame(10, $libro->fresh()->stock);
        $this->assertSame('cancelada', $venta->fresh()->estado);
    }

    public function test_deleting_cancelled_sale_does_not_restore_stock_twice(): void
    {
        $libro = $this->book(stock: 7);
        $venta = $this->sale('general');
        $this->movement($venta, $libro, 3);

        $this->withSession($this->adminSession())->post(route('ventas.cancelar', $venta))->assertRedirect();
        $this->assertSame(10, $libro->fresh()->stock);

        $this->withSession($this->adminSession())->delete(route('ventas.destroy', $venta))->assertRedirect();
        $this->assertSame(10, $libro->fresh()->stock);
    }

    public function test_mobile_final_abono_liquidates_apartado_automatically(): void
    {
        $apartado = $this->apartado('general');
        $apartado->update(['monto_total' => 50, 'saldo_pendiente' => 50]);

        $this->postJson('/api/v1/movil/abonos', [
            'apartado_id' => $apartado->id,
            'monto' => 50,
            'metodo_pago' => 'efectivo',
            'usuario' => 'test',
        ])->assertCreated();

        $this->assertSame('liquidado', $apartado->fresh()->estado);
        $this->assertSame('0.00', $apartado->fresh()->saldo_pendiente);
        $this->assertNotNull($apartado->fresh()->venta_id);
    }

    private function book(int $stock, int $subStock = 0): Libro
    {
        return Libro::create(['nombre' => 'Libro prueba', 'precio' => 100, 'stock' => $stock, 'stock_subinventario' => $subStock]);
    }

    private function subinventory(): SubInventario
    {
        return SubInventario::create(['fecha_subinventario' => now(), 'descripcion' => 'Prueba', 'estado' => 'activo', 'usuario' => 'test']);
    }

    private function sale(string $type, ?int $subId = null): Venta
    {
        return Venta::create([
            'fecha_venta' => now(), 'tipo_pago' => 'contado', 'estado' => 'completada', 'usuario' => 'test',
            'tipo_inventario' => $type, 'subinventario_id' => $subId, 'es_a_plazos' => false,
        ]);
    }

    private function movement(Venta $venta, Libro $libro, int $quantity): Movimiento
    {
        return Movimiento::create([
            'venta_id' => $venta->id, 'libro_id' => $libro->id, 'tipo_movimiento' => 'salida',
            'tipo_salida' => 'venta', 'cantidad' => $quantity, 'precio_unitario' => 100, 'fecha' => now(),
        ]);
    }

    private function apartado(string $type, ?int $subId = null): Apartado
    {
        $cliente = Cliente::create(['nombre' => 'Cliente prueba']);
        return Apartado::create([
            'folio' => uniqid('AP-'), 'cliente_id' => $cliente->id, 'fecha_apartado' => now(),
            'monto_total' => 100, 'enganche' => 0, 'saldo_pendiente' => 100, 'estado' => 'activo',
            'usuario' => 'test', 'tipo_inventario' => $type, 'subinventario_id' => $subId,
        ]);
    }

    private function detail(Apartado $apartado, Libro $libro, int $quantity): ApartadoDetalle
    {
        return ApartadoDetalle::create([
            'apartado_id' => $apartado->id, 'libro_id' => $libro->id, 'cantidad' => $quantity,
            'precio_unitario' => 100, 'descuento' => 0, 'subtotal' => 100 * $quantity,
        ]);
    }

    private function adminSession(): array
    {
        return [
            'codCongregante' => 'test-admin',
            'username' => 'test',
            'roles' => [['ROL' => 'ADMIN LIBRERIA', 'ID' => 19]],
        ];
    }

    public function test_skip_zero_quantity_movements_in_subinventory_update(): void
    {
        $libro1 = $this->book(stock: 10, subStock: 5);
        $libro2 = $this->book(stock: 10, subStock: 0);

        $sub = $this->subinventory();
        $sub->libros()->attach($libro1->id, ['cantidad' => 5]);

        $response = $this->withSession($this->adminSession())
            ->put(route('subinventarios.update', $sub), [
                'fecha_subinventario' => now()->toDateString(),
                'descripcion' => 'Updated Description',
                'libros' => [
                    ['libro_id' => $libro1->id, 'cantidad' => 0],
                    ['libro_id' => $libro2->id, 'cantidad' => 0],
                ]
            ]);

        $response->assertRedirect(route('subinventarios.show', $sub));

        $this->assertDatabaseMissing('movimientos', [
            'cantidad' => 0,
        ]);
        
        $this->assertSame(15, $libro1->fresh()->stock);
        $this->assertSame(0, $libro1->fresh()->stock_subinventario);
        
        $this->assertSame(10, $libro2->fresh()->stock);
        $this->assertSame(0, $libro2->fresh()->stock_subinventario);
    }

    public function test_audit_direct_stock_edits_via_controller_update(): void
    {
        $libro = $this->book(stock: 10);

        $response = $this->withSession($this->adminSession())
            ->put(route('inventario.update', $libro->id), [
                'nombre' => $libro->nombre,
                'codigo_barras' => $libro->codigo_barras,
                'precio' => $libro->precio,
                'stock' => 15,
            ]);

        $response->assertRedirect(route('inventario.show', $libro->id));

        $this->assertSame(15, $libro->fresh()->stock);

        $this->assertDatabaseHas('movimientos', [
            'libro_id' => $libro->id,
            'tipo_movimiento' => 'entrada',
            'tipo_entrada' => 'ajuste_positivo',
            'cantidad' => 5,
        ]);
    }

    public function test_block_book_deletion_if_relations_exist(): void
    {
        $libro = $this->book(stock: 10);
        $sub = $this->subinventory();
        $sub->libros()->attach($libro->id, ['cantidad' => 5]);

        $response = $this->withSession($this->adminSession())
            ->delete(route('inventario.destroy', $libro->id));

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('libros', ['id' => $libro->id]);

        $sub->libros()->detach($libro->id);
        
        $response2 = $this->withSession($this->adminSession())
            ->delete(route('inventario.destroy', $libro->id));

        $response2->assertRedirect(route('inventario.index'));
        $response2->assertSessionHas('success');
        $this->assertDatabaseMissing('libros', ['id' => $libro->id]);
    }

    public function test_cannot_delete_payment_if_sale_cancelled(): void
    {
        $libro = $this->book(stock: 10);
        $venta = Venta::create([
            'fecha_venta' => now(),
            'tipo_pago' => 'credito',
            'estado' => 'completada',
            'usuario' => 'test',
            'tipo_inventario' => 'general',
            'es_a_plazos' => true,
            'estado_pago' => 'pendiente',
            'total' => 100,
            'total_pagado' => 0,
        ]);
        
        $pago = \App\Models\Pago::create([
            'venta_id' => $venta->id,
            'fecha_pago' => now(),
            'monto' => 50,
            'metodo_pago' => 'efectivo',
        ]);

        $venta->update(['estado' => 'cancelada']);

        $response = $this->withSession($this->adminSession())
            ->delete(route('pagos.destroy', $pago->id));

        $response->assertSessionHas('error', 'No se pueden eliminar pagos de una venta cancelada');
        $this->assertDatabaseHas('pagos', ['id' => $pago->id]);
    }

    public function test_subinventory_partial_return_with_lock(): void
    {
        $libro = $this->book(stock: 10, subStock: 5);
        $sub = $this->subinventory();
        $sub->libros()->attach($libro->id, ['cantidad' => 5]);

        $response = $this->withSession($this->adminSession())
            ->post(route('subinventarios.devolver-parcial', $sub), [
                'libro_id' => $libro->id,
                'cantidad' => 3,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertSame(13, $libro->fresh()->stock);
        $this->assertSame(2, $libro->fresh()->stock_subinventario);
        $this->assertDatabaseHas('subinventario_libro', [
            'subinventario_id' => $sub->id,
            'libro_id' => $libro->id,
            'cantidad' => 2,
        ]);
    }

    public function test_manual_movement_cannot_spend_reserved_stock(): void
    {
        $libro = $this->book(stock: 10);
        $apartado = $this->apartado('general');
        $this->detail($apartado, $libro, 4);
        
        DB::transaction(fn () => $this->service->reserveApartado($apartado));

        $response = $this->withSession($this->adminSession())
            ->post(route('movimientos.store'), [
                'libro_id' => $libro->id,
                'tipo_movimiento' => 'salida',
                'tipo_salida' => 'perdida',
                'cantidad' => 7,
            ]);

        $response->assertSessionHasErrors('cantidad');
        
        $response2 = $this->withSession($this->adminSession())
            ->post(route('movimientos.store'), [
                'libro_id' => $libro->id,
                'tipo_movimiento' => 'salida',
                'tipo_salida' => 'perdida',
                'cantidad' => 6,
            ]);

        $response2->assertRedirect(route('movimientos.index'));
        $response2->assertSessionHas('success');
        $this->assertSame(4, $libro->fresh()->stock);
    }

    public function test_direct_stock_edit_cannot_go_below_reserved_stock(): void
    {
        $libro = $this->book(stock: 10);
        $apartado = $this->apartado('general');
        $this->detail($apartado, $libro, 4);

        DB::transaction(fn () => $this->service->reserveApartado($apartado));

        $response = $this->withSession($this->adminSession())
            ->put(route('inventario.update', $libro->id), [
                'nombre' => $libro->nombre,
                'codigo_barras' => $libro->codigo_barras,
                'precio' => $libro->precio,
                'stock' => 3,
            ]);

        $response->assertSessionHasErrors('stock');
        $this->assertSame(10, $libro->fresh()->stock);

        $response2 = $this->withSession($this->adminSession())
            ->put(route('inventario.update', $libro->id), [
                'nombre' => $libro->nombre,
                'codigo_barras' => $libro->codigo_barras,
                'precio' => $libro->precio,
                'stock' => 4,
            ]);

        $response2->assertRedirect(route('inventario.show', $libro->id));
        $this->assertSame(4, $libro->fresh()->stock);
    }

    public function test_update_general_sale_respects_reserved_stock(): void
    {
        $libro = $this->book(stock: 10);
        
        // Reservar 4 unidades en un apartado general
        $apartado = $this->apartado('general');
        $this->detail($apartado, $libro, 4);
        DB::transaction(fn () => $this->service->reserveApartado($apartado));
        
        // Venta general original de 5 unidades (descuenta 5, queda stock = 5, con 4 reservados)
        $venta = $this->sale('general');
        $this->movement($venta, $libro, 5);
        $libro->decrement('stock', 5);
        
        $this->assertSame(5, $libro->fresh()->stock);
        
        // Intentar actualizar la venta a 7 unidades
        // Al restaurar, stock es 5 + 5 = 10 (disponible para venta = 10, pero 4 están reservados, por lo que quedan solo 6 disponibles)
        // Pedir 7 unidades debería fallar
        $response = $this->withSession($this->adminSession())
            ->put(route('ventas.update', $venta), [
                'fecha_venta' => now()->toDateString(),
                'tipo_pago' => 'contado',
                'cliente_id' => null,
                'libros' => [
                    ['libro_id' => $libro->id, 'cantidad' => 7]
                ]
            ]);
            
        $response->assertSessionHasErrors('error');
        // El stock no debió cambiar (sigue siendo 5)
        $this->assertSame(5, $libro->fresh()->stock);
        // Los movimientos de la venta no debieron cambiar
        $this->assertSame(5, $venta->movimientos()->first()->cantidad);
    }

    public function test_update_general_sale_applies_stock_changes_correctly(): void
    {
        $libro = $this->book(stock: 10);
        
        // Reservar 4 unidades en un apartado general
        $apartado = $this->apartado('general');
        $this->detail($apartado, $libro, 4);
        DB::transaction(fn () => $this->service->reserveApartado($apartado));
        
        // Venta general original de 5 unidades (descuenta 5, queda stock = 5)
        $venta = $this->sale('general');
        $this->movement($venta, $libro, 5);
        $libro->decrement('stock', 5);
        
        $this->assertSame(5, $libro->fresh()->stock);
        
        // Actualizar la venta a 6 unidades
        // Al restaurar, stock es 1 + 5 = 6 (disponible para venta = 6, pero 4 están reservados, quedan 2 libres)
        // Pedir 6 unidades (requiere 1 adicional del stock libre de 2) debería funcionar
        $response = $this->withSession($this->adminSession())
            ->put(route('ventas.update', $venta), [
                'fecha_venta' => now()->toDateString(),
                'tipo_pago' => 'contado',
                'cliente_id' => null,
                'libros' => [
                    ['libro_id' => $libro->id, 'cantidad' => 6]
                ]
            ]);
            
        $response->assertRedirect(route('ventas.show', $venta));
        // El stock físico debió quedar en 4 (10 total - 6 venta) ya que las reservas solo aumentan stock_apartado, no descuentan stock físico
        $this->assertSame(4, $libro->fresh()->stock);
        // El movimiento se actualizó a 6
        $this->assertSame(6, $venta->movimientos()->first()->cantidad);
        
        // Verificar que el total y total_pagado se recalcularon y actualizados correctamente
        $ventaFresh = $venta->fresh();
        $this->assertEquals(600, $ventaFresh->total);
        $this->assertEquals(600, $ventaFresh->total_pagado);
    }

    public function test_cancelling_sale_reactivates_associated_apartado(): void
    {
        $libro = $this->book(stock: 10);
        
        // Crear apartado
        $apartado = $this->apartado('general');
        $this->detail($apartado, $libro, 3);
        
        // Reservar stock del apartado (stock_apartado = 3)
        DB::transaction(fn () => $this->service->reserveApartado($apartado));
        $apartado->update(['saldo_pendiente' => 0, 'monto_total' => 300]);
        $this->assertSame(3, $libro->fresh()->stock_apartado);
        $this->assertSame(10, $libro->fresh()->stock);

        // Liquidar apartado (esto crea una venta y consume stock)
        $this->withSession($this->adminSession())
            ->put(route('apartados.liquidar', $apartado))
            ->assertRedirect();
        
        // Verificar que el apartado esté liquidado y la venta tenga el apartado_id
        $apartado = $apartado->fresh();
        $this->assertSame('liquidado', $apartado->estado);
        $this->assertNotNull($apartado->venta_id);
        
        $venta = $apartado->venta;
        $this->assertNotNull($venta);
        $this->assertSame($apartado->id, $venta->apartado_id);
        
        // Verificar que el stock se haya consumido (stock = 7, stock_apartado = 0)
        $this->assertSame(7, $libro->fresh()->stock);
        $this->assertSame(0, $libro->fresh()->stock_apartado);
        
        // Cancelar la venta
        $this->withSession($this->adminSession())
            ->post(route('ventas.cancelar', $venta))
            ->assertRedirect();
            
        // Verificar que el apartado se haya reactivado, su venta_id sea null y el stock se haya reservado de nuevo
        $apartado = $apartado->fresh();
        $this->assertSame('activo', $apartado->estado);
        $this->assertNull($apartado->venta_id);
        
        // El stock físico de bodega volvió a 10 y el stock reservado volvió a 3
        $this->assertSame(10, $libro->fresh()->stock);
        $this->assertSame(3, $libro->fresh()->stock_apartado);
    }

    public function test_api_store_mixed_payment_updates_total_pagado(): void
    {
        $libro = $this->book(stock: 10, subStock: 5);
        $sub = $this->subinventory();
        $sub->libros()->attach($libro->id, ['cantidad' => 5]);
        
        // Asignar el usuario al subinventario
        DB::table('subinventario_user')->insert([
            'subinventario_id' => $sub->id,
            'cod_congregante' => 'test-user',
            'nombre_congregante' => 'Test User',
        ]);

        $response = $this->postJson('/api/v1/ventas', [
            'subinventario_id' => $sub->id,
            'cod_congregante' => 'test-user',
            'fecha_venta' => now()->toDateString(),
            'tipo_pago' => 'mixto',
            'usuario' => 'test-user',
            'libros' => [
                ['libro_id' => $libro->id, 'cantidad' => 2]
            ]
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        
        // El total pagado debe ser igual al total de la venta (no 0)
        $ventaId = $response->json('data.venta_id');
        $venta = Venta::findOrFail($ventaId);
        $this->assertEquals($venta->total, $venta->total_pagado);
        $this->assertGreaterThan(0, $venta->total_pagado);
    }

    public function test_api_store_admin_mixed_payment_updates_total_pagado(): void
    {
        $libro = $this->book(stock: 10);
        
        $response = $this->postJson('/api/v1/movil/admin/ventas', [
            'tipo_inventario' => 'general',
            'fecha_venta' => now()->toDateString(),
            'tipo_pago' => 'mixto',
            'usuario' => 'admin-user',
            'libros' => [
                ['libro_id' => $libro->id, 'cantidad' => 1]
            ]
        ], [
            'X-Roles' => json_encode([['ROL' => 'ADMIN LIBRERIA', 'ID' => 19]])
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        
        $ventaId = $response->json('data.venta_id');
        $venta = Venta::findOrFail($ventaId);
        $this->assertEquals($venta->total, $venta->total_pagado);
        $this->assertGreaterThan(0, $venta->total_pagado);
    }

    public function test_api_mis_libros_disponibles_filters_by_congregante(): void
    {
        $libro1 = $this->book(stock: 10, subStock: 5);
        $libro2 = $this->book(stock: 10, subStock: 3);

        $sub1 = $this->subinventory();
        $sub1->libros()->attach($libro1->id, ['cantidad' => 5]);

        $sub2 = $this->subinventory();
        $sub2->libros()->attach($libro2->id, ['cantidad' => 3]);

        // Asignar test-user solo a sub1
        DB::table('subinventario_user')->insert([
            'subinventario_id' => $sub1->id,
            'cod_congregante' => 'test-user',
            'nombre_congregante' => 'Test User',
        ]);

        // Vendedor normal
        $response = $this->getJson('/api/v1/mis-libros-disponibles/test-user');
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        
        // Debería ver solo libro1 y no libro2
        $librosResponse = collect($response->json('data.libros'));
        $this->assertTrue($librosResponse->contains('id', $libro1->id));
        $this->assertFalse($librosResponse->contains('id', $libro2->id));
    }

    public function test_api_listar_libros_filters_availability_by_congregante(): void
    {
        $libro = $this->book(stock: 10, subStock: 5);

        $sub1 = $this->subinventory();
        $sub1->libros()->attach($libro->id, ['cantidad' => 5]);

        // No asignar test-user a ningún subinventario
        $response = $this->getJson('/api/v1/libros?cod_congregante=test-user');
        $response->assertStatus(200);
        
        $libroResponse = collect($response->json('data'))->firstWhere('id', $libro->id);
        $this->assertFalse($libroResponse['puede_vender']);
        $this->assertSame(0, $libroResponse['cantidad_disponible_para_mi']);

        // Asignar test-user a sub1
        DB::table('subinventario_user')->insert([
            'subinventario_id' => $sub1->id,
            'cod_congregante' => 'test-user',
            'nombre_congregante' => 'Test User',
        ]);

        $response2 = $this->getJson('/api/v1/libros?cod_congregante=test-user');
        $response2->assertStatus(200);
        
        $libroResponse2 = collect($response2->json('data'))->firstWhere('id', $libro->id);
        $this->assertTrue($libroResponse2['puede_vender']);
        $this->assertSame(5, $libroResponse2['cantidad_disponible_para_mi']);
    }

    public function test_api_mis_subinventarios_filters_by_congregante_for_vendors(): void
    {
        $sub1 = $this->subinventory();
        $sub2 = $this->subinventory();

        // Asignar test-user solo a sub1
        DB::table('subinventario_user')->insert([
            'subinventario_id' => $sub1->id,
            'cod_congregante' => 'test-user',
            'nombre_congregante' => 'Test User',
        ]);

        // Vendedor normal
        $response = $this->getJson('/api/v1/mis-subinventarios/test-user');
        $response->assertStatus(200);
        
        // Debería listar solo sub1 y no sub2
        $subinventarios = collect($response->json('data'));
        $this->assertTrue($subinventarios->contains('id', $sub1->id));
        $this->assertFalse($subinventarios->contains('id', $sub2->id));
    }

    public function test_api_mis_libros_disponibles_sanitizes_sort_direction(): void
    {
        $libro1 = $this->book(stock: 10, subStock: 5);
        $sub1 = $this->subinventory();
        $sub1->libros()->attach($libro1->id, ['cantidad' => 5]);

        DB::table('subinventario_user')->insert([
            'subinventario_id' => $sub1->id,
            'cod_congregante' => 'test-user',
            'nombre_congregante' => 'Test User',
        ]);

        // Intentar pasar una dirección de ordenamiento maliciosa
        $response = $this->getJson('/api/v1/mis-libros-disponibles/test-user?ordenar=cantidad_total_disponible&direccion=desc;--');
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
    }

    public function test_stock_total_includes_active_subinventory_apartados(): void
    {
        $libro = $this->book(stock: 10, subStock: 5);
        $sub = $this->subinventory();
        $sub->libros()->attach($libro->id, ['cantidad' => 5]);

        // Stock total inicial: 10 (general) + 5 (subinventario) = 15
        $this->assertSame(15, $libro->fresh()->stock_total);

        // Crear un apartado de subinventario por 2 unidades
        $apartado = $this->apartado('subinventario', $sub->id);
        $this->detail($apartado, $libro, 2);
        DB::transaction(fn () => $this->service->reserveApartado($apartado));

        // Al reservar, stock_subinventario baja a 3, pero stock_total debe seguir siendo 15
        $libroFresh = $libro->fresh();
        $this->assertSame(3, $libroFresh->stock_subinventario);
        $this->assertSame(15, $libroFresh->stock_total);

        // Liquidar el apartado (se convierte en venta, el stock físicamente sale)
        // Simulamos la liquidación consumiendo el apartado
        DB::transaction(fn () => $this->service->consumeApartado($apartado));
        $apartado->update(['estado' => 'liquidado']);

        // Ahora el stock físicamente ya no está en el subinventario, por lo que el stock total baja a 13
        $this->assertSame(13, $libro->fresh()->stock_total);
    }

    public function test_cannot_delete_client_with_associated_apartados(): void
    {
        $libro = $this->book(stock: 10);
        $apartado = $this->apartado('general');
        $cliente = $apartado->cliente;

        // Intentar eliminar el cliente
        $response = $this->withSession($this->adminSession())
            ->delete(route('clientes.destroy', $cliente));

        $response->assertRedirect(route('clientes.index'));
        $response->assertSessionHas('error', 'No se puede eliminar el cliente porque tiene apartados asociados');

        // Confirmar que el cliente sigue existiendo
        $this->assertDatabaseHas('clientes', ['id' => $cliente->id]);
    }
}

