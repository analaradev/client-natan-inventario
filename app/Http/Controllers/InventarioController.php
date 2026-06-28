<?php

namespace App\Http\Controllers;

use App\Models\Libro;
use App\Services\CodeGeneratorService;
use App\Services\ExcelService;
use App\Services\ExcelReportService;
use App\Services\PdfReportService;
use App\Services\LibroService;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Barryvdh\DomPDF\Facade\Pdf;

use App\Traits\HasRoleChecks;

class InventarioController extends Controller
{
    use HasRoleChecks;

    protected $libroService;
    protected $excelService;
    protected $excelReportService;
    protected $pdfReportService;
    protected $codeGenerator;

    public function __construct(
        LibroService $libroService,
        ExcelService $excelService,
        ExcelReportService $excelReportService,
        PdfReportService $pdfReportService,
        CodeGeneratorService $codeGenerator
    ) {
        $this->libroService = $libroService;
        $this->excelService = $excelService;
        $this->excelReportService = $excelReportService;
        $this->pdfReportService = $pdfReportService;
        $this->codeGenerator = $codeGenerator;
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = $this->libroService->buildFilteredQuery($request);
        $statistics = $this->libroService->calculateStatistics($query);
        $libros = $query->paginate(10);

        return view('inventario.index', array_merge(
            compact('libros'),
            $statistics
        ));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('inventario.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate(
            $this->libroService->getCreateRules(),
            $this->libroService->getValidationMessages()
        );

        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            $stockInicial = isset($validated['stock']) ? (int)$validated['stock'] : 0;

            // Excluir stock de la creación directa para delegar al MovimientoObserver
            $data = $validated;
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
                    'observaciones' => 'Inventario inicial (Creación del libro)',
                    'usuario' => auth()->user()->name ?? 'Admin',
                ]);
            }

            \Illuminate\Support\Facades\DB::commit();

            return redirect()->route('inventario.index')
                ->with('success', 'Libro agregado exitosamente' . ($stockInicial > 0 ? " con stock inicial de {$stockInicial}" : ''));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            return back()->withErrors(['error' => 'Error al registrar el libro: ' . $e->getMessage()])->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $libro = Libro::findOrFail($id);
        return view('inventario.show', compact('libro'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $libro = Libro::findOrFail($id);
        return view('inventario.edit', compact('libro'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate(
            $this->libroService->getUpdateRules($id),
            $this->libroService->getValidationMessages()
        );

        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            $libro = Libro::whereKey($id)->lockForUpdate()->firstOrFail();
            $oldStock = (int)$libro->stock;
            $newStockVal = $oldStock;
            // Validar que el stock no sea menor que la cantidad reservada en apartados activos
            if (isset($validated['stock'])) {
                $newStockVal = (int)$validated['stock'];
                
                if ($newStockVal < $oldStock) {
                    $reservadoGeneral = (int) \Illuminate\Support\Facades\DB::table('apartado_detalles as ad')
                        ->join('apartados as a', 'a.id', '=', 'ad.apartado_id')
                        ->where('ad.libro_id', $libro->id)
                        ->where('a.tipo_inventario', 'general')
                        ->where('a.estado', 'activo')
                        ->sum('ad.cantidad');
                    
                    if ($newStockVal < $reservadoGeneral) {
                        throw new \DomainException("El stock no puede ser menor que la cantidad en apartados activos ({$reservadoGeneral} unidades)");
                    }
                }
                unset($validated['stock']); // Excluir stock de la actualización directa
            }

            $libro->update($validated);

            if ($oldStock !== $newStockVal) {
                $diferencia = $newStockVal - $oldStock;
                $tipoMovimiento = $diferencia > 0 ? 'entrada' : 'salida';
                $tipoAjuste = $diferencia > 0 ? 'ajuste_positivo' : 'ajuste_negativo';

                \App\Models\Movimiento::create([
                    'libro_id' => $libro->id,
                    'tipo_movimiento' => $tipoMovimiento,
                    ($tipoMovimiento === 'entrada' ? 'tipo_entrada' : 'tipo_salida') => $tipoAjuste,
                    'cantidad' => abs($diferencia),
                    'precio_unitario' => $libro->precio,
                    'fecha' => now()->toDateString(),
                    'observaciones' => "Ajuste manual de stock por actualización del libro (Stock anterior: {$oldStock}, Nuevo stock: {$newStockVal})",
                    'usuario' => auth()->user()->name ?? 'Admin',
                ]);
            }

            \Illuminate\Support\Facades\DB::commit();

            return redirect()->route('inventario.show', $libro->id)
                ->with('success', 'Libro actualizado exitosamente');

        } catch (\DomainException $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            return back()->withErrors(['stock' => $e->getMessage()])->withInput();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            return back()->withErrors(['error' => 'Error al actualizar el libro: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $libro = Libro::findOrFail($id);

        // Validar si tiene relaciones
        $hasMovements = $libro->movimientos()->exists();
        $hasApartados = \App\Models\ApartadoDetalle::where('libro_id', $libro->id)->exists();
        $hasSubinventarios = $libro->subinventarios()->wherePivot('cantidad', '>', 0)->exists();

        if ($hasMovements || $hasApartados || $hasSubinventarios) {
            return back()->with('error', "No se puede eliminar el libro '{$libro->nombre}' porque cuenta con historial de movimientos, ventas, apartados o existencias en subinventarios. Si ya no deseas usar este libro, te sugerimos editar su stock a 0.");
        }

        $libro->delete();

        return redirect()->route('inventario.index')
            ->with('success', 'Libro eliminado exitosamente');
    }

    /**
     * Generar código de barras aleatorio
     */
    public function generateBarcode()
    {
        $codigo = $this->libroService->generateBarcode();
        return response()->json(['codigo' => $codigo]);
    }

    /**
     * Mostrar vista de importación
     */
    public function importView()
    {
        return view('inventario.import');
    }

    /**
     * Descargar plantilla de Excel
     */
    public function downloadTemplate()
    {
        $this->excelService->generateTemplate();
    }

    /**
     * Procesar importación de Excel
     */
    public function importProcess(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:5120',
        ]);

        try {
            $file = $request->file('file');
            
            $result = $this->excelService->import($file->getPathname(), [
                'skip_errors' => $request->boolean('skip_errors'),
            ]);

            $message = $this->excelService->buildResultMessage($result);

            if (empty($result['errors'])) {
                return redirect()->route('inventario.index')
                    ->with('success', $message ?: 'Importación completada exitosamente');
            } else {
                return redirect()->route('inventario.import')
                    ->with('warning', $message)
                    ->with('errors_list', $result['errors']);
            }

        } catch (\Exception $e) {
            return redirect()->route('inventario.import')
                ->with('error', 'Error al procesar el archivo: ' . $e->getMessage());
        }
    }

    /**
     * Descargar código QR como imagen SVG con información del libro
     */
    public function downloadQR($id)
    {
        $libro = Libro::findOrFail($id);
        
        $svgContent = $this->codeGenerator->generateQrSvg(
            $libro->codigo_barras,
            $libro->nombre
        );
        
        $fileName = 'qr-' . $libro->codigo_barras . '.svg';
        
        return response($svgContent)
            ->header('Content-Type', 'image/svg+xml')
            ->header('Content-Disposition', 'attachment; filename="' . $fileName . '"');
    }

    /**
     * Exportar libros filtrados a Excel
     */
    public function exportExcel(Request $request)
    {
        $query = $this->libroService->buildFilteredQuery($request);
        $libros = $query->get();
        
        // Crear spreadsheet usando el servicio
        $spreadsheet = $this->excelReportService->createSpreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Título
        $row = $this->excelReportService->setTitle($sheet, 'REPORTE DE INVENTARIO DE LIBROS', 'E', 1);
        $row++; // Espacio
        
        // Filtros aplicados
        $filtros = $this->buildFiltersList($request);
        $row = $this->excelReportService->setFilters($sheet, $filtros, $row);
        
        // Encabezados de tabla
        $headers = ['ID', 'Nombre', 'Código de Barras', 'Precio', 'Stock'];
        $row = $this->excelReportService->setTableHeaders($sheet, $headers, $row);
        
        // Datos
        $data = [];
        foreach ($libros as $libro) {
            $data[] = [
                $libro->id,
                $libro->nombre,
                $libro->codigo_barras ?? 'Sin código',
                '$' . number_format($libro->precio, 2),
                $libro->stock,
            ];
        }
        
        $lastRow = $this->excelReportService->fillData($sheet, $data, $row);
        
        // Auto ajustar columnas
        $this->excelReportService->autoSizeColumns($sheet, ['A', 'B', 'C', 'D', 'E']);
        
        // Descargar
        $filename = $this->excelReportService->generateFilename('reporte_inventario');
        $this->excelReportService->download($spreadsheet, $filename);
    }

    /**
     * Exportar libros filtrados a PDF
     */
    public function exportPdf(Request $request)
    {
        $query = $this->libroService->buildFilteredQuery($request);
        $libros = $query->get();
        
        // Preparar filtros usando el helper
        $filtros = $this->buildFiltersList($request);
        
        // Obtener estilos base del servicio
        $styles = $this->pdfReportService->getBaseStyles();
        
        // Generar PDF usando el servicio
        $filename = $this->pdfReportService->generateFilename('reporte_inventario');
        
        return $this->pdfReportService->generate(
            'inventario.pdf-report-new',
            compact('libros', 'filtros', 'styles'),
            $filename
        );
    }

    /**
     * Construir lista de filtros aplicados (helper privado)
     */
    private function buildFiltersList(Request $request): array
    {
        $filtros = [];
        
        if ($request->filled('search')) {
            $filtros[] = 'Búsqueda: ' . $request->search;
        }
        
        if ($request->filled('stock_filter')) {
            $stockLabels = [
                '0-100' => 'Stock: Menos de 100',
                '100-200' => 'Stock: 100 a 200',
                '200-300' => 'Stock: 200 a 300',
                '300-400' => 'Stock: 300 a 400',
                '400-up' => 'Stock: 400 o más'
            ];
            $filtros[] = $stockLabels[$request->stock_filter] ?? 'Stock: ' . $request->stock_filter;
        }
        
        if ($request->filled('precio_filter')) {
            $precioLabels = [
                '0-100' => 'Precio: Menos de $100',
                '100-200' => 'Precio: $100 a $200',
                '200-300' => 'Precio: $200 a $300',
                '300-400' => 'Precio: $300 a $400',
                '400-up' => 'Precio: $400 o más'
            ];
            $filtros[] = $precioLabels[$request->precio_filter] ?? 'Precio: ' . $request->precio_filter;
        }
        
        return $filtros;
    }

    /**
     * API - Buscar libro por código de barras o QR
     */
    public function apiBuscarPorCodigo(Request $request, $codigo)
    {
        if (!$this->hasValidMobileRole($request)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes autorización para acceder a este recurso.'
            ], 403);
        }

        $libro = Libro::where('codigo_barras', $codigo)->first();

        if (!$libro) {
            return response()->json([
                'success' => false,
                'message' => 'Libro no encontrado'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $libro->id,
                'nombre' => $libro->nombre,
                'codigo_barras' => $libro->codigo_barras,
                'precio' => $libro->precio,
                'stock' => $libro->stock,  // Stock en inventario general
                'stock_subinventario' => $libro->stock_subinventario,
                'stock_total' => $libro->stock_total,  // Stock total (general + subinventarios)
            ]
        ]);
    }

    /**
     * API - Listar todos los libros
     * Incluye filtros opcionales y paginación
     * Si se proporciona cod_congregante, marca qué libros puede vender
     */
    public function apiListarLibros(Request $request)
    {
        if (!$this->hasValidMobileRole($request)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes autorización para acceder a este recurso.'
            ], 403);
        }

        $query = Libro::query();

        // Filtro: Solo libros con stock
        if ($request->has('con_stock') && $request->con_stock == 'true') {
            $query->where('stock', '>', 0);
        }

        // Filtro: Buscar por nombre
        if ($request->filled('buscar')) {
            $query->where('nombre', 'like', '%' . $request->buscar . '%');
        }

        // Filtro: Precio mínimo
        if ($request->filled('precio_min')) {
            $query->where('precio', '>=', $request->precio_min);
        }

        // Filtro: Precio máximo
        if ($request->filled('precio_max')) {
            $query->where('precio', '<=', $request->precio_max);
        }

        // Ordenamiento
        $orderBy = $request->get('ordenar', 'nombre');
        $orderDirection = strtolower($request->get('direccion', 'asc')) === 'desc' ? 'desc' : 'asc';
        
        $allowedOrderBy = ['nombre', 'precio', 'stock', 'created_at'];
        if (in_array($orderBy, $allowedOrderBy)) {
            $query->orderBy($orderBy, $orderDirection);
        }

        // Paginación
        $perPage = $request->get('per_page', 50);
        $perPage = min($perPage, 100); // Máximo 100 por página

        $libros = $query->paginate($perPage);

        // Si se proporciona cod_congregante, obtener los subinventarios activos asignados a este congregante
        $misSubinventariosIds = [];
        $codCongregante = $request->attributes->get('validated_cod_congregante', $request->get('cod_congregante'));
        
        if ($codCongregante) {
            $misSubinventariosIds = \DB::table('subinventarios')
                ->where('estado', 'activo')
                ->pluck('id')
                ->toArray();
        }

        // Formatear libros con información de disponibilidad para el vendedor
        $librosFormateados = $libros->map(function($libro) use ($misSubinventariosIds, $codCongregante) {
            $resultado = [
                'id' => $libro->id,
                'nombre' => $libro->nombre,
                'codigo_barras' => $libro->codigo_barras,
                'precio' => $libro->precio,
                'stock' => $libro->stock,
                'stock_subinventario' => $libro->stock_subinventario,
                'stock_apartado' => $libro->stock_apartado,
            ];

            // Si hay cod_congregante, agregar info de vendibilidad
            if ($codCongregante) {
                // Verificar si el libro está en algún subinventario del vendedor
                if (!empty($misSubinventariosIds)) {
                    $cantidadDisponible = \DB::table('subinventario_libro')
                        ->join('subinventarios', 'subinventario_libro.subinventario_id', '=', 'subinventarios.id')
                        ->where('subinventario_libro.libro_id', $libro->id)
                        ->whereIn('subinventario_libro.subinventario_id', $misSubinventariosIds)
                        ->where('subinventarios.estado', 'activo')
                        ->where('subinventario_libro.cantidad', '>', 0)
                        ->sum('subinventario_libro.cantidad');

                    $resultado['puede_vender'] = $cantidadDisponible > 0;
                    $resultado['cantidad_disponible_para_mi'] = (int)$cantidadDisponible;
                } else {
                    // No tiene subinventarios asignados
                    $resultado['puede_vender'] = false;
                    $resultado['cantidad_disponible_para_mi'] = 0;
                }
            }

            return $resultado;
        });

        // Calcular resumen de vendibilidad si se proporciona cod_congregante
        $resumen = null;
        if ($codCongregante) {
            $totalPuedeVender = $librosFormateados->filter(function($libro) {
                return $libro['puede_vender'] === true;
            })->count();

            $totalNoPuedeVender = $librosFormateados->filter(function($libro) {
                return $libro['puede_vender'] === false;
            })->count();

            $resumen = [
                'total_puede_vender' => $totalPuedeVender,
                'total_no_puede_vender' => $totalNoPuedeVender,
                'total_libros_pagina' => $librosFormateados->count(),
            ];
        }

        $response = [
            'success' => true,
            'data' => $librosFormateados,
            'pagination' => [
                'total' => $libros->total(),
                'per_page' => $libros->perPage(),
                'current_page' => $libros->currentPage(),
                'last_page' => $libros->lastPage(),
                'from' => $libros->firstItem(),
                'to' => $libros->lastItem(),
            ]
        ];

        if ($resumen) {
            $response['resumen'] = $resumen;
        }

        return response()->json($response);
    }

    /**
     * API - Verificar disponibilidad de un libro en inventarios
     * Retorna si el libro está en inventario general y/o en qué subinventarios
     */
    public function apiDisponibilidadLibro(Request $request, $id)
    {
        if (!$this->hasValidMobileRole($request)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes autorización para acceder a este recurso.'
            ], 403);
        }

        $libro = Libro::with(['subinventarios' => function($query) {
            $query->where('estado', 'activo')
                  ->wherePivot('cantidad', '>', 0);
        }])->find($id);

        if (!$libro) {
            return response()->json([
                'success' => false,
                'message' => 'Libro no encontrado'
            ], 404);
        }

        // Información del inventario general
        $inventarioGeneral = [
            'disponible' => $libro->stock > 0,
            'cantidad' => $libro->stock
        ];

        // Información de subinventarios
        $subinventarios = $libro->subinventarios->map(function($sub) {
            return [
                'subinventario_id' => $sub->id,
                'descripcion' => $sub->descripcion ?? 'Sin descripción',
                'fecha_subinventario' => $sub->fecha_subinventario->format('Y-m-d'),
                'cantidad_disponible' => $sub->pivot->cantidad
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'libro' => [
                    'id' => $libro->id,
                    'nombre' => $libro->nombre,
                    'codigo_barras' => $libro->codigo_barras,
                    'precio' => $libro->precio
                ],
                'inventario_general' => $inventarioGeneral,
                'subinventarios' => $subinventarios,
                'total_disponible' => $libro->stock + $subinventarios->sum('cantidad_disponible'),
                'tiene_stock' => ($libro->stock > 0 || $subinventarios->count() > 0)
            ]
        ]);
    }
}
