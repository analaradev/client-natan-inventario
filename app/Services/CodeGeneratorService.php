<?php

namespace App\Services;

use App\Models\Libro;
use App\Models\Venta;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Servicio para generación de códigos de barras y QR
 * 
 * Responsabilidades:
 * - Generar códigos de barras únicos
 * - Generar códigos QR en formato SVG
 * - Validar existencia de códigos
 */
class CodeGeneratorService
{
    // ============================================
    // CONSTANTES - CÓDIGOS DE BARRAS
    // ============================================
    
    private const BARCODE_PREFIX = 'LIB-';
    private const BARCODE_MAX_ATTEMPTS = 100;
    
    // ============================================
    // CONSTANTES - CÓDIGOS QR
    // ============================================
    
    private const QR_CANVAS_WIDTH = 500;
    private const QR_CANVAS_HEIGHT = 600;
    private const QR_SIZE = 450;
    private const QR_OFFSET_X = 25;
    
    // ============================================
    // SECCIÓN: CÓDIGOS DE BARRAS
    // ============================================
    
    /**
     * Genera un código de barras único
     */
    public function generateBarcode(): string
    {
        do {
            $codigo = $this->buildBarcode();
        } while ($this->barcodeExists($codigo));

        return $codigo;
    }

    /**
     * Genera un código único con sufijo si es necesario
     */
    public function generateUniqueBarcode(string $baseCode): string
    {
        if (!$this->barcodeExists($baseCode)) {
            return $baseCode;
        }

        $attempt = 0;
        $codigo = $baseCode;

        while ($this->barcodeExists($codigo) && $attempt < self::BARCODE_MAX_ATTEMPTS) {
            $attempt++;
            $codigo = $baseCode . '-' . $attempt;
        }

        if ($this->barcodeExists($codigo)) {
            throw new \RuntimeException(
                "No se pudo generar un código único después de " . self::BARCODE_MAX_ATTEMPTS . " intentos"
            );
        }

        return $codigo;
    }

    /**
     * Genera un código basado en número de fila (para importaciones)
     */
    public function generateBarcodeForRow(int $rowNumber): string
    {
        $codigo = self::BARCODE_PREFIX . date('Ymd') . '-' . str_pad($rowNumber, 4, '0', STR_PAD_LEFT);
        return $this->generateUniqueBarcode($codigo);
    }

    /**
     * Verifica si un código de barras ya existe
     */
    public function barcodeExists(string $codigo): bool
    {
        return Libro::where('codigo_barras', $codigo)->exists();
    }

    /**
     * Construye un código de barras aleatorio
     */
    private function buildBarcode(): string
    {
        return self::BARCODE_PREFIX . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    // ============================================
    // SECCIÓN: CÓDIGOS QR
    // ============================================
    
    /**
     * Genera código QR en formato SVG con información del libro
     */
    public function generateQrSvg(string $codigoBarras, string $nombreLibro): string
    {
        // Generar QR básico
        $qrSvg = QrCode::format('svg')
            ->size(self::QR_SIZE)
            ->errorCorrection('H')
            ->generate($codigoBarras);

        // Extraer contenido del SVG interno
        preg_match('/<svg[^>]*>(.*?)<\/svg>/s', $qrSvg, $matches);
        $innerSvgContent = $matches[1] ?? '';

        // Construir SVG completo con texto
        return $this->buildQrSvgTemplate($innerSvgContent, $codigoBarras, $nombreLibro);
    }

    /**
     * Construye el template SVG con QR y texto
     */
    private function buildQrSvgTemplate(string $qrContent, string $codigo, string $nombre): string
    {
        $canvasWidth = self::QR_CANVAS_WIDTH;
        $canvasHeight = self::QR_CANVAS_HEIGHT;
        $qrSize = self::QR_SIZE;
        $offsetX = self::QR_OFFSET_X;
        $centerX = $canvasWidth / 2;
        
        $nombreEscaped = htmlspecialchars($nombre, ENT_XML1, 'UTF-8');
        $codigoEscaped = htmlspecialchars($codigo, ENT_XML1, 'UTF-8');

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d">
                <rect width="%d" height="%d" fill="white"/>
                <svg x="%d" y="0" width="%d" height="%d" viewBox="0 0 %d %d">
                    %s
                </svg>
                <text x="%d" y="500" font-family="Arial, sans-serif" font-size="16" font-weight="bold" text-anchor="middle" fill="black">
                    %s
                </text>
                <text x="%d" y="525" font-family="Courier, monospace" font-size="14" text-anchor="middle" fill="black">
                    %s
                </text>
            </svg>',
            $canvasWidth, $canvasHeight, $canvasWidth, $canvasHeight,
            $canvasWidth, $canvasHeight,
            $offsetX, $qrSize, $qrSize, $qrSize, $qrSize,
            $qrContent,
            $centerX, $nombreEscaped,
            $centerX, $codigoEscaped
        );
    }

    // ============================================
    // SECCIÓN: CÓDIGOS DE VENTA
    // ============================================
    
    /**
     * Genera un código único para ventas (V-0001)
     */
    public function generateVentaCode(): string
    {
        $ultimaVenta = Venta::orderBy('id', 'desc')->first();
        $nextNumber = $ultimaVenta ? ($ultimaVenta->id + 1) : 1;
        
        do {
            $codigo = 'V-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
            $nextNumber++;
        } while (Venta::where('codigo', $codigo)->exists());

        return $codigo;
    }

    /**
     * Genera un código único para cualquier modelo
     * 
     * @param string $modelClass Clase del modelo (ej: 'Apartado', 'Venta')
     * @param string $prefix Prefijo del código (ej: 'AP', 'V')
     * @param string $field Campo que contiene el código (default: 'folio' o 'codigo')
     * @return string Código generado (ej: AP-2025-0001)
     */
    public function generateCode(string $modelClass, string $prefix, ?string $field = null): string
    {
        $modelClass = "App\\Models\\{$modelClass}";
        
        if (!class_exists($modelClass)) {
            throw new \Exception("Model {$modelClass} does not exist");
        }

        // Determinar el campo a usar (folio o codigo)
        $field = $field ?? (in_array($prefix, ['AP']) ? 'folio' : 'codigo');
        
        $year = date('Y');
        $pattern = "{$prefix}-{$year}-%";
        
        // Obtener el último código del año actual
        $lastRecord = $modelClass::where($field, 'like', $pattern)
            ->orderBy($field, 'desc')
            ->first();
        
        if ($lastRecord) {
            // Extraer el número del último código (ej: AP-2025-0001 -> 0001)
            $lastCode = $lastRecord->$field;
            preg_match('/-(\d+)$/', $lastCode, $matches);
            $lastNumber = isset($matches[1]) ? intval($matches[1]) : 0;
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }
        
        // Generar código con formato PREFIX-YYYY-####
        do {
            $codigo = sprintf("%s-%s-%04d", $prefix, $year, $nextNumber);
            $nextNumber++;
        } while ($modelClass::where($field, $codigo)->exists());

        return $codigo;
    }
}
