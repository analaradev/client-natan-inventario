<?php

namespace App\Http\Controllers;

use App\Models\Movimiento;
use App\Models\Libro;
use App\Models\SubInventario;
use App\Models\AjusteMasivo;
use App\Services\ExcelReportService;
use App\Services\PdfReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

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
        $query = Movimiento::with(['libro', 'subinventario', 'ajusteMasivo'])
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
        [$libros, $subinventarios] = $this->movementFormData();

        return view('movimientos.create', compact('libros', 'subinventarios'));
    }

    public function createMasivo()
    {
        [$libros, $subinventarios] = $this->movementFormData();

        return view('movimientos.create-masivo', compact('libros', 'subinventarios'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if (in_array($request->input('tipo_movimiento'), ['entrada', 'salida'], true) && !$request->filled('origen_stock')) {
            $request->merge(['origen_stock' => 'general']);
        }

        $request->validate([
            'libro_id' => 'required|exists:libros,id',
            'tipo_movimiento' => 'required|in:entrada,salida',
            'tipo_entrada' => 'required_if:tipo_movimiento,entrada',
            'tipo_salida' => 'required_if:tipo_movimiento,salida',
            'origen_stock' => 'nullable|in:general,subinventario',
            'subinventario_id' => 'nullable|required_if:origen_stock,subinventario|exists:subinventarios,id',
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
            'origen_stock.required_if' => 'Debes seleccionar de dónde saldrá el stock',
            'subinventario_id.required_if' => 'Debes seleccionar el subinventario',
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

            $esMovimientoSubinventario = $request->origen_stock === 'subinventario';

            if ($esMovimientoSubinventario) {
                if ($request->tipo_entrada === 'devolucion_subinventario' || $request->tipo_salida === 'transferencia_subinventario') {
                    DB::rollBack();
                    return back()->withErrors([
                        'origen_stock' => 'Las transferencias entre inventario general y subinventario se manejan desde Subinventarios. En Movimientos usa ajustes, mermas, donaciones, compras o devoluciones propias de la ubicación.'
                    ])->withInput();
                }

                $subinventario = SubInventario::whereKey($request->subinventario_id)
                    ->where('estado', 'activo')
                    ->lockForUpdate()
                    ->first();

                if (!$subinventario) {
                    DB::rollBack();
                    return back()->withErrors(['subinventario_id' => 'El subinventario seleccionado no está activo o no existe.'])
                        ->withInput();
                }

                $pivot = DB::table('subinventario_libro')
                    ->where('subinventario_id', $subinventario->id)
                    ->where('libro_id', $libro->id)
                    ->lockForUpdate()
                    ->first();

                $stockDisponible = $pivot ? (int) $pivot->cantidad : 0;
                if ($request->tipo_movimiento === 'salida' && $stockDisponible < $request->cantidad) {
                    DB::rollBack();
                    return back()->withErrors(['cantidad' => 'No hay suficiente stock disponible en el subinventario. Stock disponible: ' . $stockDisponible])
                        ->withInput();
                }
            } elseif ($request->tipo_movimiento === 'salida') {
                $reservadoGeneral = (int) \Illuminate\Support\Facades\DB::table('apartado_detalles as ad')
                    ->join('apartados as a', 'a.id', '=', 'ad.apartado_id')
                    ->where('ad.libro_id', $libro->id)
                    ->where('a.tipo_inventario', 'general')
                    ->where('a.estado', 'activo')
                    ->sum('ad.cantidad');
                
                $stockDisponible = $libro->stock - $reservadoGeneral;

                if ($stockDisponible < $request->cantidad) {
                    \Illuminate\Support\Facades\DB::rollBack();
                    return back()->withErrors(['cantidad' => 'No hay suficiente stock disponible (excluyendo apartados activos). Stock disponible: ' . $stockDisponible])
                        ->withInput();
                }
            }

            // Crear el movimiento
            $movimiento = Movimiento::create([
                'libro_id' => $request->libro_id,
                'subinventario_id' => $esMovimientoSubinventario ? $request->subinventario_id : null,
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



            DB::commit();

            return redirect()->route('movimientos.index')
                ->with('success', 'Movimiento registrado exitosamente. Stock actualizado: ' . $libro->fresh()->stock);
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Error al registrar el movimiento: ' . $e->getMessage()])
                ->withInput();
        }
    }

    public function storeMasivo(Request $request)
    {
        if (in_array($request->input('tipo_movimiento'), ['entrada', 'salida'], true) && !$request->filled('origen_stock')) {
            $request->merge(['origen_stock' => 'general']);
        }

        $validated = $request->validate([
            'tipo_movimiento' => 'required|in:entrada,salida',
            'tipo_entrada' => 'required_if:tipo_movimiento,entrada',
            'tipo_salida' => 'required_if:tipo_movimiento,salida',
            'origen_stock' => 'required|in:general,subinventario',
            'subinventario_id' => 'nullable|required_if:origen_stock,subinventario|exists:subinventarios,id',
            'fecha' => 'nullable|date',
            'observaciones' => 'nullable|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.libro_id' => 'required|exists:libros,id|distinct',
            'items.*.cantidad' => 'required|integer|min:1',
            'items.*.observaciones' => 'nullable|string|max:255',
        ], [
            'items.required' => 'Debes agregar al menos un libro',
            'items.min' => 'Debes agregar al menos un libro',
            'items.*.libro_id.required' => 'Cada línea debe tener un libro',
            'items.*.libro_id.distinct' => 'No repitas el mismo libro dentro del ajuste masivo',
            'items.*.cantidad.required' => 'Cada línea debe tener cantidad',
            'items.*.cantidad.min' => 'La cantidad debe ser al menos 1',
            'subinventario_id.required_if' => 'Debes seleccionar el subinventario',
        ]);

        $tipoEntrada = $validated['tipo_movimiento'] === 'entrada' ? $validated['tipo_entrada'] : null;
        $tipoSalida = $validated['tipo_movimiento'] === 'salida' ? $validated['tipo_salida'] : null;

        if ($tipoEntrada === 'devolucion_subinventario' || $tipoSalida === 'transferencia_subinventario') {
            return back()->withErrors([
                'tipo_movimiento' => 'Las transferencias entre inventario general y subinventario se manejan desde Subinventarios. En Ajuste Masivo usa ajustes, mermas, donaciones, compras o devoluciones propias de la ubicación.'
            ])->withInput();
        }

        DB::beginTransaction();
        try {
            $esSubinventario = $validated['origen_stock'] === 'subinventario';
            $subinventario = null;

            if ($esSubinventario) {
                $subinventario = SubInventario::whereKey($validated['subinventario_id'])
                    ->where('estado', 'activo')
                    ->lockForUpdate()
                    ->first();

                if (!$subinventario) {
                    DB::rollBack();
                    return back()->withErrors(['subinventario_id' => 'El subinventario seleccionado no está activo o no existe.'])
                        ->withInput();
                }
            }

            $items = collect($validated['items'])->values();
            $libroIds = $items->pluck('libro_id')->map(fn ($id) => (int) $id)->all();
            $libros = Libro::whereIn('id', $libroIds)->lockForUpdate()->get()->keyBy('id');

            foreach ($items as $index => $item) {
                $libro = $libros->get((int) $item['libro_id']);
                $cantidad = (int) $item['cantidad'];

                if (!$libro) {
                    DB::rollBack();
                    return back()->withErrors(["items.{$index}.libro_id" => 'Uno de los libros seleccionados no existe.'])
                        ->withInput();
                }

                if ($validated['tipo_movimiento'] === 'salida') {
                    if ($esSubinventario) {
                        $pivot = DB::table('subinventario_libro')
                            ->where('subinventario_id', $subinventario->id)
                            ->where('libro_id', $libro->id)
                            ->lockForUpdate()
                            ->first();

                        $stockDisponible = $pivot ? (int) $pivot->cantidad : 0;
                        if ($stockDisponible < $cantidad) {
                            DB::rollBack();
                            return back()->withErrors([
                                "items.{$index}.cantidad" => "No hay suficiente stock de '{$libro->nombre}' en el subinventario. Disponible: {$stockDisponible}"
                            ])->withInput();
                        }
                    } else {
                        $reservadoGeneral = (int) DB::table('apartado_detalles as ad')
                            ->join('apartados as a', 'a.id', '=', 'ad.apartado_id')
                            ->where('ad.libro_id', $libro->id)
                            ->where('a.tipo_inventario', 'general')
                            ->where('a.estado', 'activo')
                            ->sum('ad.cantidad');

                        $stockDisponible = $libro->stock - $reservadoGeneral;
                        if ($stockDisponible < $cantidad) {
                            DB::rollBack();
                            return back()->withErrors([
                                "items.{$index}.cantidad" => "No hay suficiente stock disponible de '{$libro->nombre}'. Disponible: {$stockDisponible}"
                            ])->withInput();
                        }
                    }
                }
            }

            $folio = 'AM-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(4));
            $ajusteMasivo = AjusteMasivo::create([
                'folio' => $folio,
                'origen_stock' => $validated['origen_stock'],
                'subinventario_id' => $esSubinventario ? $subinventario->id : null,
                'tipo_movimiento' => $validated['tipo_movimiento'],
                'tipo_entrada' => $tipoEntrada,
                'tipo_salida' => $tipoSalida,
                'fecha' => $validated['fecha'] ?? now()->toDateString(),
                'observaciones' => $validated['observaciones'] ?? null,
                'usuario' => session('username'),
                'total_lineas' => $items->count(),
                'total_unidades' => $items->sum(fn ($item) => (int) $item['cantidad']),
            ]);

            foreach ($items as $item) {
                $libro = $libros->get((int) $item['libro_id']);
                $observaciones = $this->buildMovimientoMasivoObservaciones(
                    $folio,
                    $validated['observaciones'] ?? null,
                    $item['observaciones'] ?? null
                );

                Movimiento::create([
                    'libro_id' => $libro->id,
                    'subinventario_id' => $esSubinventario ? $subinventario->id : null,
                    'ajuste_masivo_id' => $ajusteMasivo->id,
                    'tipo_movimiento' => $validated['tipo_movimiento'],
                    'tipo_entrada' => $tipoEntrada,
                    'tipo_salida' => $tipoSalida,
                    'cantidad' => (int) $item['cantidad'],
                    'precio_unitario' => $libro->precio,
                    'descuento' => 0,
                    'fecha' => $validated['fecha'] ?? now()->toDateString(),
                    'observaciones' => $observaciones,
                    'usuario' => session('username'),
                ]);
            }

            DB::commit();

            return redirect()->route('movimientos.index')
                ->with('success', "Ajuste masivo {$folio} registrado exitosamente: {$ajusteMasivo->total_lineas} movimientos, {$ajusteMasivo->total_unidades} unidades.");
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Error al registrar el ajuste masivo: ' . $e->getMessage()])
                ->withInput();
        }
    }

    public function downloadMasivoTemplate()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Ajuste Masivo');

        $headers = ['libro_id', 'codigo_barras', 'cantidad', 'observacion'];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray([
            [314, '', 2, 'Ejemplo: salida por corrección'],
            ['', '7500000000012', 1, 'Ejemplo usando código de barras'],
        ], null, 'A2');

        $sheet->getStyle('A1:D1')->getFont()->setBold(true);
        $sheet->getStyle('A1:D1')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE5E7EB');
        foreach (range('A', 'D') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        $sheet->getStyle('C:C')->getNumberFormat()->setFormatCode('0');

        $writer = new Xlsx($spreadsheet);
        $filename = 'plantilla_ajuste_masivo_' . date('Y-m-d') . '.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function previewMasivoImport(Request $request)
    {
        $validated = $request->validate([
            'tipo_movimiento' => 'required|in:entrada,salida',
            'tipo_entrada' => 'required_if:tipo_movimiento,entrada',
            'tipo_salida' => 'required_if:tipo_movimiento,salida',
            'origen_stock' => 'required|in:general,subinventario',
            'subinventario_id' => 'nullable|required_if:origen_stock,subinventario|exists:subinventarios,id',
            'fecha' => 'nullable|date',
            'observaciones' => 'nullable|string|max:500',
            'archivo' => 'required|file|mimes:xlsx,xls,csv|max:5120',
        ], [
            'archivo.required' => 'Debes seleccionar un archivo Excel',
            'archivo.mimes' => 'El archivo debe ser Excel o CSV',
            'subinventario_id.required_if' => 'Debes seleccionar el subinventario',
        ]);

        $tipoEntrada = $validated['tipo_movimiento'] === 'entrada' ? $validated['tipo_entrada'] : null;
        $tipoSalida = $validated['tipo_movimiento'] === 'salida' ? $validated['tipo_salida'] : null;

        if ($tipoEntrada === 'devolucion_subinventario' || $tipoSalida === 'transferencia_subinventario') {
            return back()->withErrors([
                'tipo_movimiento' => 'Las transferencias entre inventario general y subinventario se manejan desde Subinventarios.'
            ])->withInput();
        }

        [$libros, $subinventarios] = $this->movementFormData();
        $subinventario = null;
        if ($validated['origen_stock'] === 'subinventario') {
            $subinventario = SubInventario::whereKey($validated['subinventario_id'])
                ->where('estado', 'activo')
                ->with(['libros' => function ($query) {
                    $query->select('libros.id', 'libros.nombre', 'libros.codigo_barras', 'libros.precio');
                }])
                ->first();

            if (!$subinventario) {
                return back()->withErrors(['subinventario_id' => 'El subinventario seleccionado no está activo o no existe.'])
                    ->withInput();
            }
        }

        try {
            $spreadsheet = IOFactory::load($request->file('archivo')->getPathname());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, true);
        } catch (\Throwable $e) {
            return back()->withErrors(['archivo' => 'No se pudo leer el archivo: ' . $e->getMessage()])
                ->withInput();
        }

        $preview = $this->buildMasivoPreviewRows($rows, $validated, $subinventario);
        $hasErrors = collect($preview)->contains(fn ($row) => !empty($row['errores']));
        $validRows = collect($preview)->filter(fn ($row) => empty($row['errores']))->values();
        $context = [
            'tipo_movimiento' => $validated['tipo_movimiento'],
            'tipo_entrada' => $tipoEntrada,
            'tipo_salida' => $tipoSalida,
            'origen_stock' => $validated['origen_stock'],
            'subinventario_id' => $validated['origen_stock'] === 'subinventario' ? $validated['subinventario_id'] : null,
            'fecha' => $validated['fecha'] ?? now()->toDateString(),
            'observaciones' => $validated['observaciones'] ?? null,
        ];

        return view('movimientos.create-masivo', compact(
            'preview',
            'validRows',
            'hasErrors',
            'context',
            'libros',
            'subinventarios'
        ));
    }

    /**
     * Display the specified resource.
     */
    public function show(Movimiento $movimiento)
    {
        $movimiento->load(['libro', 'subinventario', 'ajusteMasivo']);
        
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
        $totalCount = (clone $query)->count();
        
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
        if ($totalCount > 0) {
            $totalEntradas = (clone $query)->where('tipo_movimiento', 'entrada')->sum('cantidad');
            $totalSalidas = (clone $query)->where('tipo_movimiento', 'salida')->sum('cantidad');
            
            $sheet->setCellValue('A' . $row, 'RESUMEN:');
            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
            $row++;
            
            $sheet->setCellValue('A' . $row, 'Total de movimientos: ' . $totalCount);
            $row++;
            $sheet->setCellValue('A' . $row, 'Total entradas: ' . $totalEntradas . ' unidades');
            $row++;
            $sheet->setCellValue('A' . $row, 'Total salidas: ' . $totalSalidas . ' unidades');
            $row += 2; // Espacio
        }
        
        // Encabezados de tabla
        $headers = ['ID', 'Fecha', 'Libro', 'Tipo', 'Cantidad', 'Precio Unit.', 'Descuento', 'Subtotal'];
        $row = $this->excelReportService->setTableHeaders($sheet, $headers, $row);
        
        // Eager load for lazy loading
        $lazyQuery = (clone $query)->with('libro');

        // Datos
        $data = [];
        foreach ($lazyQuery->lazy(250) as $movimiento) {
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
        
        // Preparar filtros
        $filtros = $this->buildFiltersList($request);
        
        // Calcular estadísticas
        $estadisticas = [
            'total' => (clone $query)->count(),
            'entradas' => (clone $query)->where('tipo_movimiento', 'entrada')->sum('cantidad'),
            'salidas' => (clone $query)->where('tipo_movimiento', 'salida')->sum('cantidad'),
        ];
        
        // Limitar los resultados del PDF para evitar OOM con DOMPDF
        $movimientos = $query->with('libro')->limit(200)->get();

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
    private function movementFormData(): array
    {
        $libros = Libro::orderBy('nombre')->get();
        $subinventarios = SubInventario::activos()
            ->with(['libros' => function ($query) {
                $query->select('libros.id', 'libros.nombre', 'libros.codigo_barras', 'libros.precio');
            }])
            ->orderBy('fecha_subinventario', 'desc')
            ->get();

        return [$libros, $subinventarios];
    }

    private function buildMovimientoMasivoObservaciones(string $folio, ?string $general, ?string $linea): string
    {
        $parts = ["Ajuste masivo {$folio}"];

        if ($general) {
            $parts[] = trim($general);
        }

        if ($linea) {
            $parts[] = 'Linea: ' . trim($linea);
        }

        return Str::limit(implode("\n", array_filter($parts)), 500, '');
    }

    private function buildMasivoPreviewRows(array $rows, array $context, ?SubInventario $subinventario): array
    {
        $preview = [];
        $headerMap = [];
        $seen = [];

        foreach ($rows as $rowNumber => $row) {
            $values = array_map(fn ($value) => is_string($value) ? trim($value) : $value, $row);

            if ((int) $rowNumber === 1) {
                $headerMap = $this->buildImportHeaderMap($values);
                continue;
            }

            $libroIdRaw = $this->rowValue($values, $headerMap, ['libro_id', 'id'], 'A');
            $codigoRaw = $this->rowValue($values, $headerMap, ['codigo_barras', 'codigo', 'código', 'barcode'], 'B');
            $cantidadRaw = $this->rowValue($values, $headerMap, ['cantidad', 'qty'], 'C');
            $observacionRaw = $this->rowValue($values, $headerMap, ['observacion', 'observación', 'nota', 'notas'], 'D');

            if ($libroIdRaw === null && $codigoRaw === null && $cantidadRaw === null && $observacionRaw === null) {
                continue;
            }

            $errores = [];
            $cantidad = is_numeric($cantidadRaw) ? (int) $cantidadRaw : 0;
            if ($cantidad < 1) {
                $errores[] = 'La cantidad debe ser al menos 1.';
            }

            $libro = $this->resolveImportLibro($libroIdRaw, $codigoRaw);
            if (!$libro) {
                $errores[] = 'No se encontró el libro por ID o código de barras.';
            }

            $stockActual = null;
            $stockResultante = null;
            if ($libro) {
                if (isset($seen[$libro->id])) {
                    $errores[] = 'Libro duplicado dentro del archivo.';
                }
                $seen[$libro->id] = true;

                if ($context['origen_stock'] === 'subinventario') {
                    $pivotLibro = $subinventario?->libros?->firstWhere('id', $libro->id);
                    $stockActual = $pivotLibro ? (int) $pivotLibro->pivot->cantidad : 0;
                } else {
                    $reservadoGeneral = (int) DB::table('apartado_detalles as ad')
                        ->join('apartados as a', 'a.id', '=', 'ad.apartado_id')
                        ->where('ad.libro_id', $libro->id)
                        ->where('a.tipo_inventario', 'general')
                        ->where('a.estado', 'activo')
                        ->sum('ad.cantidad');
                    $stockActual = (int) $libro->stock - $reservadoGeneral;
                }

                $stockResultante = $context['tipo_movimiento'] === 'entrada'
                    ? $stockActual + $cantidad
                    : $stockActual - $cantidad;

                if ($context['tipo_movimiento'] === 'salida' && $stockResultante < 0) {
                    $errores[] = "Stock insuficiente. Disponible: {$stockActual}.";
                }
            }

            $preview[] = [
                'fila' => (int) $rowNumber,
                'libro_id' => $libro?->id,
                'codigo_barras' => $libro?->codigo_barras ?: $codigoRaw,
                'nombre' => $libro?->nombre,
                'cantidad' => $cantidad,
                'observaciones' => $observacionRaw,
                'stock_actual' => $stockActual,
                'stock_resultante' => $stockResultante,
                'errores' => $errores,
            ];
        }

        if (empty($preview)) {
            $preview[] = [
                'fila' => 0,
                'libro_id' => null,
                'codigo_barras' => null,
                'nombre' => null,
                'cantidad' => 0,
                'observaciones' => null,
                'stock_actual' => null,
                'stock_resultante' => null,
                'errores' => ['El archivo no contiene filas para importar.'],
            ];
        }

        return $preview;
    }

    private function buildImportHeaderMap(array $headerRow): array
    {
        $map = [];
        foreach ($headerRow as $column => $value) {
            $key = $this->normalizeImportHeader((string) $value);
            if ($key !== '') {
                $map[$key] = $column;
            }
        }

        return $map;
    }

    private function normalizeImportHeader(string $value): string
    {
        $value = Str::ascii(Str::lower(trim($value)));
        return preg_replace('/[^a-z0-9]+/', '_', $value) ?: '';
    }

    private function rowValue(array $row, array $headerMap, array $keys, string $fallbackColumn)
    {
        foreach ($keys as $key) {
            $normalized = $this->normalizeImportHeader($key);
            if (isset($headerMap[$normalized])) {
                $value = $row[$headerMap[$normalized]] ?? null;
                return $value === '' ? null : $value;
            }
        }

        $value = $row[$fallbackColumn] ?? null;
        return $value === '' ? null : $value;
    }

    private function resolveImportLibro($libroIdRaw, $codigoRaw): ?Libro
    {
        if ($libroIdRaw !== null && $libroIdRaw !== '' && is_numeric($libroIdRaw)) {
            $libro = Libro::find((int) $libroIdRaw);
            if ($libro) {
                return $libro;
            }
        }

        if ($codigoRaw !== null && $codigoRaw !== '') {
            return Libro::where('codigo_barras', trim((string) $codigoRaw))->first();
        }

        return null;
    }

    private function buildFilteredQuery(Request $request)
    {
        $query = Movimiento::with(['libro', 'subinventario', 'ajusteMasivo'])->orderBy('created_at', 'desc');

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
