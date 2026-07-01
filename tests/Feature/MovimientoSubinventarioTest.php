<?php

namespace Tests\Feature;

use App\Models\Libro;
use App\Models\Movimiento;
use App\Models\SubInventario;
use App\Models\AjusteMasivo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MovimientoSubinventarioTest extends TestCase
{
    use RefreshDatabase;

    private function adminSession(): array
    {
        return [
            'codCongregante' => 'test-admin',
            'username' => 'test',
            'roles' => [['ROL' => 'ADMIN LIBRERIA', 'ID' => 19]],
        ];
    }

    public function test_admin_can_register_stock_output_from_subinventory(): void
    {
        $libro = Libro::create([
            'nombre' => 'Libro en subinventario',
            'precio' => 100,
            'stock' => 10,
            'stock_subinventario' => 5,
        ]);

        $subinventario = SubInventario::create([
            'fecha_subinventario' => '2026-07-01',
            'descripcion' => 'Auditorio',
            'estado' => 'activo',
            'usuario' => 'Admin',
        ]);
        $subinventario->libros()->attach($libro->id, ['cantidad' => 5]);

        $response = $this->withSession($this->adminSession())
            ->post(route('movimientos.store'), [
                'libro_id' => $libro->id,
                'tipo_movimiento' => 'salida',
                'tipo_salida' => 'ajuste_negativo',
                'origen_stock' => 'subinventario',
                'subinventario_id' => $subinventario->id,
                'cantidad' => 2,
                'fecha' => '2026-07-01',
                'observaciones' => 'Corrección de stock cargado de más',
            ]);

        $response->assertRedirect(route('movimientos.index'));
        $response->assertSessionHasNoErrors();

        $libro->refresh();
        $this->assertSame(10, $libro->stock);
        $this->assertSame(3, $libro->stock_subinventario);

        $this->assertSame(3, (int) DB::table('subinventario_libro')
            ->where('subinventario_id', $subinventario->id)
            ->where('libro_id', $libro->id)
            ->value('cantidad'));

        $this->assertDatabaseHas('movimientos', [
            'libro_id' => $libro->id,
            'subinventario_id' => $subinventario->id,
            'tipo_movimiento' => 'salida',
            'tipo_salida' => 'ajuste_negativo',
            'cantidad' => 2,
        ]);
    }

    public function test_admin_can_register_stock_input_to_subinventory(): void
    {
        $libro = Libro::create([
            'nombre' => 'Libro con entrada sub',
            'precio' => 100,
            'stock' => 10,
            'stock_subinventario' => 5,
        ]);

        $subinventario = SubInventario::create([
            'fecha_subinventario' => '2026-07-01',
            'descripcion' => 'Auditorio',
            'estado' => 'activo',
            'usuario' => 'Admin',
        ]);
        $subinventario->libros()->attach($libro->id, ['cantidad' => 5]);

        $response = $this->withSession($this->adminSession())
            ->post(route('movimientos.store'), [
                'libro_id' => $libro->id,
                'tipo_movimiento' => 'entrada',
                'tipo_entrada' => 'ajuste_positivo',
                'origen_stock' => 'subinventario',
                'subinventario_id' => $subinventario->id,
                'cantidad' => 3,
                'fecha' => '2026-07-01',
                'observaciones' => 'Corrección positiva en punto de venta',
            ]);

        $response->assertRedirect(route('movimientos.index'));
        $response->assertSessionHasNoErrors();

        $libro->refresh();
        $this->assertSame(10, $libro->stock);
        $this->assertSame(8, $libro->stock_subinventario);

        $this->assertSame(8, (int) DB::table('subinventario_libro')
            ->where('subinventario_id', $subinventario->id)
            ->where('libro_id', $libro->id)
            ->value('cantidad'));

        $this->assertDatabaseHas('movimientos', [
            'libro_id' => $libro->id,
            'subinventario_id' => $subinventario->id,
            'tipo_movimiento' => 'entrada',
            'tipo_entrada' => 'ajuste_positivo',
            'cantidad' => 3,
        ]);
    }

    public function test_subinventory_output_requires_available_stock(): void
    {
        $libro = Libro::create([
            'nombre' => 'Libro con poco stock sub',
            'precio' => 100,
            'stock' => 10,
            'stock_subinventario' => 1,
        ]);

        $subinventario = SubInventario::create([
            'fecha_subinventario' => '2026-07-01',
            'descripcion' => 'Auditorio',
            'estado' => 'activo',
            'usuario' => 'Admin',
        ]);
        $subinventario->libros()->attach($libro->id, ['cantidad' => 1]);

        $response = $this->withSession($this->adminSession())
            ->post(route('movimientos.store'), [
                'libro_id' => $libro->id,
                'tipo_movimiento' => 'salida',
                'tipo_salida' => 'ajuste_negativo',
                'origen_stock' => 'subinventario',
                'subinventario_id' => $subinventario->id,
                'cantidad' => 2,
                'fecha' => '2026-07-01',
            ]);

        $response->assertSessionHasErrors('cantidad');

        $libro->refresh();
        $this->assertSame(10, $libro->stock);
        $this->assertSame(1, $libro->stock_subinventario);
        $this->assertSame(0, Movimiento::count());
    }

    public function test_admin_can_register_mass_adjustment_from_subinventory(): void
    {
        $libroA = Libro::create([
            'nombre' => 'Libro masivo A',
            'precio' => 100,
            'stock' => 10,
            'stock_subinventario' => 5,
        ]);
        $libroB = Libro::create([
            'nombre' => 'Libro masivo B',
            'precio' => 120,
            'stock' => 8,
            'stock_subinventario' => 3,
        ]);

        $subinventario = SubInventario::create([
            'fecha_subinventario' => '2026-07-01',
            'descripcion' => 'Auditorio',
            'estado' => 'activo',
            'usuario' => 'Admin',
        ]);
        $subinventario->libros()->attach($libroA->id, ['cantidad' => 5]);
        $subinventario->libros()->attach($libroB->id, ['cantidad' => 3]);

        $response = $this->withSession($this->adminSession())
            ->post(route('movimientos.masivo.store'), [
                'tipo_movimiento' => 'salida',
                'tipo_salida' => 'ajuste_negativo',
                'origen_stock' => 'subinventario',
                'subinventario_id' => $subinventario->id,
                'fecha' => '2026-07-01',
                'observaciones' => 'Corrección masiva',
                'items' => [
                    ['libro_id' => $libroA->id, 'cantidad' => 2],
                    ['libro_id' => $libroB->id, 'cantidad' => 1, 'observaciones' => 'Sobraba en reporte'],
                ],
            ]);

        $response->assertRedirect(route('movimientos.index'));
        $response->assertSessionHasNoErrors();

        $this->assertSame(1, AjusteMasivo::count());
        $ajuste = AjusteMasivo::firstOrFail();
        $this->assertSame(2, $ajuste->total_lineas);
        $this->assertSame(3, $ajuste->total_unidades);

        $this->assertSame(2, Movimiento::count());
        $this->assertDatabaseHas('movimientos', [
            'libro_id' => $libroA->id,
            'subinventario_id' => $subinventario->id,
            'ajuste_masivo_id' => $ajuste->id,
            'tipo_movimiento' => 'salida',
            'tipo_salida' => 'ajuste_negativo',
            'cantidad' => 2,
        ]);
        $this->assertDatabaseHas('movimientos', [
            'libro_id' => $libroB->id,
            'subinventario_id' => $subinventario->id,
            'ajuste_masivo_id' => $ajuste->id,
            'tipo_movimiento' => 'salida',
            'tipo_salida' => 'ajuste_negativo',
            'cantidad' => 1,
        ]);

        $this->assertSame(3, $libroA->fresh()->stock_subinventario);
        $this->assertSame(2, $libroB->fresh()->stock_subinventario);
        $this->assertSame(3, (int) DB::table('subinventario_libro')
            ->where('subinventario_id', $subinventario->id)
            ->where('libro_id', $libroA->id)
            ->value('cantidad'));
        $this->assertSame(2, (int) DB::table('subinventario_libro')
            ->where('subinventario_id', $subinventario->id)
            ->where('libro_id', $libroB->id)
            ->value('cantidad'));
    }

    public function test_mass_adjustment_rolls_back_when_a_line_has_insufficient_stock(): void
    {
        $libroA = Libro::create([
            'nombre' => 'Libro suficiente',
            'precio' => 100,
            'stock' => 10,
            'stock_subinventario' => 5,
        ]);
        $libroB = Libro::create([
            'nombre' => 'Libro insuficiente',
            'precio' => 120,
            'stock' => 8,
            'stock_subinventario' => 1,
        ]);

        $subinventario = SubInventario::create([
            'fecha_subinventario' => '2026-07-01',
            'descripcion' => 'Auditorio',
            'estado' => 'activo',
            'usuario' => 'Admin',
        ]);
        $subinventario->libros()->attach($libroA->id, ['cantidad' => 5]);
        $subinventario->libros()->attach($libroB->id, ['cantidad' => 1]);

        $response = $this->withSession($this->adminSession())
            ->from(route('movimientos.masivo.create'))
            ->post(route('movimientos.masivo.store'), [
                'tipo_movimiento' => 'salida',
                'tipo_salida' => 'ajuste_negativo',
                'origen_stock' => 'subinventario',
                'subinventario_id' => $subinventario->id,
                'fecha' => '2026-07-01',
                'items' => [
                    ['libro_id' => $libroA->id, 'cantidad' => 2],
                    ['libro_id' => $libroB->id, 'cantidad' => 2],
                ],
            ]);

        $response->assertRedirect(route('movimientos.masivo.create'));
        $response->assertSessionHasErrors('items.1.cantidad');

        $this->assertSame(0, AjusteMasivo::count());
        $this->assertSame(0, Movimiento::count());
        $this->assertSame(5, $libroA->fresh()->stock_subinventario);
        $this->assertSame(1, $libroB->fresh()->stock_subinventario);
    }

    public function test_admin_can_preview_mass_adjustment_excel_import(): void
    {
        $libro = Libro::create([
            'nombre' => 'Libro desde CSV',
            'codigo_barras' => 'CSV-001',
            'precio' => 100,
            'stock' => 10,
            'stock_subinventario' => 4,
        ]);

        $subinventario = SubInventario::create([
            'fecha_subinventario' => '2026-07-01',
            'descripcion' => 'Auditorio',
            'estado' => 'activo',
            'usuario' => 'Admin',
        ]);
        $subinventario->libros()->attach($libro->id, ['cantidad' => 4]);

        $archivo = UploadedFile::fake()->createWithContent(
            'ajuste.csv',
            "libro_id,codigo_barras,cantidad,observacion\n,CSV-001,2,Correccion CSV\n"
        );

        $response = $this->withSession($this->adminSession())
            ->post(route('movimientos.masivo.import.preview'), [
                'tipo_movimiento' => 'salida',
                'tipo_salida' => 'ajuste_negativo',
                'origen_stock' => 'subinventario',
                'subinventario_id' => $subinventario->id,
                'fecha' => '2026-07-01',
                'observaciones' => 'Importado por Excel',
                'archivo' => $archivo,
            ]);

        $response->assertOk();
        $response->assertViewIs('movimientos.create-masivo');
        $response->assertSee('Libro desde CSV');
        $response->assertSee('Correccion CSV');
        $response->assertSee('Confirmar Ajuste Masivo');

        $this->assertSame(0, AjusteMasivo::count());
        $this->assertSame(0, Movimiento::count());
    }

    public function test_admin_can_perform_full_end_to_end_excel_mass_adjustment_and_verify_stocks(): void
    {
        // 1. Create test books
        $libroA = Libro::create([
            'nombre' => 'Libro Masivo Integracion A',
            'codigo_barras' => 'BAR-00A',
            'precio' => 100,
            'stock' => 10,
            'stock_subinventario' => 0,
        ]);
        $libroB = Libro::create([
            'nombre' => 'Libro Masivo Integracion B',
            'codigo_barras' => 'BAR-00B',
            'precio' => 150,
            'stock' => 20,
            'stock_subinventario' => 0,
        ]);

        // 2. Perform Excel preview (input as CSV)
        $archivo = UploadedFile::fake()->createWithContent(
            'ajuste_integracion.csv',
            "libro_id,codigo_barras,cantidad,observacion\n,BAR-00A,3,Entrada masiva A\n,BAR-00B,5,Entrada masiva B\n"
        );

        $responsePreview = $this->withSession($this->adminSession())
            ->post(route('movimientos.masivo.import.preview'), [
                'tipo_movimiento' => 'entrada',
                'tipo_entrada' => 'compra',
                'origen_stock' => 'general',
                'fecha' => '2026-07-01',
                'observaciones' => 'Ajuste masivo integracion',
                'archivo' => $archivo,
            ]);

        $responsePreview->assertOk();
        $responsePreview->assertViewIs('movimientos.create-masivo');

        // Extract the valid preview items context and confirm
        $previewData = $responsePreview->viewData('preview');
        $this->assertCount(2, $previewData);
        $this->assertEmpty($previewData[0]['errores']);
        $this->assertEmpty($previewData[1]['errores']);

        // 3. Confirm adjustment (Store)
        $responseStore = $this->withSession($this->adminSession())
            ->post(route('movimientos.masivo.store'), [
                'tipo_movimiento' => 'entrada',
                'tipo_entrada' => 'compra',
                'origen_stock' => 'general',
                'fecha' => '2026-07-01',
                'observaciones' => 'Ajuste masivo integracion',
                'items' => [
                    [
                        'libro_id' => $libroA->id,
                        'cantidad' => 3,
                        'observaciones' => 'Entrada masiva A',
                    ],
                    [
                        'libro_id' => $libroB->id,
                        'cantidad' => 5,
                        'observaciones' => 'Entrada masiva B',
                    ],
                ]
            ]);

        $responseStore->assertRedirect(route('movimientos.index'));
        $responseStore->assertSessionHasNoErrors();

        // 4. Verify DB stock updates and records
        $libroA->refresh();
        $libroB->refresh();

        // Check general stock (10 + 3 = 13, and 20 + 5 = 25)
        $this->assertSame(13, $libroA->stock);
        $this->assertSame(25, $libroB->stock);

        // Verify AjusteMasivo record
        $this->assertSame(1, AjusteMasivo::count());
        $ajuste = AjusteMasivo::firstOrFail();
        $this->assertSame('Ajuste masivo integracion', $ajuste->observaciones);
        $this->assertSame(2, $ajuste->total_lineas);
        $this->assertSame(8, $ajuste->total_unidades);

        // Verify Movimiento records
        $this->assertSame(2, Movimiento::count());
        $this->assertDatabaseHas('movimientos', [
            'libro_id' => $libroA->id,
            'ajuste_masivo_id' => $ajuste->id,
            'tipo_movimiento' => 'entrada',
            'tipo_entrada' => 'compra',
            'cantidad' => 3,
        ]);
        $this->assertDatabaseHas('movimientos', [
            'libro_id' => $libroB->id,
            'ajuste_masivo_id' => $ajuste->id,
            'tipo_movimiento' => 'entrada',
            'tipo_entrada' => 'compra',
            'cantidad' => 5,
        ]);
    }
}
