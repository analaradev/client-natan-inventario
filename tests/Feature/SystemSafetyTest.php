<?php

namespace Tests\Feature;

use App\Models\Apartado;
use App\Models\Cliente;
use App\Models\Envio;
use App\Models\Libro;
use App\Services\InventoryStockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SystemSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_with_active_apartado_cannot_be_deleted_or_lose_reservation(): void
    {
        $cliente = Cliente::create(['nombre' => 'Cliente con apartado']);
        $libro = Libro::create(['nombre' => 'Libro', 'precio' => 100, 'stock' => 10, 'stock_subinventario' => 0]);
        $apartado = Apartado::create([
            'folio' => 'AP-TEST-0001', 'cliente_id' => $cliente->id, 'fecha_apartado' => now(),
            'monto_total' => 300, 'enganche' => 0, 'saldo_pendiente' => 300,
            'estado' => 'activo', 'usuario' => 'test', 'tipo_inventario' => 'general',
        ]);
        $apartado->detalles()->create([
            'libro_id' => $libro->id, 'cantidad' => 3, 'precio_unitario' => 100,
            'descuento' => 0, 'subtotal' => 300,
        ]);
        DB::transaction(fn () => app(InventoryStockService::class)->reserveApartado($apartado));

        $this->withSession($this->adminSession())
            ->delete(route('clientes.destroy', $cliente))
            ->assertRedirect(route('clientes.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('clientes', ['id' => $cliente->id]);
        $this->assertDatabaseHas('apartados', ['id' => $apartado->id, 'estado' => 'activo']);
        $this->assertSame(3, $libro->fresh()->stock_apartado);
    }

    public function test_client_without_sales_or_apartados_can_be_deleted(): void
    {
        $cliente = Cliente::create(['nombre' => 'Cliente vacío']);

        $this->withSession($this->adminSession())
            ->delete(route('clientes.destroy', $cliente))
            ->assertRedirect(route('clientes.index'));

        $this->assertDatabaseMissing('clientes', ['id' => $cliente->id]);
    }

    public function test_guest_cannot_mutate_inventory(): void
    {
        $this->post(route('inventario.store'), [])->assertRedirect(route('login'));
    }

    public function test_supervisor_cannot_update_user_roles_through_duplicate_route(): void
    {
        Http::fake();

        $this->withSession($this->supervisorSession())
            ->put('/usuarios/123', ['roles' => ['19']])
            ->assertRedirect(route('dashboard'));

        Http::assertNothingSent();
    }

    public function test_test_credentials_are_disabled_outside_testing_environment(): void
    {
        app()->detectEnvironment(fn () => 'production');
        Http::fake(['*' => Http::response(['error' => true], 401)]);
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

        $this->from(route('login'))
            ->post(route('login.post'), ['user' => 'test', 'contra' => 'test123'])
            ->assertRedirect(route('login'));

        $this->assertFalse(session()->has('codCongregante'));
    }

    public function test_external_login_rejects_user_without_inventory_role(): void
    {
        Http::fake(['*' => Http::response([
            'error' => false, 'token' => 'external-token',
            'roles' => [['ROL' => 'MIEMBRO', 'ID' => 1]],
        ])]);

        $this->from(route('login'))->post(route('login.post'), [
            'user' => 'miembro', 'contra' => 'clave',
        ])->assertRedirect(route('login'))->assertSessionHas('error');

        $this->assertFalse(session()->has('codCongregante'));
    }

    public function test_external_login_accepts_supervisor(): void
    {
        Http::fake(['*' => Http::response([
            'error' => false, 'token' => 'supervisor-token',
            'roles' => [['ROL' => 'SUPERVISOR', 'ID' => 20]],
        ])]);

        $this->post(route('login.post'), [
            'user' => 'supervisor', 'contra' => 'clave',
        ])->assertRedirect(route('dashboard'));

        $this->assertSame('supervisor-token', session('codCongregante'));
    }

    public function test_automatic_shipment_early_validation_closes_transaction(): void
    {
        Envio::create([
            'guia' => 'AUTO-001', 'fecha_envio' => now(), 'monto_a_pagar' => 0,
            'estado_pago' => 'pendiente', 'tipo_generacion' => 'automatico',
            'periodo_inicio' => '2026-06-01', 'periodo_fin' => '2026-06-15', 'usuario' => 'test',
        ]);
        $transactionLevel = DB::transactionLevel();

        $this->withSession($this->adminSession())->post(route('envios.store-automatico'), [
            'periodo_inicio' => '2026-06-01', 'periodo_fin' => '2026-06-15',
            'fecha_envio' => '2026-06-16', 'monto_a_pagar' => 0,
        ])->assertSessionHasErrors('error');

        $this->assertSame($transactionLevel, DB::transactionLevel());
    }

    public function test_dashboard_stock_total_includes_subinventory_stock(): void
    {
        Libro::create(['nombre' => 'Libro total', 'precio' => 100, 'stock' => 10, 'stock_subinventario' => 5]);

        $this->withSession($this->adminSession())->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('stockTotal', 15)
            ->assertViewHas('valorInventario', 1500.0);
    }

    public function test_subinventory_import_acquires_locks_correctly(): void
    {
        $libro = Libro::create(['codigo_barras' => '777777', 'nombre' => 'Libro Lock', 'precio' => 100, 'stock' => 10, 'stock_subinventario' => 0]);
        $subinventario = \App\Models\SubInventario::create(['fecha_subinventario' => now(), 'descripcion' => 'Sub Lock', 'estado' => 'activo', 'usuario' => 'test']);

        // Crear Excel temporal
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'ID');
        $sheet->setCellValue('B1', 'Cantidad');
        $sheet->setCellValue('A2', $libro->id);
        $sheet->setCellValue('B2', 3);

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $tempFile = tempnam(sys_get_temp_dir(), 'sub_lock_');
        $xlsxPath = $tempFile . '.xlsx';
        $writer->save($xlsxPath);
        @unlink($tempFile);

        $file = new \Illuminate\Http\UploadedFile(
            $xlsxPath,
            'import.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        // Importar
        $this->withSession($this->adminSession())->post(route('subinventarios.import', $subinventario), [
            'archivo' => $file
        ])->assertRedirect(route('subinventarios.show', $subinventario));

        @unlink($xlsxPath);

        // Verificar transferencia de stock exitosa
        $this->assertSame(7, $libro->fresh()->stock);
        $this->assertSame(3, $libro->fresh()->stock_subinventario);
    }

    public function test_subinventory_import_accepts_csv_format(): void
    {
        $libro = Libro::create(['codigo_barras' => '123456', 'nombre' => 'Libro CSV', 'precio' => 100, 'stock' => 10, 'stock_subinventario' => 0]);
        $subinventario = \App\Models\SubInventario::create(['fecha_subinventario' => now(), 'descripcion' => 'Sub CSV', 'estado' => 'activo', 'usuario' => 'test']);

        $tempFile = tempnam(sys_get_temp_dir(), 'sub_csv_');
        $csvPath = $tempFile . '.csv';
        file_put_contents($csvPath, "ID,Cantidad\n{$libro->id},4\n");

        $file = new \Illuminate\Http\UploadedFile(
            $csvPath,
            'import.csv',
            'text/csv',
            null,
            true
        );

        $this->withSession($this->adminSession())
            ->post(route('subinventarios.import', $subinventario), ['archivo' => $file])
            ->assertRedirect(route('subinventarios.show', $subinventario));

        @unlink($csvPath);
        @unlink($tempFile);

        $this->assertSame(6, $libro->fresh()->stock);
        $this->assertSame(4, $libro->fresh()->stock_subinventario);
    }

    public function test_abono_deletion_requires_lock_and_fails_if_not_latest(): void
    {
        $cliente = Cliente::create(['nombre' => 'Cliente Abono']);
        $apartado = Apartado::create([
            'folio' => 'AP-TEST-AB', 'cliente_id' => $cliente->id, 'fecha_apartado' => now(),
            'monto_total' => 300, 'enganche' => 100, 'saldo_pendiente' => 200,
            'estado' => 'activo', 'usuario' => 'test', 'tipo_inventario' => 'general',
        ]);
        $abono1 = \App\Models\Abono::create([
            'apartado_id' => $apartado->id, 'fecha_abono' => now(), 'monto' => 50,
            'saldo_anterior' => 200, 'saldo_nuevo' => 150, 'metodo_pago' => 'efectivo', 'usuario' => 'test',
        ]);
        $abono2 = \App\Models\Abono::create([
            'apartado_id' => $apartado->id, 'fecha_abono' => now(), 'monto' => 50,
            'saldo_anterior' => 150, 'saldo_nuevo' => 100, 'metodo_pago' => 'efectivo', 'usuario' => 'test',
        ]);

        // Intentar borrar $abono1 (que no es el último abono, el último es $abono2)
        $this->withSession($this->adminSession())
            ->delete(route('apartados.abonos.destroy', $abono1))
            ->assertRedirect()
            ->assertSessionHas('error');

        // Borrar el último abono ($abono2)
        $this->withSession($this->adminSession())
            ->delete(route('apartados.abonos.destroy', $abono2))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('abonos', ['id' => $abono2->id]);
        $this->assertDatabaseHas('abonos', ['id' => $abono1->id]);
    }

    public function test_old_shipment_proof_file_is_preserved_on_db_rollback(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        
        $oldPath = 'comprobantes_pago/old_comprobante.pdf';
        \Illuminate\Support\Facades\Storage::disk('public')->put($oldPath, 'Old File Content');
        
        $envio = Envio::create([
            'guia' => 'AUTO-ROLLBACK', 'fecha_envio' => now(), 'monto_a_pagar' => 0,
            'estado_pago' => 'pendiente', 'comprobante_pago' => $oldPath, 'usuario' => 'test',
        ]);

        $newFile = \Illuminate\Http\UploadedFile::fake()->create('new_comprobante.pdf', 100);

        Envio::saving(function ($model) {
            if ($model->guia === 'AUTO-ROLLBACK') {
                throw new \Exception("Simulated DB Fail");
            }
        });

        $this->withSession($this->adminSession())
            ->post(route('envios.marcar-pagado', $envio), [
                'comprobante_pago' => $newFile,
                'fecha_pago' => now()->toDateString(),
                'referencia_pago' => 'REF-NEW',
            ]);

        // Verificar que el comprobante viejo SIGUE en storage
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($oldPath);
    }

    public function test_supervisor_cannot_access_sensitive_payment_and_shipment_routes(): void
    {
        $cliente = Cliente::create(['nombre' => 'Cliente test']);
        $venta = \App\Models\Venta::create([
            'folio' => 'V-TEST-001', 'cliente_id' => $cliente->id, 'fecha_venta' => now(),
            'total' => 500, 'total_pagado' => 0, 'estado' => 'completada', 'usuario' => 'test',
        ]);
        $envio = Envio::create([
            'guia' => 'AUTO-SUPERVISOR', 'fecha_envio' => now(), 'monto_a_pagar' => 0,
            'estado_pago' => 'pendiente', 'usuario' => 'test',
        ]);

        // 1. Crear pago (PagoController@store)
        $this->withSession($this->supervisorSession())
            ->post(route('pagos.store', $venta), ['monto' => 100, 'fecha_pago' => now(), 'metodo_pago' => 'efectivo'])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error');

        // 2. Marcar envío pagado (EnvioController@marcarPagado)
        $this->withSession($this->supervisorSession())
            ->post(route('envios.marcar-pagado', $envio), ['fecha_pago' => now(), 'referencia_pago' => 'REF'])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error');

        // 3. Crear envío automático (EnvioController@storeAutomatico)
        $this->withSession($this->supervisorSession())
            ->post(route('envios.store-automatico'), ['periodo_inicio' => '2026-06-01', 'periodo_fin' => '2026-06-15', 'fecha_envio' => '2026-06-16', 'monto_a_pagar' => 0])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error');
    }

    private function adminSession(): array
    {
        return [
            'codCongregante' => 'admin-test', 'username' => 'Admin',
            'roles' => [['ROL' => 'ADMIN LIBRERIA', 'ID' => 19]],
        ];
    }

    private function supervisorSession(): array
    {
        return [
            'codCongregante' => 'supervisor-test', 'username' => 'Supervisor',
            'roles' => [['ROL' => 'SUPERVISOR', 'ID' => 20]],
        ];
    }
}
