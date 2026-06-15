<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Libro;
use App\Services\ExcelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExcelServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $excelService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->excelService = $this->app->make(ExcelService::class);
    }

    /**
     * Helper to create a temporary Excel file
     */
    protected function createTempExcel(array $rows): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Escribir encabezados
        $sheet->setCellValue('A1', 'Código de Barras');
        $sheet->setCellValue('B1', 'Nombre del Libro *');
        $sheet->setCellValue('C1', 'Precio *');
        $sheet->setCellValue('D1', 'Stock Inicial *');

        // Escribir datos
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $sheet->setCellValue('A' . $rowNumber, $row['codigo_barras'] ?? '');
            $sheet->setCellValue('B' . $rowNumber, $row['nombre'] ?? '');
            $sheet->setCellValue('C' . $rowNumber, $row['precio'] ?? '');
            $sheet->setCellValue('D' . $rowNumber, $row['stock'] ?? '');
        }

        $writer = new Xlsx($spreadsheet);
        $tempFile = tempnam(sys_get_temp_dir(), 'excel_test_');
        $xlsxPath = $tempFile . '.xlsx';
        $writer->save($xlsxPath);
        
        @unlink($tempFile);
        
        return $xlsxPath;
    }

    public function test_import_creates_new_books_successfully()
    {
        $rows = [
            [
                'codigo_barras' => '9781234567890',
                'nombre' => 'Libro de Prueba 1',
                'precio' => 150.50,
                'stock' => 10
            ],
            [
                'codigo_barras' => '9781234567891',
                'nombre' => 'Libro de Prueba 2',
                'precio' => 200.00,
                'stock' => 5
            ]
        ];

        $filePath = $this->createTempExcel($rows);

        $result = $this->excelService->import($filePath, ['skip_errors' => false]);

        @unlink($filePath);

        $this->assertEquals(2, $result['imported']);
        $this->assertEmpty($result['errors']);

        $this->assertDatabaseHas('libros', [
            'codigo_barras' => '9781234567890',
            'nombre' => 'Libro de Prueba 1',
            'precio' => 150.50,
            'stock' => 10
        ]);

        $this->assertDatabaseHas('libros', [
            'codigo_barras' => '9781234567891',
            'nombre' => 'Libro de Prueba 2',
            'precio' => 200.00,
            'stock' => 5
        ]);
    }

    public function test_import_fails_on_duplicate_barcode()
    {
        // Crear un libro previo
        Libro::create([
            'codigo_barras' => '9781234567890',
            'nombre' => 'Libro Existente',
            'precio' => 100.00,
            'stock' => 10
        ]);

        $rows = [
            [
                'codigo_barras' => '9781234567890', // Duplicado
                'nombre' => 'Libro Nuevo',
                'precio' => 150.00,
                'stock' => 5
            ]
        ];

        $filePath = $this->createTempExcel($rows);

        $result = $this->excelService->import($filePath, ['skip_errors' => true]);

        @unlink($filePath);

        $this->assertEquals(0, $result['imported']);
        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString("El código de barras '9781234567890' ya existe", $result['errors'][0]);
    }

    public function test_import_validates_required_fields()
    {
        $rows = [
            [
                'codigo_barras' => '9781234567892',
                'nombre' => '', // Faltante
                'precio' => 150.00,
                'stock' => 5
            ]
        ];

        $filePath = $this->createTempExcel($rows);

        $result = $this->excelService->import($filePath, ['skip_errors' => true]);

        @unlink($filePath);

        $this->assertEquals(0, $result['imported']);
        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString("Faltan campos obligatorios", $result['errors'][0]);
    }
}
