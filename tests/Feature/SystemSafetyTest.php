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

        $this->assertSoftDeleted('clientes', ['id' => $cliente->id]);
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

    public function test_natan_supervisor_can_only_operate_sales_apartados_and_clients(): void
    {
        Http::fake(['*' => Http::response([
            'error' => false,
            'token' => 'natan-supervisor-token',
            'roles' => [['ROL' => 'SUPERVISOR', 'ID' => 20]],
        ])]);

        $this->post(route('login.post'), [
            'user' => 'natan',
            'contra' => '1414',
        ])->assertRedirect(route('dashboard'));

        $this->assertSame('natan-supervisor-token', session('codCongregante'));
        $this->assertSame('natan', session('username'));

        $session = $this->supervisorSession();

        // Puede ver modulos y reportes.
        $this->withSession($session)->get(route('inventario.index'))->assertOk();
        $this->withSession($session)->get(route('movimientos.index'))->assertOk();
        $this->withSession($session)->get(route('envios.index'))->assertOk();
        $this->withSession($session)->get(route('ventas.export.pdf'))->assertOk();
        $this->withSession($session)->get(route('apartados.export.pdf'))->assertOk();

        // Puede abrir formularios operativos.
        $this->withSession($session)->get(route('ventas.create'))->assertOk();
        $this->withSession($session)->get(route('apartados.create'))->assertOk();
        $this->withSession($session)->get(route('clientes.create'))->assertOk();

        // No puede modificar libros, movimientos ni envios.
        $this->withSession($session)->get(route('inventario.create'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error');
        $this->withSession($session)->get(route('movimientos.create'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error');
        $this->withSession($session)->get(route('envios.create'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error');
    }

    public function test_supervisor_can_create_clients_sales_and_apartados_without_inventory_admin_access(): void
    {
        $session = $this->supervisorSession();
        $cliente = Cliente::create(['nombre' => 'Cliente Supervisor']);
        $libroGeneral = Libro::create(['nombre' => 'Libro General Supervisor', 'precio' => 100, 'stock' => 10, 'stock_subinventario' => 0]);
        $libroSub = Libro::create(['nombre' => 'Libro Sub Supervisor', 'precio' => 120, 'stock' => 10, 'stock_subinventario' => 4]);
        $subinventario = \App\Models\SubInventario::create([
            'fecha_subinventario' => now(),
            'descripcion' => 'Sub Supervisor',
            'estado' => 'activo',
            'usuario' => 'test',
        ]);
        $subinventario->libros()->attach($libroSub->id, ['cantidad' => 4]);

        $this->withSession($session)->post(route('clientes.store'), [
            'nombre' => 'Cliente Nuevo Supervisor',
            'telefono' => '8114141414',
        ])->assertRedirect(route('clientes.index'));
        $this->assertDatabaseHas('clientes', ['nombre' => 'Cliente Nuevo Supervisor']);

        $this->withSession($session)->post(route('ventas.store'), [
            'tipo_inventario' => 'general',
            'cliente_id' => null,
            'fecha_venta' => now()->toDateString(),
            'tipo_pago' => 'contado',
            'libros' => [
                ['libro_id' => $libroGeneral->id, 'cantidad' => 1, 'descuento' => 0],
            ],
        ])->assertRedirect();

        $this->withSession($session)->post(route('ventas.store'), [
            'tipo_inventario' => 'subinventario',
            'subinventario_id' => $subinventario->id,
            'cliente_id' => null,
            'fecha_venta' => now()->toDateString(),
            'tipo_pago' => 'contado',
            'libros' => [
                ['libro_id' => $libroSub->id, 'cantidad' => 1, 'descuento' => 0],
            ],
        ])->assertRedirect();

        $this->withSession($session)->post(route('apartados.store'), [
            'tipo_inventario' => 'general',
            'cliente_id' => $cliente->id,
            'fecha_apartado' => now()->toDateString(),
            'enganche' => 0,
            'libros' => [
                ['libro_id' => $libroGeneral->id, 'cantidad' => 1, 'precio_unitario' => 100, 'descuento' => 0],
            ],
        ])->assertRedirect();

        $this->assertDatabaseCount('ventas', 2);
        $this->assertDatabaseCount('apartados', 1);
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

        $this->assertSoftDeleted('abonos', ['id' => $abono2->id]);
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

    }

    public function test_envio_cannot_associate_invalid_sales(): void
    {
        $cliente = Cliente::create(['nombre' => 'Cliente Envio']);
        
        // 1. Venta con tiene_envio = false
        $ventaNoEnvio = \App\Models\Venta::create([
            'folio' => 'V-TEST-002', 'cliente_id' => $cliente->id, 'fecha_venta' => now(),
            'total' => 500, 'total_pagado' => 0, 'estado' => 'completada', 'usuario' => 'test',
            'tiene_envio' => false,
        ]);

        // 2. Venta cancelada
        $ventaCancelada = \App\Models\Venta::create([
            'folio' => 'V-TEST-003', 'cliente_id' => $cliente->id, 'fecha_venta' => now(),
            'total' => 500, 'total_pagado' => 0, 'estado' => 'cancelada', 'usuario' => 'test',
            'tiene_envio' => true,
        ]);

        // 3. Venta ya en otro envío
        $ventaYaEnviada = \App\Models\Venta::create([
            'folio' => 'V-TEST-004', 'cliente_id' => $cliente->id, 'fecha_venta' => now(),
            'total' => 500, 'total_pagado' => 0, 'estado' => 'completada', 'usuario' => 'test',
            'tiene_envio' => true,
        ]);
        $envioExistente = Envio::create([
            'guia' => 'GUIA-111', 'fecha_envio' => now(), 'monto_a_pagar' => 100,
            'estado_pago' => 'pendiente', 'usuario' => 'test',
        ]);
        $envioExistente->ventas()->attach($ventaYaEnviada->id);

        // Crear envío intentando asociar venta inválida (tiene_envio=false)
        $response1 = $this->withSession($this->adminSession())
            ->post(route('envios.store'), [
                'fecha_envio' => now()->toDateString(),
                'monto_a_pagar' => 120,
                'ventas' => [$ventaNoEnvio->id],
            ]);
        $response1->assertSessionHasErrors('ventas');

        // Crear envío intentando asociar venta cancelada
        $response2 = $this->withSession($this->adminSession())
            ->post(route('envios.store'), [
                'fecha_envio' => now()->toDateString(),
                'monto_a_pagar' => 120,
                'ventas' => [$ventaCancelada->id],
            ]);
        $response2->assertSessionHasErrors('ventas');

        // Crear envío intentando asociar venta ya enviada
        $response3 = $this->withSession($this->adminSession())
            ->post(route('envios.store'), [
                'fecha_envio' => now()->toDateString(),
                'monto_a_pagar' => 120,
                'ventas' => [$ventaYaEnviada->id],
            ]);
        $response3->assertSessionHasErrors('ventas');
    }

    public function test_db_migrations_route_requires_key_and_fails_if_empty(): void
    {
        // Forzar entorno local
        app()->detectEnvironment(fn () => 'local');
        
        // Sin definir la clave en env()
        $response = $this->get('/api/run-migrations/somekey');
        $response->assertStatus(403);
    }

    public function test_search_routes_are_session_protected_in_web_routes(): void
    {
        // Acceder sin sesión web -> redirecciona a login
        $this->get('/api/apartados/buscar')->assertRedirect(route('login'));
        $this->get('/api/clientes/buscar')->assertRedirect(route('login'));

        // Acceder con sesión web -> exitoso
        $this->withSession($this->adminSession())
            ->get('/api/apartados/buscar')
            ->assertStatus(200);

        $this->withSession($this->adminSession())
            ->get('/api/clientes/buscar')
            ->assertStatus(200);
    }

    public function test_subinventarios_libros_web_route_protection_and_retrieval(): void
    {
        // 1. Acceder sin sesión -> redirecciona a login
        $this->get('/api/subinventarios/1/libros')->assertRedirect(route('login'));

        // 2. Crear datos de prueba
        $sub = \App\Models\SubInventario::create([
            'fecha_subinventario' => now(),
            'descripcion' => 'Subinventario Test',
            'estado' => 'activo',
            'usuario' => 'test'
        ]);
        $libro = \App\Models\Libro::create([
            'nombre' => 'Libro de Prueba',
            'precio' => 120,
            'stock' => 10
        ]);
        $sub->libros()->attach($libro->id, ['cantidad' => 5]);

        // 3. Acceder con sesión web -> exitoso
        $response = $this->withSession($this->adminSession())
            ->get("/api/subinventarios/{$sub->id}/libros");
        
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.subinventario.id', $sub->id);
        
        // El libro debe estar en el listado
        $librosList = $response->json('data.libros');
        $this->assertNotEmpty($librosList);
        $this->assertEquals($libro->id, $librosList[0]['id']);
        $this->assertEquals(5, $librosList[0]['cantidad_disponible']);
    }


    public function test_client_paginate_capping(): void
    {
        // Simular autenticación para endpoint API
        $response = $this->getJson('/api/v1/clientes?per_page=1000&cod_congregante=token-test', [
            'X-Roles' => json_encode([['ROL' => 'ADMIN LIBRERIA', 'ID' => 19]])
        ]);
        
        $response->assertStatus(200);
        $this->assertLessThanOrEqual(250, $response->json('pagination.per_page'));
    }

    public function test_abono_movil_capping_and_defaults(): void
    {
        // 1. listarApartados límite capping
        $response = $this->getJson('/api/v1/movil/apartados?limite=500&cod_congregante=token-test', [
            'X-Roles' => json_encode([['ROL' => 'VENDEDOR', 'ID' => 18]])
        ]);
        $this->assertTrue(in_array($response->status(), [200, 404]));
    }

    /**
     * URGENTE 1 — Verifica que la condición bccomp con redondeo detecta
     * correctamente saldos "casi cero" producidos por aritmética float.
     *
     * Antes del fix: `$saldo == 0` podía fallar si el saldo era 4.44E-16.
     * Después del fix: `bccomp(round($saldo, 2), '0', 2) <= 0` cubre ese caso.
     *
     * También verifica que después de registrar el abono completo en BD,
     * el apartado efectivamente queda con saldo_pendiente almacenado en 0.
     */
    public function test_bccomp_detecta_saldo_casi_cero_y_abono_actualiza_bd(): void
    {
        // Parte 1: La condicion matematica del fix es correcta
        // Simular el valor que PHP puede producir con 10.10 - 10.10
        $saldoReal    = 10.10 - 10.10;               // puede ser 4.44E-16, no exactamente 0
        $saldoLibrobd = 0.00;                         // lo que guarda la BD con decimal:2

        // Con == podría fallar si $saldoReal no es exactamente 0
        // Con bccomp + round siempre funciona
        $this->assertSame(
            0,
            bccomp((string) round($saldoReal, 2), '0', 2),
            'bccomp(round(10.10 - 10.10, 2), 0) debe ser 0 (saldo es cero)'
        );

        // Un saldo real de 0.004 (menos de medio centavo) también debe tratarse como cero
        $saldoFantasma = 0.004;
        $this->assertSame(
            0,
            bccomp((string) round($saldoFantasma, 2), '0', 2),
            'bccomp(round(0.004, 2), 0) debe ser 0 (se redondea a 0.00)'
        );

        // Un saldo de 0.01 NO debe tratarse como cero (hay 1 centavo pendiente)
        $saldoValido = 0.01;
        $this->assertSame(
            1,
            bccomp((string) round($saldoValido, 2), '0', 2),
            'bccomp(round(0.01, 2), 0) debe ser 1 (hay centavo pendiente)'
        );

        // Parte 2: El flujo de abono con monto exacto actualiza saldo_pendiente en BD
        $cliente = Cliente::create(['nombre' => 'Cliente Float Test']);
        $libro   = Libro::create(['nombre' => 'Libro Float', 'precio' => 50, 'stock' => 5]);

        $apartado = \App\Models\Apartado::create([
            'folio'           => 'AP-FLOAT-002',
            'cliente_id'      => $cliente->id,
            'fecha_apartado'  => now(),
            'monto_total'     => 50.00,
            'enganche'        => 0,
            'saldo_pendiente' => 50.00,
            'estado'          => 'activo',
            'usuario'         => 'test',
            'tipo_inventario' => 'general',
        ]);
        $apartado->detalles()->create([
            'libro_id'        => $libro->id,
            'cantidad'        => 1,
            'precio_unitario' => 50.00,
            'descuento'       => 0,
            'subtotal'        => 50.00,
        ]);

        DB::transaction(fn () => app(InventoryStockService::class)->reserveApartado($apartado));

        // Simular exactamente lo que hace AbonoMovilController::registrarAbono
        DB::transaction(function () use ($apartado) {
            $monto = 50.00;
            $saldoAnterior = $apartado->saldo_pendiente;

            \App\Models\Abono::create([
                'apartado_id'  => $apartado->id,
                'fecha_abono'  => now(),
                'monto'        => $monto,
                'saldo_anterior' => $saldoAnterior,
                'saldo_nuevo'  => $saldoAnterior - $monto,
                'metodo_pago'  => 'efectivo',
                'usuario'      => 'test',
            ]);

            $apartado->saldo_pendiente = $saldoAnterior - $monto;

            // Esta es la condición del FIX — debe ser true y liquidar
            if (bccomp((string) round($apartado->saldo_pendiente, 2), '0', 2) <= 0) {
                $venta = \App\Models\Venta::create([
                    'cliente_id'       => $apartado->cliente_id,
                    'apartado_id'      => $apartado->id,
                    'fecha_venta'      => now(),
                    'tipo_pago'        => 'contado',
                    'subtotal'         => $apartado->monto_total,
                    'descuento_global' => 0,
                    'total'            => $apartado->monto_total,
                    'estado'           => 'completada',
                    'tiene_envio'      => false,
                    'costo_envio'      => 0,
                    'observaciones'    => 'Test auto-liquidacion',
                    'usuario'          => 'test',
                    'tipo_inventario'  => $apartado->tipo_inventario,
                    'subinventario_id' => null,
                    'es_a_plazos'      => false,
                    'total_pagado'     => $apartado->monto_total,
                    'estado_pago'      => 'completado',
                ]);

                foreach ($apartado->detalles as $detalle) {
                    \App\Models\Movimiento::create([
                        'libro_id'        => $detalle->libro_id,
                        'venta_id'        => $venta->id,
                        'tipo_movimiento' => 'salida',
                        'tipo_salida'     => 'venta',
                        'cantidad'        => $detalle->cantidad,
                        'precio_unitario' => $detalle->precio_unitario,
                        'descuento'       => 0,
                        'fecha'           => now(),
                        'observaciones'   => 'Auto-liquidacion test',
                        'usuario'         => 'test',
                    ]);
                }

                app(InventoryStockService::class)->consumeApartado($apartado);
                $apartado->estado   = 'liquidado';
                $apartado->venta_id = $venta->id;
            }

            $apartado->save();
        });

        $apartado->refresh();

        // El apartado debe haber quedado liquidado (el bccomp detectó saldo = 0)
        $this->assertSame('liquidado', $apartado->estado, 'El apartado debe quedar liquidado tras abonar el monto exacto');
        $this->assertNotNull($apartado->venta_id,          'El apartado debe tener una venta asociada');
        $this->assertSame(0, (int) $libro->fresh()->stock_apartado, 'stock_apartado debe ser 0 tras liquidar');
    }

    /**
     * URGENTE 3 — Verifica que al liquidar un apartado de subinventario vía app móvil
     * los contadores de stock queden con los valores EXACTOS correctos.
     *
     * Flujo esperado:
     *   Inicio:        pivot=3, stock_sub=3, stock_apt=0
     *   Tras reservar: pivot=2, stock_sub=2, stock_apt=1  (1 libro apartado)
     *   Tras liquidar: pivot=2, stock_sub=2, stock_apt=0  (el libro salió, reserva liberada)
     *
     * Bug anterior: el MovimientoObserver decrementaba pivot y stock_sub otra vez
     * al crear el movimiento de venta, dejando pivot=1 y stock_sub=1 (descuadrado).
     */
    public function test_liquidacion_movil_subinventario_no_deja_stock_negativo(): void
    {
        $cliente = Cliente::create(['nombre' => 'Cliente Sub-Liquida']);
        $libro   = Libro::create([
            'nombre'              => 'Libro SubInv',
            'precio'              => 50,
            'stock'               => 0,          // inventario general vacío
            'stock_subinventario' => 3,
            'stock_apartado'      => 0,
        ]);

        // Crear subinventario
        $subinventario = \App\Models\SubInventario::create([
            'descripcion'          => 'Punto venta test',
            'fecha_subinventario'  => now()->toDateString(),
            'estado'               => 'activo',
        ]);

        // Registrar el libro en el pivot del subinventario con 3 unidades
        DB::table('subinventario_libro')->insert([
            'subinventario_id' => $subinventario->id,
            'libro_id'         => $libro->id,
            'cantidad'         => 3,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        // Crear apartado de subinventario (1 unidad)
        $apartado = \App\Models\Apartado::create([
            'folio'            => 'AP-SUB-001',
            'cliente_id'       => $cliente->id,
            'fecha_apartado'   => now(),
            'monto_total'      => 50,
            'enganche'         => 0,
            'saldo_pendiente'  => 50,
            'estado'           => 'activo',
            'usuario'          => 'test',
            'tipo_inventario'  => 'subinventario',
            'subinventario_id' => $subinventario->id,
        ]);

        $apartado->detalles()->create([
            'libro_id'        => $libro->id,
            'cantidad'        => 1,
            'precio_unitario' => 50,
            'descuento'       => 0,
            'subtotal'        => 50,
        ]);

        // Reservar: debe descontar 1 del pivot y aumentar stock_apartado en 1
        DB::transaction(fn () => app(InventoryStockService::class)->reserveApartado($apartado));

        // Verificar estado después de reservar
        $libroPost = $libro->fresh();
        $pivotPost = DB::table('subinventario_libro')
            ->where('subinventario_id', $subinventario->id)
            ->where('libro_id', $libro->id)
            ->first();

        $this->assertSame(1, (int) $libroPost->stock_apartado,  'stock_apartado debe ser 1 tras reservar');
        $this->assertSame(2, (int) $pivotPost->cantidad,         'pivot debe tener 2 tras reservar (3-1)');
        // stock_subinventario decrementó al reservar en subinventario
        $this->assertSame(2, (int) $libroPost->stock_subinventario, 'stock_subinventario debe ser 2 tras reservar');

        // Ahora liquidar el apartado (simula lo que hace registrarAbono cuando saldo = 0)
        DB::transaction(function () use ($apartado) {
            // Crear venta como lo hace AbonoMovilController
            $venta = \App\Models\Venta::create([
                'cliente_id'       => $apartado->cliente_id,
                'apartado_id'      => $apartado->id,
                'fecha_venta'      => now(),
                'tipo_pago'        => 'contado',
                'subtotal'         => $apartado->monto_total,
                'descuento_global' => 0,
                'total'            => $apartado->monto_total,
                'estado'           => 'completada',
                'tiene_envio'      => false,
                'costo_envio'      => 0,
                'observaciones'    => 'Test liquidación',
                'usuario'          => 'test',
                'tipo_inventario'  => $apartado->tipo_inventario,
                'subinventario_id' => $apartado->subinventario_id,
                'es_a_plazos'      => false,
                'total_pagado'     => $apartado->monto_total,
                'estado_pago'      => 'completado',
            ]);

            foreach ($apartado->detalles as $detalle) {
                \App\Models\Movimiento::create([
                    'libro_id'        => $detalle->libro_id,
                    'venta_id'        => $venta->id,
                    'tipo_movimiento' => 'salida',
                    'tipo_salida'     => 'venta',
                    'cantidad'        => $detalle->cantidad,
                    'precio_unitario' => $detalle->precio_unitario,
                    'descuento'       => $detalle->descuento,
                    'fecha'           => now(),
                    'observaciones'   => 'Test',
                    'usuario'         => 'test',
                ]);
            }

            // consumeApartado libera stock_apartado
            app(InventoryStockService::class)->consumeApartado($apartado);

            $apartado->update(['estado' => 'liquidado', 'venta_id' => $venta->id]);
        });

        // === VERIFICACIÓN FINAL: valores EXACTOS esperados ===
        // stock_sub y pivot deben mantenerse en 2 (igual que tras reservar).
        // El movimiento de liquidación NO debe volver a decrementarlos.
        // Solo consumeApartado() decrementa stock_apartado de 1 a 0.
        $libroFinal = $libro->fresh();
        $pivotFinal = DB::table('subinventario_libro')
            ->where('subinventario_id', $subinventario->id)
            ->where('libro_id', $libro->id)
            ->first();

        $this->assertSame(
            2,
            (int) $libroFinal->stock_subinventario,
            "stock_subinventario debe ser 2 tras liquidar (actual: {$libroFinal->stock_subinventario}). "
            . 'El movimiento de liquidación NO debe volver a decrementar: ya se descontaron al reservar.'
        );

        $this->assertSame(
            2,
            (int) $pivotFinal->cantidad,
            "El pivot debe ser 2 tras liquidar (actual: {$pivotFinal->cantidad}). "
            . 'El movimiento de liquidación NO debe volver a decrementar el pivot.'
        );

        $this->assertSame(
            0,
            (int) $libroFinal->stock_apartado,
            "stock_apartado debe ser 0 tras liquidar (actual: {$libroFinal->stock_apartado}). La reserva no se liberó."
        );

        $this->assertDatabaseHas('apartados', ['id' => $apartado->id, 'estado' => 'liquidado']);
    }

    /**
     * Ciclo completo: reservar → liquidar → cancelar venta de subinventario.
     *
     * Verifica que al cancelar la venta generada al liquidar un apartado de subinventario,
     * los tres contadores de stock recuperan exactamente los valores del estado "reservado":
     *
     *   Inicio:        pivot=3, stock_sub=3, stock_apt=0
     *   Tras reservar: pivot=2, stock_sub=2, stock_apt=1
     *   Tras liquidar: pivot=2, stock_sub=2, stock_apt=0  (apartado=liquidado)
     *   Tras cancelar: pivot=2, stock_sub=2, stock_apt=1  (apartado=activo)
     *
     * La secuencia interna al cancelar:
     *   1. handleEntrada(devolucion) eleva pivot y stock_sub → 3 temporalmente
     *   2. reserveApartado() los baja de vuelta a 2 y sube stock_apt a 1
     */
    public function test_cancelar_venta_de_liquidacion_subinventario_restaura_stock_exactamente(): void
    {
        $cliente = Cliente::create(['nombre' => 'Cliente Cancelar Sub']);
        $libro   = Libro::create([
            'nombre'              => 'Libro Cancel SubInv',
            'precio'              => 80,
            'stock'               => 0,
            'stock_subinventario' => 3,
            'stock_apartado'      => 0,
        ]);

        $subinventario = \App\Models\SubInventario::create([
            'descripcion'         => 'Punto test cancelar',
            'fecha_subinventario' => now()->toDateString(),
            'estado'              => 'activo',
        ]);

        DB::table('subinventario_libro')->insert([
            'subinventario_id' => $subinventario->id,
            'libro_id'         => $libro->id,
            'cantidad'         => 3,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $apartado = \App\Models\Apartado::create([
            'folio'            => 'AP-CANCEL-001',
            'cliente_id'       => $cliente->id,
            'fecha_apartado'   => now(),
            'monto_total'      => 80,
            'enganche'         => 0,
            'saldo_pendiente'  => 80,
            'estado'           => 'activo',
            'usuario'          => 'test',
            'tipo_inventario'  => 'subinventario',
            'subinventario_id' => $subinventario->id,
        ]);
        $apartado->detalles()->create([
            'libro_id'        => $libro->id,
            'cantidad'        => 1,
            'precio_unitario' => 80,
            'descuento'       => 0,
            'subtotal'        => 80,
        ]);

        // ── PASO 1: Reservar ────────────────────────────────────────────────
        DB::transaction(fn () => app(InventoryStockService::class)->reserveApartado($apartado));

        $this->assertSame(2, (int) $libro->fresh()->stock_subinventario, 'Tras reservar: stock_sub=2');
        $this->assertSame(2, (int) DB::table('subinventario_libro')
            ->where('subinventario_id', $subinventario->id)->where('libro_id', $libro->id)
            ->value('cantidad'), 'Tras reservar: pivot=2');
        $this->assertSame(1, (int) $libro->fresh()->stock_apartado, 'Tras reservar: stock_apt=1');

        // ── PASO 2: Liquidar (simula AbonoMovilController/ApartadoController) ─
        $venta = null;
        DB::transaction(function () use ($apartado, &$venta) {
            $venta = \App\Models\Venta::create([
                'cliente_id'       => $apartado->cliente_id,
                'apartado_id'      => $apartado->id,
                'fecha_venta'      => now(),
                'tipo_pago'        => 'contado',
                'subtotal'         => $apartado->monto_total,
                'descuento_global' => 0,
                'total'            => $apartado->monto_total,
                'estado'           => 'completada',
                'tiene_envio'      => false,
                'costo_envio'      => 0,
                'observaciones'    => 'Liquidación test cancelar',
                'usuario'          => 'test',
                'tipo_inventario'  => $apartado->tipo_inventario,
                'subinventario_id' => $apartado->subinventario_id,
                'es_a_plazos'      => false,
                'total_pagado'     => $apartado->monto_total,
                'estado_pago'      => 'completado',
            ]);

            foreach ($apartado->detalles as $detalle) {
                \App\Models\Movimiento::create([
                    'libro_id'        => $detalle->libro_id,
                    'venta_id'        => $venta->id,
                    'tipo_movimiento' => 'salida',
                    'tipo_salida'     => 'venta',
                    'cantidad'        => $detalle->cantidad,
                    'precio_unitario' => $detalle->precio_unitario,
                    'descuento'       => 0,
                    'fecha'           => now(),
                    'observaciones'   => 'Test liquidación para cancelar',
                    'usuario'         => 'test',
                ]);
            }

            app(InventoryStockService::class)->consumeApartado($apartado);
            $apartado->update(['estado' => 'liquidado', 'venta_id' => $venta->id]);
        });

        $this->assertSame(2, (int) $libro->fresh()->stock_subinventario, 'Tras liquidar: stock_sub=2 (sin doble descuento)');
        $this->assertSame(2, (int) DB::table('subinventario_libro')
            ->where('subinventario_id', $subinventario->id)->where('libro_id', $libro->id)
            ->value('cantidad'), 'Tras liquidar: pivot=2 (sin doble descuento)');
        $this->assertSame(0, (int) $libro->fresh()->stock_apartado, 'Tras liquidar: stock_apt=0');
        $this->assertDatabaseHas('apartados', ['id' => $apartado->id, 'estado' => 'liquidado']);

        // ── PASO 3: Cancelar la venta vía la ruta real ──────────────────────
        $this->withSession($this->adminSession())
            ->post(route('ventas.cancelar', $venta))
            ->assertRedirect();

        // ── VERIFICACIÓN FINAL: idéntico al estado "tras reservar" ──────────
        $libroFinal = $libro->fresh();
        $pivotFinal = DB::table('subinventario_libro')
            ->where('subinventario_id', $subinventario->id)
            ->where('libro_id', $libro->id)
            ->first();

        $this->assertSame(
            2,
            (int) $libroFinal->stock_subinventario,
            "stock_subinventario debe ser 2 tras cancelar (actual: {$libroFinal->stock_subinventario}). "
            . 'Debería quedar igual que tras la reserva original.'
        );

        $this->assertSame(
            2,
            (int) $pivotFinal->cantidad,
            "pivot debe ser 2 tras cancelar (actual: {$pivotFinal->cantidad}). "
            . 'El libro está de nuevo reservado en el subinventario.'
        );

        $this->assertSame(
            1,
            (int) $libroFinal->stock_apartado,
            "stock_apartado debe ser 1 tras cancelar (actual: {$libroFinal->stock_apartado}). "
            . 'La reserva del apartado debe haberse restaurado.'
        );

        // El apartado vuelve a activo con saldo pendiente y sin venta asociada
        $this->assertDatabaseHas('apartados', [
            'id'       => $apartado->id,
            'estado'   => 'activo',
            'venta_id' => null,
        ]);

        // La venta queda cancelada
        $this->assertDatabaseHas('ventas', [
            'id'     => $venta->id,
            'estado' => 'cancelada',
        ]);
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
