<?php

namespace App\Services;

use App\Models\Libro;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Servicio para manejo de Excel
 * 
 * Responsabilidades:
 * - Generación de plantillas Excel
 * - Importación de libros desde Excel
 * - Validación de datos importados
 * - Procesamiento de filas
 */
class ExcelService
{
    // ============================================
    // CONSTANTES DE CONFIGURACIÓN
    // ============================================
    
    /**
     * Configuración de columnas para la plantilla
     */
    private const COLUMNS = [
        'A' => [
            'header' => 'Código de Barras',
            'width' => 20,
        ],
        'B' => [
            'header' => 'Nombre del Libro *',
            'width' => 40,
        ],
        'C' => [
            'header' => 'Precio *',
            'width' => 15,
        ],
        'D' => [
            'header' => 'Stock Inicial *',
            'width' => 15,
        ],
    ];

    /**
     * Colores del tema (gray-scale)
     */
    private const COLORS = [
        'HEADER_BG' => '1F2937',    // gray-800
        'HEADER_TEXT' => 'FFFFFF',   // white
    ];

    protected $codeGenerator;

    public function __construct(CodeGeneratorService $codeGenerator)
    {
        $this->codeGenerator = $codeGenerator;
    }

    // ============================================
    // SECCIÓN: PLANTILLAS
    // ============================================
    
    /**
     * Genera y devuelve un archivo Excel de plantilla
     */
    public function generateTemplate(): void
    {
        $spreadsheet = $this->createSpreadsheet();
        $fileName = $this->generateFileName();

        $this->sendDownloadHeaders($fileName);
        $this->writeToOutput($spreadsheet);
    }

    /**
     * Crea el spreadsheet configurado
     */
    private function createSpreadsheet(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $this->setHeaders($sheet);
        $this->applyHeaderStyles($sheet);
        $this->setColumnWidths($sheet);

        return $spreadsheet;
    }

    /**
     * Establece los encabezados de las columnas
     */
    private function setHeaders($sheet): void
    {
        foreach (self::COLUMNS as $column => $config) {
            $cell = $column . '1';
            $sheet->setCellValue($cell, $config['header']);
        }
    }

    /**
     * Aplica estilos a los encabezados
     */
    private function applyHeaderStyles($sheet): void
    {
        $headerRange = 'A1:D1';
        
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => self::COLORS['HEADER_TEXT']],
                'size' => 12,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => self::COLORS['HEADER_BG']],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
    }

    /**
     * Establece el ancho de las columnas
     */
    private function setColumnWidths($sheet): void
    {
        foreach (self::COLUMNS as $column => $config) {
            $sheet->getColumnDimension($column)->setWidth($config['width']);
        }
    }

    /**
     * Genera el nombre del archivo con fecha
     */
    private function generateFileName(): string
    {
        return 'plantilla_libros_' . date('Y-m-d') . '.xlsx';
    }

    /**
     * Envía los headers HTTP para descarga
     */
    private function sendDownloadHeaders(string $fileName): void
    {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');
    }

    /**
     * Escribe el spreadsheet a la salida
     */
    private function writeToOutput(Spreadsheet $spreadsheet): void
    {
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    // ============================================
    // SECCIÓN: IMPORTACIÓN
    // ============================================
    
    /**
     * Procesa la importación de un archivo Excel
     */
    public function import(string $filePath, array $options = []): array
    {
        $skipErrors = $options['skip_errors'] ?? true;

        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        // Remover encabezados
        array_shift($rows);

        $result = [
            'imported' => 0,
            'errors' => [],
        ];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // +2 porque array_shift quitó la primera fila

            // Saltar filas vacías
            if (empty(array_filter($row))) {
                continue;
            }

            try {
                $this->processRow($row, $rowNumber, $result);
            } catch (\Exception $e) {
                $result['errors'][] = "Fila {$rowNumber}: {$e->getMessage()}";
                
                if (!$skipErrors) {
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * Procesa una fila individual
     */
    private function processRow(array $row, int $rowNumber, array &$result): void
    {
        $data = $this->extractRowData($row, $rowNumber);
        $this->validateRowData($data);

        // Solo buscar libro existente si hay código de barras
        $libro = null;
        if (!empty($data['codigo_barras'])) {
            $libro = Libro::where('codigo_barras', $data['codigo_barras'])->first();
        }

        if ($libro) {
            throw new \Exception(
                "El código de barras '{$data['codigo_barras']}' ya existe"
            );
        }

        $this->createLibro($data);
        $result['imported']++;
    }

    /**
     * Extrae y prepara los datos de una fila
     */
    private function extractRowData(array $row, int $rowNumber): array
    {
        $codigoBarras = $row[0] ?? null;
        $nombre = $row[1] ?? null;
        $precio = $row[2] ?? null;
        $stock = $row[3] ?? null;

        // No generar código de barras automáticamente - dejar vacío si está vacío
        // El código de barras es opcional

        return [
            'codigo_barras' => $codigoBarras,
            'nombre' => $nombre,
            'precio' => $precio,
            'stock' => $stock,
        ];
    }

    /**
     * Valida los datos de una fila
     */
    private function validateRowData(array $data): void
    {
        // Validar campos obligatorios (nombre, precio y stock)
        if (empty($data['nombre']) || $data['precio'] === null || $data['stock'] === null) {
            throw new \Exception("Faltan campos obligatorios (Nombre, Precio, Stock)");
        }

        // Validar formato de precio
        if (!is_numeric($data['precio']) || $data['precio'] < 0) {
            throw new \Exception("Precio inválido ({$data['precio']})");
        }

        // Validar formato de stock
        if (!is_numeric($data['stock']) || $data['stock'] < 0 || floor($data['stock']) != $data['stock']) {
            throw new \Exception("Stock inválido ({$data['stock']})");
        }
    }

    /**
     * Crea un nuevo libro
     */
    private function createLibro(array $data): void
    {
        // Solo generar código único si el código no está vacío
        if (!empty($data['codigo_barras'])) {
            $data['codigo_barras'] = $this->codeGenerator->generateUniqueBarcode($data['codigo_barras']);
        } else {
            // Dejar como null si está vacío
            $data['codigo_barras'] = null;
        }

        $stockInicial = isset($data['stock']) ? (int)$data['stock'] : 0;
        unset($data['stock']);

        $libro = Libro::create($data);

        if ($stockInicial > 0) {
            \App\Models\Movimiento::create([
                'libro_id' => $libro->id,
                'tipo_movimiento' => 'entrada',
                'tipo_entrada' => 'compra',
                'cantidad' => $stockInicial,
                'precio_unitario' => $libro->precio,
                'fecha' => now()->toDateString(),
                'observaciones' => 'Inventario inicial (Importación Excel)',
                'usuario' => session('username') ?? auth()->user()->name ?? 'Admin',
            ]);
        }
    }

    /**
     * Genera mensaje de resultado
     */
    public function buildResultMessage(array $result): string
    {
        $messages = [];

        if ($result['imported'] > 0) {
            $messages[] = "{$result['imported']} libro(s) importado(s)";
        }

        if (!empty($result['errors'])) {
            $messages[] = count($result['errors']) . " error(es) encontrado(s)";
        }

        return implode('. ', $messages) . '.';
    }
}
