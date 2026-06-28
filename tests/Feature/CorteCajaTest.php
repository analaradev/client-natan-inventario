<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\CorteCaja;
use App\Models\IngresoCaja;
use App\Models\Libro;
use App\Models\Movimiento;
use App\Models\Pago;
use App\Models\SubInventario;
use App\Models\Venta;
use App\Services\IngresoCajaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CorteCajaTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_view_hides_subinventory_selector_for_general_cut(): void
    {
        SubInventario::create([
            'fecha_subinventario' => '2026-06-28',
            'descripcion' => 'Auditorio',
            'estado' => 'activo',
            'usuario' => 'test',
        ]);

        $this->withSession($this->supervisorSession())
            ->get(route('cortes.create', [
                'fecha_corte' => '2026-06-28',
                'tipo_inventario' => 'general',
            ]))
            ->assertOk()
            ->assertSee('Caja de')
            ->assertSee('id="subinventario_wrap" class="min-w-0 opacity-60"', false)
            ->assertSee('id="subinventario_id"', false)
            ->assertSee('disabled', false)
            ->assertDontSee('No aplica');
    }

    public function test_index_filters_by_single_cut_date(): void
    {
        CorteCaja::create([
            'fecha_corte' => '2026-06-27',
            'tipo_inventario' => 'general',
            'total_sistema' => 100,
            'total_reportado' => 100,
            'diferencia' => 0,
            'usuario_cierre' => 'natan',
        ]);

        CorteCaja::create([
            'fecha_corte' => '2026-06-28',
            'tipo_inventario' => 'general',
            'total_sistema' => 200,
            'total_reportado' => 200,
            'diferencia' => 0,
            'usuario_cierre' => 'natan',
        ]);

        $this->withSession($this->supervisorSession())
            ->get(route('cortes.index', ['fecha_corte' => '2026-06-27']))
            ->assertOk()
            ->assertSee('Fecha del corte')
            ->assertDontSee('Desde')
            ->assertDontSee('Hasta')
            ->assertSee('27/06/2026')
            ->assertDontSee('28/06/2026')
            ->assertSee('$100.00')
            ->assertDontSee('$200.00');
    }

    public function test_index_shows_export_links(): void
    {
        $this->withSession($this->supervisorSession())
            ->get(route('cortes.index', ['fecha_corte' => '2026-06-27']))
            ->assertOk()
            ->assertSee(route('cortes.export.excel', ['fecha_corte' => '2026-06-27']), false)
            ->assertSee(route('cortes.export.pdf', ['fecha_corte' => '2026-06-27']), false);
    }

    public function test_create_view_shows_subinventory_selector_only_for_subinventory_cut(): void
    {
        $subinventario = SubInventario::create([
            'fecha_subinventario' => '2026-06-28',
            'descripcion' => 'Auditorio',
            'estado' => 'activo',
            'usuario' => 'test',
        ]);

        $this->withSession($this->supervisorSession())
            ->get(route('cortes.create', [
                'fecha_corte' => '2026-06-28',
                'tipo_inventario' => 'subinventario',
                'subinventario_id' => $subinventario->id,
            ]))
            ->assertOk()
            ->assertSee('id="subinventario_wrap" class="min-w-0 "', false)
            ->assertSee('Auditorio')
            ->assertDontSee('id="subinventario_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg" disabled', false);
    }

    public function test_create_view_autoselects_only_subinventory(): void
    {
        $subinventario = SubInventario::create([
            'fecha_subinventario' => '2026-06-28',
            'descripcion' => 'Auditorio unico',
            'estado' => 'activo',
            'usuario' => 'test',
        ]);

        $this->withSession($this->supervisorSession())
            ->get(route('cortes.create', [
                'fecha_corte' => '2026-06-28',
                'tipo_inventario' => 'subinventario',
            ]))
            ->assertOk()
            ->assertSee('value="' . $subinventario->id . '" selected', false);
    }

    public function test_cash_income_is_created_from_sale_and_closed_in_corte(): void
    {
        $venta = Venta::create([
            'fecha_venta' => '2026-06-28',
            'tipo_pago' => 'contado',
            'metodo_pago' => 'efectivo',
            'subtotal' => 150,
            'total' => 170,
            'total_pagado' => 170,
            'estado' => 'completada',
            'estado_pago' => 'completado',
            'tiene_envio' => true,
            'costo_envio' => 20,
            'usuario' => 'natan',
            'tipo_inventario' => 'general',
            'es_a_plazos' => false,
        ]);

        app(IngresoCajaService::class)->registrarVenta($venta);

        $this->assertDatabaseHas('ingresos_caja', [
            'venta_id' => $venta->id,
            'concepto' => 'venta',
            'monto' => 150,
            'metodo_pago' => 'efectivo',
            'estado' => 'activo',
        ]);

        $this->assertDatabaseHas('ingresos_caja', [
            'venta_id' => $venta->id,
            'concepto' => 'envio',
            'monto' => 20,
            'metodo_pago' => 'efectivo',
            'estado' => 'activo',
        ]);

        $this->withSession($this->supervisorSession())
            ->post(route('cortes.store'), [
                'fecha_corte' => '2026-06-28',
                'tipo_inventario' => 'general',
                'total_reportado' => 170,
            ])
            ->assertRedirect();

        $corte = CorteCaja::firstOrFail();
        $this->assertSame('170.00', $corte->total_sistema);
        $this->assertSame('0.00', $corte->diferencia);
        $this->assertCount(2, $corte->ingresos);
    }

    public function test_credit_payment_creates_income_with_real_payment_method(): void
    {
        $cliente = Cliente::create(['nombre' => 'Cliente credito']);
        $venta = Venta::create([
            'cliente_id' => $cliente->id,
            'fecha_venta' => '2026-06-28',
            'tipo_pago' => 'credito',
            'metodo_pago' => 'no_especificado',
            'subtotal' => 300,
            'total' => 300,
            'total_pagado' => 0,
            'estado' => 'completada',
            'estado_pago' => 'pendiente',
            'usuario' => 'natan',
            'tipo_inventario' => 'general',
            'es_a_plazos' => true,
        ]);

        $pago = Pago::create([
            'venta_id' => $venta->id,
            'fecha_pago' => '2026-06-28',
            'monto' => 125,
            'metodo_pago' => 'transferencia',
        ]);

        app(IngresoCajaService::class)->registrarPago($pago);

        $ingreso = IngresoCaja::firstOrFail();
        $this->assertSame('pago_venta', $ingreso->concepto);
        $this->assertSame('transferencia', $ingreso->metodo_pago);
        $this->assertSame('125.00', $ingreso->monto);
    }

    public function test_subinventory_cut_requires_selected_subinventory(): void
    {
        $this->withSession($this->supervisorSession())
            ->from(route('cortes.create', ['tipo_inventario' => 'subinventario']))
            ->post(route('cortes.store'), [
                'fecha_corte' => '2026-06-28',
                'tipo_inventario' => 'subinventario',
                'total_reportado' => 10,
            ])
            ->assertRedirect(route('cortes.create', ['tipo_inventario' => 'subinventario']))
            ->assertSessionHasErrors('subinventario_id');
    }

    public function test_cut_requires_reported_total(): void
    {
        $this->withSession($this->supervisorSession())
            ->from(route('cortes.create'))
            ->post(route('cortes.store'), [
                'fecha_corte' => '2026-06-28',
                'tipo_inventario' => 'general',
            ])
            ->assertRedirect(route('cortes.create'))
            ->assertSessionHasErrors('total_reportado');
    }

    public function test_cut_without_income_returns_clear_error(): void
    {
        $this->withSession($this->supervisorSession())
            ->from(route('cortes.create'))
            ->post(route('cortes.store'), [
                'fecha_corte' => '2026-06-28',
                'tipo_inventario' => 'general',
                'total_reportado' => 0,
            ])
            ->assertRedirect(route('cortes.create'))
            ->assertSessionHasErrors('error');

        $this->assertDatabaseCount('cortes_caja', 0);
    }

    public function test_same_income_cannot_be_closed_twice(): void
    {
        $venta = Venta::create([
            'fecha_venta' => '2026-06-28',
            'tipo_pago' => 'contado',
            'metodo_pago' => 'efectivo',
            'subtotal' => 80,
            'total' => 80,
            'total_pagado' => 80,
            'estado' => 'completada',
            'estado_pago' => 'completado',
            'usuario' => 'natan',
            'tipo_inventario' => 'general',
            'es_a_plazos' => false,
        ]);

        app(IngresoCajaService::class)->registrarVenta($venta);

        $payload = [
            'fecha_corte' => '2026-06-28',
            'tipo_inventario' => 'general',
            'total_reportado' => 80,
        ];

        $this->withSession($this->supervisorSession())
            ->post(route('cortes.store'), $payload)
            ->assertRedirect();

        $this->withSession($this->supervisorSession())
            ->from(route('cortes.create'))
            ->post(route('cortes.store'), $payload)
            ->assertRedirect(route('cortes.create'))
            ->assertSessionHasErrors('error');

        $this->assertDatabaseCount('cortes_caja', 1);
    }

    public function test_show_cut_lists_sold_products(): void
    {
        $libro = Libro::create([
            'nombre' => 'Pan diario',
            'codigo_barras' => 'PAN-001',
            'precio' => 50,
            'stock' => 10,
        ]);

        $venta = Venta::create([
            'fecha_venta' => '2026-06-28',
            'tipo_pago' => 'contado',
            'metodo_pago' => 'efectivo',
            'subtotal' => 100,
            'total' => 100,
            'total_pagado' => 100,
            'estado' => 'completada',
            'estado_pago' => 'completado',
            'usuario' => 'natan',
            'tipo_inventario' => 'general',
            'es_a_plazos' => false,
        ]);

        Movimiento::create([
            'venta_id' => $venta->id,
            'libro_id' => $libro->id,
            'tipo_movimiento' => 'salida',
            'tipo_salida' => 'venta',
            'cantidad' => 2,
            'precio_unitario' => 50,
            'descuento' => 0,
            'fecha' => '2026-06-28',
            'usuario' => 'natan',
        ]);

        $ingreso = IngresoCaja::create([
            'fecha' => '2026-06-28 10:00:00',
            'monto' => 100,
            'metodo_pago' => 'efectivo',
            'concepto' => 'venta',
            'venta_id' => $venta->id,
            'tipo_inventario' => 'general',
            'estado' => 'activo',
            'usuario' => 'natan',
        ]);

        $corte = CorteCaja::create([
            'fecha_corte' => '2026-06-28',
            'tipo_inventario' => 'general',
            'total_efectivo' => 100,
            'total_sistema' => 100,
            'total_reportado' => 100,
            'diferencia' => 0,
            'usuario_cierre' => 'natan',
        ]);
        $corte->ingresos()->attach($ingreso->id);

        $this->withSession($this->supervisorSession())
            ->get(route('cortes.show', $corte))
            ->assertOk()
            ->assertSee('Productos del corte')
            ->assertSee('Pan diario')
            ->assertSee('PAN-001')
            ->assertSee('Venta')
            ->assertSee('Venta #' . $venta->id)
            ->assertSee('$100.00');
    }

    public function test_pdf_exports_render_for_list_and_individual_cut(): void
    {
        $corte = CorteCaja::create([
            'fecha_corte' => '2026-06-27',
            'tipo_inventario' => 'general',
            'total_efectivo' => 100,
            'total_sistema' => 100,
            'total_reportado' => 100,
            'diferencia' => 0,
            'usuario_cierre' => 'natan',
        ]);

        $ingreso = IngresoCaja::create([
            'fecha' => '2026-06-27 10:00:00',
            'monto' => 100,
            'metodo_pago' => 'efectivo',
            'concepto' => 'venta',
            'tipo_inventario' => 'general',
            'estado' => 'activo',
            'usuario' => 'natan',
        ]);
        $corte->ingresos()->attach($ingreso->id);

        $this->withSession($this->supervisorSession())
            ->get(route('cortes.export.pdf', ['fecha_corte' => '2026-06-27']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->withSession($this->supervisorSession())
            ->get(route('cortes.export-individual.pdf', $corte))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

    }

    private function supervisorSession(): array
    {
        return [
            'authenticated' => true,
            'username' => 'natan',
            'roles' => [['ROL' => 'SUPERVISOR']],
            'codCongregante' => 'natan-supervisor-token',
        ];
    }
}
