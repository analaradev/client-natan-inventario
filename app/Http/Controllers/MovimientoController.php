<?php

namespace App\Http\Controllers;

use App\Models\Movimiento;
use App\Models\Libro;
use App\Services\ExcelReportService;
use App\Services\PdfReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MovimientoController extends Controller
{
    protected $excelReportService;
    protected $pdfReportService;

    public function __construct(
        ExcelReportService $excelReportService,
        PdfReportService $pdfReportService
    ) {
        $this->excelReportService = $excelReportService;
        $this->pdfReportService = $pdfReportService;
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Movimiento::with('libro')
            ->orderBy('created_at', 'desc');

        // Filtrar por libro
        if ($request->filled('libro_id')) {
            $query->where('libro_id', $request->libro_id);
        }

        // Filtrar por tipo de movimiento (unificado)
        if ($request->filled('tipo_movimiento')) {
            $tipoMovimiento = $request->tipo_movimiento;
            
            // Verificar si es un tipo específico (entrada_compra, salida_venta, etc.)
            if (str_starts_with($tipoMovimiento, 'entrada_')) {
                $tipo = str_replace('entrada_', '', $tipoMovimiento);
                $query->where('tipo_movimiento', 'entrada')
                      ->where('tipo_entrada', $tipo);
            } elseif (str_starts_with($tipoMovimiento, 'salida_')) {
                $tipo = str_replace('salida_', '', $tipoMovimiento);
                $query->where('tipo_movimiento', 'salida')
                      ->where('tipo_salida', $tipo);
            } else {
                // Es un filtro general (solo "entrada" o "salida")
                $query->where('tipo_movimiento', $tipoMovimiento);
            }
        }

        // Filtrar por fecha
        if ($request->filled('fecha_desde')) {
            $query->whereDate('created_at', '>=', $request->fecha_desde);
        }
        if ($request->filled('fecha_hasta')) {
            $query->whereDate('created_at', '<=', $request->fecha_hasta);
        }

        // Calcular estadísticas antes de paginar (sobre todos los registros filtrados)
        $totalEntradas = (clone $query)->where('tipo_movimiento', 'entrada')->sum('cantidad');
        $totalSalidas = (clone $query)->where('tipo_movimiento', 'salida')->sum('cantidad');
        $totalMovimientos = (clone $query)->count();

        $movimientos = $query->paginate(10);
        $libros = Libro::orderBy('nombre')->get();

        return view('movimientos.index', compact('movimientos', 'libros', 'totalEntradas', 'totalSalidas', 'totalMovimientos'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $libros = Libro::orderBy('nombre')->get();
        return view('movimientos.create', compact('libros'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'libro_id' => 'required|exists:libros,id',
            'tipo_movimiento' => 'required|in:entrada,salida',
            'tipo_entrada' => 'required_if:tipo_movimiento,entrada',
            'tipo_salida' => 'required_if:tipo_movimiento,salida',
            'cantidad' => 'required|integer|min:1',
            'descuento' => 'nullable|numeric|min:0|max:100',
            'fecha' => 'nullable|date',
            'observaciones' => 'nullable|string|max:500',
        ], [
            'libro_id.required' => 'Debes seleccionar un libro',
            'libro_id.exists' => 'El libro seleccionado no existe',
            'tipo_movimiento.required' => 'Debes seleccionar el tipo de movimiento',
            'tipo_entrada.required_if' => 'Debes seleccionar el tipo de entrada',
            'tipo_salida.required_if' => 'Debes seleccionar el tipo de salida',
            'cantidad.required' => 'La cantidad es obligatoria',
            'cantidad.min' => 'La cantidad debe ser al menos 1',
            'descuento.numeric' => 'El descuento debe ser un número',
            'descuento.min' => 'El descuento no puede ser negativo',
            'descuento.max' => 'El descuento no puede ser mayor a 100%',
            'fecha.date' => 'La fecha debe ser válida',
        ]);

        DB::beginTransaction();
        try {
            $libro = Libro::where('id', $request->libro_id)->lockForUpdate()->firstOrFail();

            // Validar que hay stock suficiente para salidas
            if ($request->tipo_movimiento === 'salida' && $libro->stock < $request->cantidad) {
                return back()->withErrors(['cantidad' => 'No hay suficiente stock. Stock actual: ' . $libro->stock])
                    ->withInput();
            }

            // Crear el movimiento
            $movimiento = Movimiento::create([
                'libro_id' => $request->libro_id,
                'tipo_movimiento' => $request->tipo_movimiento,
                'tipo_entrada' => $request->tipo_movimiento === 'entrada' ? $request->tipo_entrada : null,
                'tipo_salida' => $request->tipo_movimiento === 'salida' ? $request->tipo_salida : null,
                'cantidad' => $request->cantidad,
                'precio_unitario' => $libro->precio, // Usar el precio del libro
                'descuento' => $request->descuento,
                'fecha' => $request->fecha ?? now()->toDateString(),
                'observaciones' => $request->observaciones,
                'usuario' => session('username')
            ]);

            // Actualizar el stock del libro
            if ($request->tipo_movimiento === 'entrada') {
                $libro->increment('stock', $request->cantidad);
            } else {
                $libro->decrement('stock', $request->cantidad);
            }

            DB::commit();

            return redirect()->route('movimientos.index')
                ->with('success', 'Movimiento registrado exitosamente. Stock actualizado: ' . $libro->fresh()->stock);
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Error al registrar el movimiento: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Movimiento $movimiento)
    {
        $movimiento->load('libro');
        
        // Obtener los últimos 5 movimientos del mismo libro (excluyendo el actual)
        $movimientosLibro = Movimiento::where('libro_id', $movimiento->libro_id)
            ->where('id', '!=', $movimiento->id)
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();
        
        return view('movimientos.show', compact('movimiento', 'movimientosLibro'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Movimiento $movimiento)
    {
        // No permitir editar movimientos para mantener integridad del inventario
        return redirect()->route('movimientos.index')
            ->with('warning', 'Los movimientos no pueden ser editados para mantener la integridad del inventario.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Movimiento $movimiento)
    {
        // No permitir actualizar
        return redirect()->route('movimientos.index')
            ->with('warning', 'Los movimientos no pueden ser editados.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Movimiento $movimiento)
    {
        // No permitir eliminar para mantener historial
        return redirect()->route('movimientos.index')
            ->with('warning', 'Los movimientos no pueden ser eliminados para mantener el historial del inventario.');
    }

    /**
     * Exportar movimientos filtrados a Excel
     */
    public function exportExcel(Request $request)
    {
        // Construir query con filtros
        $query = $this->buildFilteredQuery($request);
        $movimientos = $query->get();
        
        // Crear spreadsheet
        $spreadsheet = $this->excelReportService->createSpreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Título
        $row = $this->excelReportService->setTitle($sheet, 'REPORTE DE MOVIMIENTOS DE INVENTARIO', 'F', 1);
        $row++; // Espacio
        
        // Filtros aplicados
        $filtros = $this->buildFiltersList($request);
        $row = $this->excelReportService->setFilters($sheet, $filtros, $row);
        
        // Estadísticas
        if ($movimientos->count() > 0) {
            $totalEntradas = $movimientos->where('tipo_movimiento', 'entrada')->sum('cantidad');
            $totalSalidas = $movimientos->where('tipo_movimiento', 'salida')->sum('cantidad');
            
            $sheet->setCellValue('A' . $row, 'RESUMEN:');
            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
            $row++;
            
            $sheet->setCellValue('A' . $row, 'Total de movimientos: ' . $movimientos->count());
            $row++;
            $sheet->setCellValue('A' . $row, 'Total entradas: ' . $totalEntradas . ' unidades');
            $row++;
            $sheet->setCellValue('A' . $row, 'Total salidas: ' . $totalSalidas . ' unidades');
            $row += 2; // Espacio
        }
        
        // Encabezados de tabla
        $headers = ['ID', 'Fecha', 'Libro', 'Tipo', 'Cantidad', 'Precio Unit.', 'Descuento', 'Subtotal'];
        $row = $this->excelReportService->setTableHeaders($sheet, $headers, $row);
        
        // Datos
        $data = [];
        foreach ($movimientos as $movimiento) {
            $subtotal = $movimiento->precio_unitario * $movimiento->cantidad * (1 - ($movimiento->descuento / 100));
            $data[] = [
                $movimiento->id,
                $movimiento->created_at->format('d/m/Y H:i'),
                $movimiento->libro->nombre ?? 'N/A',
                $movimiento->getTipoLabel(),
                $movimiento->cantidad,
                '$' . number_format($movimiento->precio_unitario, 2),
                $movimiento->descuento ? $movimiento->descuento . '%' : '0%',
                '$' . number_format($subtotal, 2),
            ];
        }
        
        $lastRow = $this->excelReportService->fillData($sheet, $data, $row);
        
        // Auto ajustar columnas
        $this->excelReportService->autoSizeColumns($sheet, ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H']);
        
        // Descargar
        $filename = $this->excelReportService->generateFilename('reporte_movimientos');
        $this->excelReportService->download($spreadsheet, $filename);
    }

    /**
     * Exportar movimientos filtrados a PDF
     */
    public function exportPdf(Request $request)
    {
        // Construir query con filtros
        $query = $this->buildFilteredQuery($request);
        $movimientos = $query->get();
        
        // Preparar filtros
        $filtros = $this->buildFiltersList($request);
        
        // Calcular estadísticas
        $estadisticas = [
            'total' => $movimientos->count(),
            'entradas' => $movimientos->where('tipo_movimiento', 'entrada')->sum('cantidad'),
            'salidas' => $movimientos->where('tipo_movimiento', 'salida')->sum('cantidad'),
        ];
        
        // Obtener estilos base
        $styles = $this->pdfReportService->getBaseStyles();
        
        // Generar PDF
        $filename = $this->pdfReportService->generateFilename('reporte_movimientos');
        
        return $this->pdfReportService->generate(
            'movimientos.pdf-report',
            compact('movimientos', 'filtros', 'estadisticas', 'styles'),
            $filename,
            ['orientation' => 'landscape'] // Landscape para más columnas
        );
    }

    /**
     * Construir query con filtros (helper privado)
     */
    private function buildFilteredQuery(Request $request)
    {
        $query = Movimiento::with('libro')->orderBy('created_at', 'desc');

        // Filtrar por libro
        if ($request->filled('libro_id')) {
            $query->where('libro_id', $request->libro_id);
        }

        // Filtrar por tipo de movimiento
        if ($request->filled('tipo_movimiento')) {
            $tipoMovimiento = $request->tipo_movimiento;
            
            if (str_starts_with($tipoMovimiento, 'entrada_')) {
                $tipo = str_replace('entrada_', '', $tipoMovimiento);
                $query->where('tipo_movimiento', 'entrada')
                      ->where('tipo_entrada', $tipo);
            } elseif (str_starts_with($tipoMovimiento, 'salida_')) {
                $tipo = str_replace('salida_', '', $tipoMovimiento);
                $query->where('tipo_movimiento', 'salida')
                      ->where('tipo_salida', $tipo);
            } else {
                $query->where('tipo_movimiento', $tipoMovimiento);
            }
        }

        // Filtrar por fecha
        if ($request->filled('fecha_desde')) {
            $query->whereDate('created_at', '>=', $request->fecha_desde);
        }
        if ($request->filled('fecha_hasta')) {
            $query->whereDate('created_at', '<=', $request->fecha_hasta);
        }

        return $query;
    }

    /**
     * Construir lista de filtros aplicados (helper privado)
     */
    private function buildFiltersList(Request $request): array
    {
        $filtros = [];
        
        if ($request->filled('libro_id')) {
            $libro = Libro::find($request->libro_id);
            if ($libro) {
                $filtros[] = 'Libro: ' . $libro->nombre;
            }
        }
        
        if ($request->filled('tipo_movimiento')) {
            $tipoMovimiento = $request->tipo_movimiento;
            
            if (str_starts_with($tipoMovimiento, 'entrada_')) {
                $tipo = str_replace('entrada_', '', $tipoMovimiento);
                $tipoLabel = Movimiento::tiposEntrada()[$tipo] ?? $tipo;
                $filtros[] = 'Tipo: ' . $tipoLabel;
            } elseif (str_starts_with($tipoMovimiento, 'salida_')) {
                $tipo = str_replace('salida_', '', $tipoMovimiento);
                $tipoLabel = Movimiento::tiposSalida()[$tipo] ?? $tipo;
                $filtros[] = 'Tipo: ' . $tipoLabel;
            } else {
                $filtros[] = 'Tipo: ' . ucfirst($tipoMovimiento);
            }
        }
        
        if ($request->filled('fecha_desde')) {
            $filtros[] = 'Desde: ' . date('d/m/Y', strtotime($request->fecha_desde));
        }
        
        if ($request->filled('fecha_hasta')) {
            $filtros[] = 'Hasta: ' . date('d/m/Y', strtotime($request->fecha_hasta));
        }
        
        return $filtros;
    }
}
