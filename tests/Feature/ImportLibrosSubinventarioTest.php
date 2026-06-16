<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Libro;
use App\Models\SubInventario;
use App\Models\Movimiento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Support\Facades\DB;

class ImportLibrosSubinventarioTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Helper to create a temporary Excel file for sub-inventory
     */
    protected function createTempExcel(array $rows): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Escribir encabezados
        $sheet->setCellValue('A1', 'ID');
        $sheet->setCellValue('B1', 'Cantidad');

        // Escribir datos
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $sheet->setCellValue('A' . $rowNumber, $row['id'] ?? '');
            $sheet->setCellValue('B' . $rowNumber, $row['cantidad'] ?? '');
        }

        $writer = new Xlsx($spreadsheet);
        $tempFile = tempnam(sys_get_temp_dir(), 'sub_excel_test_');
        $xlsxPath = $tempFile . '.xlsx';
        $writer->save($xlsxPath);
        
        @unlink($tempFile);
        
        return $xlsxPath;
    }

    public function test_unauthorized_user_is_redirected()
    {
        $subinventario = SubInventario::create([
            'fecha_subinventario' => '2026-06-15',
            'descripcion' => 'Subinventario Test',
            'estado' => 'activo',
            'usuario' => 'test_user'
        ]);

        $response = $this->post(route('subinventarios.import', $subinventario), [
            'archivo' => UploadedFile::fake()->create('test.xlsx')
        ]);

        $response->assertRedirect(route('login'));
    }

    public function test_import_to_inactive_subinventory_fails()
    {
        $subinventario = SubInventario::create([
            'fecha_subinventario' => '2026-06-15',
            'descripcion' => 'Subinventario Inactivo',
            'estado' => 'completado', // Inactivo para importar
            'usuario' => 'test_user'
        ]);

        $response = $this->withSession([
            'codCongregante' => 'admin_code',
            'roles' => [['ROL' => 'ADMIN LIBRERIA', 'ID' => 19]]
        ])->post(route('subinventarios.import', $subinventario), [
            'archivo' => UploadedFile::fake()->create('test.xlsx')
        ]);

        $response->assertRedirect(route('subinventarios.show', $subinventario));
        $response->assertSessionHas('error', 'Solo se pueden importar libros a sub-inventarios activos');
    }

    public function test_import_to_empty_subinventory_associates_books()
    {
        $libro1 = Libro::create([
            'codigo_barras' => '111111',
            'nombre' => 'Libro 1',
            'precio' => 100.00,
            'stock' => 50,
            'stock_subinventario' => 0
        ]);

        $libro2 = Libro::create([
            'codigo_barras' => '222222',
            'nombre' => 'Libro 2',
            'precio' => 150.00,
            'stock' => 30,
            'stock_subinventario' => 0
        ]);

        $subinventario = SubInventario::create([
            'fecha_subinventario' => '2026-06-15',
            'descripcion' => 'Subinventario Activo',
            'estado' => 'activo',
            'usuario' => 'admin_user'
        ]);

        $rows = [
            ['id' => $libro1->id, 'cantidad' => 10],
            ['id' => $libro2->id, 'cantidad' => 5]
        ];

        $xlsxPath = $this->createTempExcel($rows);
        $file = new UploadedFile(
            $xlsxPath,
            'import.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $response = $this->withSession([
            'codCongregante' => 'admin_code',
            'roles' => [['ROL' => 'ADMIN LIBRERIA', 'ID' => 19]]
        ])->post(route('subinventarios.import', $subinventario), [
            'archivo' => $file
        ]);

        @unlink($xlsxPath);

        $response->assertRedirect(route('subinventarios.show', $subinventario));
        $response->assertSessionHas('success');

        // Verificar base de datos del sub-inventario
        $this->assertDatabaseHas('subinventario_libro', [
            'subinventario_id' => $subinventario->id,
            'libro_id' => $libro1->id,
            'cantidad' => 10
        ]);

        $this->assertDatabaseHas('subinventario_libro', [
            'subinventario_id' => $subinventario->id,
            'libro_id' => $libro2->id,
            'cantidad' => 5
        ]);

        // Verificar actualización del stock_subinventario en los libros
        $libro1->refresh();
        $libro2->refresh();
        $this->assertEquals(10, $libro1->stock_subinventario);
        $this->assertEquals(5, $libro2->stock_subinventario);

        // Verificar decremento del stock general
        $this->assertEquals(40, $libro1->stock);
        $this->assertEquals(25, $libro2->stock);

        // Verificar creación de movimientos de inventario
        $this->assertDatabaseHas('movimientos', [
            'libro_id' => $libro1->id,
            'tipo_movimiento' => 'salida',
            'tipo_salida' => 'transferencia_subinventario',
            'cantidad' => 10
        ]);

        $this->assertDatabaseHas('movimientos', [
            'libro_id' => $libro2->id,
            'tipo_movimiento' => 'salida',
            'tipo_salida' => 'transferencia_subinventario',
            'cantidad' => 5
        ]);
    }

    public function test_import_updates_existing_book_quantity()
    {
        $libro = Libro::create([
            'codigo_barras' => '111111',
            'nombre' => 'Libro 1',
            'precio' => 100.00,
            'stock' => 50,
            'stock_subinventario' => 10
        ]);

        $subinventario = SubInventario::create([
            'fecha_subinventario' => '2026-06-15',
            'descripcion' => 'Subinventario Activo',
            'estado' => 'activo',
            'usuario' => 'admin_user'
        ]);

        // Pre-asociar libro con cantidad 10
        $subinventario->libros()->attach($libro->id, ['cantidad' => 10]);

        $rows = [
            ['id' => $libro->id, 'cantidad' => 5]
        ];

        $xlsxPath = $this->createTempExcel($rows);
        $file = new UploadedFile(
            $xlsxPath,
            'import.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $response = $this->withSession([
            'codCongregante' => 'admin_code',
            'roles' => [['ROL' => 'ADMIN LIBRERIA', 'ID' => 19]]
        ])->post(route('subinventarios.import', $subinventario), [
            'archivo' => $file
        ]);

        @unlink($xlsxPath);

        $response->assertRedirect(route('subinventarios.show', $subinventario));
        
        // Debe haberse sumado: 10 + 5 = 15
        $this->assertDatabaseHas('subinventario_libro', [
            'subinventario_id' => $subinventario->id,
            'libro_id' => $libro->id,
            'cantidad' => 15
        ]);

        $libro->refresh();
        $this->assertEquals(15, $libro->stock_subinventario);
        $this->assertEquals(45, $libro->stock);

        // Verificar creación del movimiento
        $this->assertDatabaseHas('movimientos', [
            'libro_id' => $libro->id,
            'tipo_movimiento' => 'salida',
            'tipo_salida' => 'transferencia_subinventario',
            'cantidad' => 5
        ]);
    }

    public function test_import_fails_on_insufficient_stock()
    {
        $libro = Libro::create([
            'codigo_barras' => '111111',
            'nombre' => 'Libro 1',
            'precio' => 100.00,
            'stock' => 5, // Stock insuficiente
            'stock_subinventario' => 0
        ]);

        $subinventario = SubInventario::create([
            'fecha_subinventario' => '2026-06-15',
            'descripcion' => 'Subinventario Activo',
            'estado' => 'activo',
            'usuario' => 'admin_user'
        ]);

        $rows = [
            ['id' => $libro->id, 'cantidad' => 10] // Solicita más del stock
        ];

        $xlsxPath = $this->createTempExcel($rows);
        $file = new UploadedFile(
            $xlsxPath,
            'import.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $response = $this->withSession([
            'codCongregante' => 'admin_code',
            'roles' => [['ROL' => 'ADMIN LIBRERIA', 'ID' => 19]]
        ])->post(route('subinventarios.import', $subinventario), [
            'archivo' => $file
        ]);

        @unlink($xlsxPath);

        $response->assertRedirect(route('subinventarios.show', $subinventario));
        
        // El libro NO se debió haber agregado debido a stock insuficiente
        $this->assertDatabaseMissing('subinventario_libro', [
            'subinventario_id' => $subinventario->id,
            'libro_id' => $libro->id
        ]);

        // Debe haber errores en la respuesta
        $response->assertSessionHas('errores_importacion');
        $errores = session('errores_importacion');
        $this->assertCount(1, $errores);
        $this->assertStringContainsString("Stock insuficiente", $errores[0]);
    }
}
