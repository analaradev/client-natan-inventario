<?php

namespace App\Http\Controllers;

use App\Models\Apartado;
use App\Models\ApartadoDetalle;
use App\Models\Cliente;
use App\Models\Libro;
use App\Models\Venta;
use App\Models\Movimiento;
use App\Services\CodeGeneratorService;
use App\Services\ExcelReportService;
use App\Services\PdfReportService;
use App\Services\InventoryStockService;
use App\Services\IngresoCajaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

use App\Traits\HasRoleChecks;

class ApartadoController extends Controller
{
    use HasRoleChecks;

    protected $codeGenerator;
    protected $excelReportService;
    protected $pdfReportService;
    protected $stockService;
    protected $ingresoCajaService;

    public function __construct(
        CodeGeneratorService $codeGenerator,
        ExcelReportService $excelReportService,
        PdfReportService $pdfReportService,
        InventoryStockService $stockService,
        IngresoCajaService $ingresoCajaService
    ) {
        $this->codeGenerator = $codeGenerator;
        $this->excelReportService = $excelReportService;
        $this->pdfReportService = $pdfReportService;
        $this->stockService = $stockService;
        $this->ingresoCajaService = $ingresoCajaService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Apartado::with(['cliente', 'detalles.libro', 'abonos']);

        // Filtro por estado
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        // Filtro por cliente
        if ($request->filled('cliente_id')) {
            $query->where('cliente_id', $request->cliente_id);
        }

        // Filtro por fechas
        if ($request->filled('fecha_desde')) {
            $query->whereDate('fecha_apartado', '>=', $request->fecha_desde);
        }
        if ($request->filled('fecha_hasta')) {
            $query->whereDate('fecha_apartado', '<=', $request->fecha_hasta);
        }

        // Filtro por vencidos
        if ($request->filled('vencidos') && $request->vencidos == '1') {
            $query->vencidos();
        }

        // Búsqueda general
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('folio', 'like', "%{$search}%")
                  ->orWhere('id', 'like', "%{$search}%")
                  ->orWhereHas('cliente', function($q) use ($search) {
                      $q->where('nombre', 'like', "%{$search}%");
                  });
            });
        }

        // Ordenamiento
        $ordenar = $request->get('ordenar', 'reciente');
        switch ($ordenar) {
            case 'reciente':
                $query->latest();
                break;
            case 'antiguo':
                $query->oldest();
                break;
            case 'mayor_saldo':
                $query->orderBy('saldo_pendiente', 'desc');
                break;
            case 'menor_saldo':
                $query->orderBy('saldo_pendiente', 'asc');
                break;
        }

        $apartados = $query->paginate(10)->withQueryString();

        // Estadísticas
        $estadisticas = [
            'total_apartados' => Apartado::count(),
            'activos' => Apartado::activos()->count(),
            'liquidados' => Apartado::liquidados()->count(),
            'cancelados' => Apartado::cancelados()->count(),
            'vencidos' => Apartado::vencidos()->count(),
            'total_apartado' => Apartado::activos()->sum('monto_total'),
            'saldo_pendiente_total' => Apartado::activos()->sum('saldo_pendiente'),
        ];

        $clientes = Cliente::orderBy('nombre')->get();

        return view('apartados.index', compact('apartados', 'estadisticas', 'clientes'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $clientes = Cliente::orderBy('nombre')->get();
        $libros = Libro::where('stock', '>', 0)->orderBy('nombre')->get();
        $subinventarios = \App\Models\SubInventario::where('estado', 'activo')
            ->orderBy('descripcion')
            ->get();

        return view('apartados.create', compact('clientes', 'libros', 'subinventarios'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'tipo_inventario' => 'nullable|in:general,subinventario',
            'subinventario_id' => 'nullable|exists:subinventarios,id',
            'cliente_id' => 'required|exists:clientes,id',
            'fecha_apartado' => 'required|date',
            'enganche' => 'required|numeric|min:0',
            'fecha_limite' => 'nullable|date|after:today',
            'observaciones' => 'nullable|string',
            'libros' => 'required|array|min:1',
            'libros.*.libro_id' => 'required|exists:libros,id',
            'libros.*.cantidad' => 'required|integer|min:1',
            'libros.*.precio_unitario' => 'required|numeric|min:0',
            'libros.*.descuento' => 'nullable|numeric|min:0|max:100',
        ], [
            'tipo_inventario.in' => 'El tipo de inventario debe ser "general" o "subinventario"',
            'cliente_id.required' => 'Debe seleccionar un cliente',
            'fecha_apartado.required' => 'La fecha de apartado es obligatoria',
            'enganche.required' => 'El enganche es obligatorio',
            'enganche.min' => 'El enganche debe ser mayor o igual a 0',
            'libros.required' => 'Debe agregar al menos un libro',
            'libros.min' => 'Debe agregar al menos un libro',
            'fecha_limite.after' => 'La fecha límite debe ser posterior a hoy',
        ]);

        // Determinar tipo de inventario (por defecto general)
        $tipoInventario = $validated['tipo_inventario'] ?? 'general';
        
        // Si es subinventario, validar que se proporcione subinventario_id
        if ($tipoInventario === 'subinventario' && empty($validated['subinventario_id'])) {
            return back()->withErrors(['error' => 'Debe seleccionar un subinventario'])
                ->withInput();
        }

        DB::beginTransaction();
        try {
            // Generar folio único
            $folio = $this->codeGenerator->generateCode('Apartado', 'AP');

            // Calcular monto total y validar stock según tipo de inventario
            $montoTotal = 0;
            $esAdmin = $this->isAdmin();
            foreach ($validated['libros'] as &$libroData) {
                $libro = Libro::find($libroData['libro_id']);
                
                if (!$esAdmin) {
                    $libroData['precio_unitario'] = $libro->precio;
                }
                
                if ($tipoInventario === 'subinventario') {
                    // Verificar que el libro esté en el subinventario
                    $libroSubinventario = DB::table('subinventario_libro')
                        ->where('subinventario_id', $validated['subinventario_id'])
                        ->where('libro_id', $libroData['libro_id'])
                        ->first();

                    if (!$libroSubinventario) {
                        throw new \Exception("El libro '{$libro->nombre}' no está en este subinventario");
                    }

                    // Verificar stock disponible en subinventario
                    if ($libroSubinventario->cantidad < $libroData['cantidad']) {
                        throw new \Exception("Cantidad insuficiente para '{$libro->nombre}'. Disponible: {$libroSubinventario->cantidad}");
                    }
                } else {
                    // Verificar stock disponible en inventario general
                    $reservadoGeneral = DB::table('apartado_detalles as ad')
                        ->join('apartados as a', 'a.id', '=', 'ad.apartado_id')
                        ->where('ad.libro_id', $libro->id)
                        ->where('a.tipo_inventario', 'general')
                        ->where('a.estado', 'activo')
                        ->sum('ad.cantidad');
                    $stockDisponible = $libro->stock - $reservadoGeneral;
                    if ($stockDisponible < $libroData['cantidad']) {
                        throw new \Exception("Stock insuficiente en inventario general para '{$libro->nombre}'. Disponible: {$stockDisponible}");
                    }
                }

                $precio = $libroData['precio_unitario'];
                if (!empty($libroData['descuento'])) {
                    $precio -= ($precio * $libroData['descuento'] / 100);
                }
                $subtotal = $precio * $libroData['cantidad'];
                $montoTotal += $subtotal;
            }
            unset($libroData);

            // Validar que el enganche no sea mayor al total
            if ($validated['enganche'] > $montoTotal) {
                throw new \Exception('El enganche no puede ser mayor al monto total del apartado');
            }

            // Preparar observaciones con información del origen
            $observacionesBase = $validated['observaciones'] ?? '';
            if ($tipoInventario === 'subinventario') {
                $subinventario = \App\Models\SubInventario::find($validated['subinventario_id']);
                $observacionesBase .= "\n--- DATOS DE SUBINVENTARIO ---\nSubinventario: {$subinventario->nombre_display}";
            } else {
                $observacionesBase .= "\n--- INVENTARIO GENERAL ---\nApartado desde inventario general";
            }

            // Crear apartado
            $apartado = Apartado::create([
                'folio' => $folio,
                'cliente_id' => $validated['cliente_id'],
                'fecha_apartado' => $validated['fecha_apartado'],
                'monto_total' => $montoTotal,
                'enganche' => $validated['enganche'],
                'saldo_pendiente' => $montoTotal - $validated['enganche'],
                'fecha_limite' => $validated['fecha_limite'] ?? null,
                'estado' => 'activo',
                'observaciones' => $observacionesBase,
                'usuario' => Auth::user()->name ?? 'Sistema',
                'tipo_inventario' => $tipoInventario,
                'subinventario_id' => $tipoInventario === 'subinventario' ? $validated['subinventario_id'] : null,
            ]);

            // Crear detalles y actualizar stock
            foreach ($validated['libros'] as $libroData) {
                $libro = Libro::find($libroData['libro_id']);
                
                $precio = $libroData['precio_unitario'];
                $descuento = $libroData['descuento'] ?? 0;
                if ($descuento > 0) {
                    $precio -= ($precio * $descuento / 100);
                }
                $subtotal = $precio * $libroData['cantidad'];

                ApartadoDetalle::create([
                    'apartado_id' => $apartado->id,
                    'libro_id' => $libroData['libro_id'],
                    'cantidad' => $libroData['cantidad'],
                    'precio_unitario' => $libroData['precio_unitario'],
                    'descuento' => $descuento,
                    'subtotal' => $subtotal,
                ]);

            }

            $this->stockService->reserveApartado($apartado);

            // Si hubo enganche, crear el primer abono
            if ($validated['enganche'] > 0) {
                $enganche = $apartado->abonos()->create([
                    'fecha_abono' => $validated['fecha_apartado'],
                    'monto' => $validated['enganche'],
                    'saldo_anterior' => $montoTotal,
                    'saldo_nuevo' => $montoTotal - $validated['enganche'],
                    'metodo_pago' => 'efectivo',
                    'observaciones' => 'Enganche inicial',
                    'usuario' => Auth::user()->name ?? 'Sistema',
                ]);
                $this->ingresoCajaService->registrarAbono($enganche, 'automatico', 'enganche_apartado');
            }

            DB::commit();

            return redirect()->route('apartados.show', $apartado)
                ->with('success', 'Apartado creado exitosamente. Folio: ' . $folio);

        } catch (\Exception $e) {
            DB::rollBack();
            
            // Log detallado del error
            Log::error('Error al crear apartado', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'data' => $request->except(['_token']),
                'user' => Auth::user()->name ?? 'Sistema',
            ]);
            
            return back()->withErrors(['error' => 'Error al crear el apartado: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Apartado $apartado)
    {
        $apartado->load(['cliente', 'detalles.libro', 'abonos', 'venta']);
        
        return view('apartados.show', compact('apartado'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Apartado $apartado)
    {
        // Solo permitir editar apartados activos
        if ($apartado->estado !== 'activo') {
            return redirect()->route('apartados.show', $apartado)
                ->with('warning', 'Solo se pueden editar apartados activos');
        }

        // Si ya tiene abonos, no permitir editar los libros
        $tieneAbonos = $apartado->abonos()->count() > 0;

        $apartado->load(['cliente', 'detalles.libro']);
        $clientes = Cliente::orderBy('nombre')->get();
        $libros = Libro::where('stock', '>', 0)->orderBy('nombre')->get();

        return view('apartados.edit', compact('apartado', 'clientes', 'libros', 'tieneAbonos'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Apartado $apartado)
    {
        // Solo permitir actualizar apartados activos
        if ($apartado->estado !== 'activo') {
            return redirect()->route('apartados.show', $apartado)
                ->with('error', 'Solo se pueden editar apartados activos');
        }

        $validated = $request->validate([
            'fecha_limite' => 'nullable|date|after:today',
            'observaciones' => 'nullable|string',
        ], [
            'fecha_limite.after' => 'La fecha límite debe ser posterior a hoy',
        ]);

        try {
            $apartado->update([
                'fecha_limite' => $validated['fecha_limite'] ?? null,
                'observaciones' => $validated['observaciones'] ?? null,
            ]);

            return redirect()->route('apartados.show', $apartado)
                ->with('success', 'Apartado actualizado exitosamente');

        } catch (\Exception $e) {
            Log::error('Error al actualizar apartado', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'apartado_id' => $apartado->id,
                'data' => $request->except(['_token']),
                'user' => Auth::user()->name ?? 'Sistema',
            ]);
            
            return back()->withErrors(['error' => 'Error al actualizar el apartado: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Liquidar un apartado y crear la venta
     */
    public function liquidar(Apartado $apartado)
    {
        DB::beginTransaction();
        try {
            $apartado = Apartado::whereKey($apartado->id)->lockForUpdate()->firstOrFail();
            if ($apartado->estado !== 'activo') {
                throw new \DomainException('Solo se pueden liquidar apartados activos');
            }
            if ($apartado->saldo_pendiente > 0) {
                throw new \DomainException('El apartado aún tiene saldo pendiente. Debe estar completamente pagado para liquidar.');
            }
            Log::info('Iniciando liquidación de apartado', [
                'apartado_id' => $apartado->id,
                'apartado_folio' => $apartado->folio,
                'cliente_id' => $apartado->cliente_id,
                'monto_total' => $apartado->monto_total,
            ]);

            // Crear la venta
            $venta = Venta::create([
                'cliente_id' => $apartado->cliente_id,
                'apartado_id' => $apartado->id,
                'fecha_venta' => now(),
                'tipo_pago' => 'contado', // Ya está pagado por los abonos
                'subtotal' => $apartado->monto_total,
                'descuento_global' => 0,
                'total' => $apartado->monto_total,
                'estado' => 'completada',
                'tiene_envio' => false,
                'costo_envio' => 0,
                'observaciones' => "Venta generada del apartado {$apartado->folio}",
                'usuario' => Auth::user()->name ?? 'Sistema',
                'tipo_inventario' => $apartado->tipo_inventario,
                'subinventario_id' => $apartado->subinventario_id,
                'es_a_plazos' => false,
                'total_pagado' => $apartado->monto_total,
                'estado_pago' => 'completado',
            ]);

            Log::info('Venta creada exitosamente', [
                'venta_id' => $venta->id,
                'apartado_id' => $apartado->id,
            ]);

            // Crear movimientos de salida y descontar inventario
            foreach ($apartado->detalles as $detalle) {
                Log::info('Procesando detalle de apartado', [
                    'libro_id' => $detalle->libro_id,
                    'cantidad' => $detalle->cantidad,
                    'stock_actual' => $detalle->libro->stock,
                    'stock_apartado_actual' => $detalle->libro->stock_apartado,
                ]);

                Movimiento::create([
                    'libro_id' => $detalle->libro_id,
                    'venta_id' => $venta->id,
                    'tipo_movimiento' => 'salida',
                    'tipo_salida' => 'venta',
                    'cantidad' => $detalle->cantidad,
                    'precio_unitario' => $detalle->precio_unitario,
                    'descuento' => $detalle->descuento,
                    'fecha' => now(),
                    'observaciones' => "Venta del apartado {$apartado->folio}",
                    'usuario' => Auth::user()->name ?? 'Sistema',
                ]);

            }

            $this->stockService->consumeApartado($apartado);

            // Actualizar apartado
            $apartado->update([
                'estado' => 'liquidado',
                'venta_id' => $venta->id,
            ]);

            Log::info('Apartado liquidado exitosamente', [
                'apartado_id' => $apartado->id,
                'venta_id' => $venta->id,
            ]);

            DB::commit();

            return redirect()->route('ventas.show', $venta)
                ->with('success', '¡Apartado liquidado exitosamente! Se ha creado la venta #' . $venta->id);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error al liquidar apartado', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'apartado_id' => $apartado->id,
                'apartado_folio' => $apartado->folio,
                'apartado_monto_total' => $apartado->monto_total,
                'apartado_saldo_pendiente' => $apartado->saldo_pendiente,
                'user' => Auth::user()->name ?? 'Sistema',
            ]);
            
            return back()->withErrors(['error' => 'Error al liquidar el apartado: ' . $e->getMessage()]);
        }
    }

    /**
     * Cancelar un apartado
     */
    public function cancelar(Apartado $apartado)
    {
        DB::beginTransaction();
        try {
            $apartado = Apartado::whereKey($apartado->id)->lockForUpdate()->firstOrFail();
            if ($apartado->estado !== 'activo') {
                throw new \DomainException('Solo se pueden cancelar apartados activos');
            }

            $this->stockService->releaseApartado($apartado);

            // Cambiar estado
            $apartado->update([
                'estado' => 'cancelado',
            ]);

            DB::commit();

            return redirect()->route('apartados.show', $apartado)
                ->with('success', 'Apartado cancelado exitosamente. El stock ha sido liberado.');

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error al cancelar apartado', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'apartado_id' => $apartado->id,
                'apartado_folio' => $apartado->folio,
                'user' => Auth::user()->name ?? 'Sistema',
            ]);
            
            return back()->withErrors(['error' => 'Error al cancelar el apartado: ' . $e->getMessage()]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Apartado $apartado)
    {
        // Solo permitir eliminar apartados cancelados
        if ($apartado->estado !== 'cancelado') {
            return back()->with('error', 'Solo se pueden eliminar apartados cancelados');
        }

        DB::beginTransaction();
        try {
            $apartado->delete();

            DB::commit();

            return redirect()->route('apartados.index')
                ->with('success', 'Apartado eliminado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error al eliminar apartado', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'apartado_id' => $apartado->id,
                'apartado_folio' => $apartado->folio,
                'user' => Auth::user()->name ?? 'Sistema',
            ]);
            
            return back()->withErrors(['error' => 'Error al eliminar el apartado: ' . $e->getMessage()]);
        }
    }

    /**
     * API: Buscar apartados por folio o cliente
     */
    public function apiBuscar(Request $request)
    {
        $query = Apartado::with(['cliente'])
            ->where('estado', 'activo')
            ->where('saldo_pendiente', '>', 0);

        if ($request->filled('folio')) {
            $query->where('folio', 'like', '%' . $request->folio . '%');
        }

        if ($request->filled('cliente')) {
            $query->whereHas('cliente', function($q) use ($request) {
                $q->where('nombre', 'like', '%' . $request->cliente . '%');
            });
        }

        $apartados = $query->orderBy('fecha_apartado', 'desc')
            ->limit(10)
            ->get();

        return response()->json($apartados);
    }

    /**
     * Exportar apartados filtrados a Excel
     */
    public function exportExcel(Request $request)
    {
        $query = $this->buildFilteredQuery($request);
        $apartados = $query->get();
        
        // Crear spreadsheet usando el servicio
        $spreadsheet = $this->excelReportService->createSpreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Título
        $row = $this->excelReportService->setTitle($sheet, 'REPORTE DE APARTADOS', 'H', 1);
        $row++; // Espacio
        
        // Filtros aplicados
        $filtros = $this->buildFiltersList($request);
        $row = $this->excelReportService->setFilters($sheet, $filtros, $row);
        
        // Encabezados de tabla
        $headers = ['ID', 'Folio', 'Cliente', 'Fecha Apartado', 'Fecha Límite', 'Monto Total', 'Total Pagado', 'Saldo Pendiente', 'Estado'];
        $row = $this->excelReportService->setTableHeaders($sheet, $headers, $row);
        
        // Datos
        $data = [];
        foreach ($apartados as $apartado) {
            $data[] = [
                $apartado->id,
                $apartado->folio,
                $apartado->cliente->nombre,
                $apartado->fecha_apartado->format('d/m/Y'),
                $apartado->fecha_limite ? $apartado->fecha_limite->format('d/m/Y') : 'N/A',
                '$' . number_format($apartado->monto_total, 2),
                '$' . number_format($apartado->totalPagado, 2),
                '$' . number_format($apartado->saldo_pendiente, 2),
                $apartado->getEstadoLabel(),
            ];
        }
        
        $lastRow = $this->excelReportService->fillData($sheet, $data, $row);
        
        // Auto ajustar columnas
        $this->excelReportService->autoSizeColumns($sheet, ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I']);
        
        // Descargar
        $filename = $this->excelReportService->generateFilename('reporte_apartados');
        $this->excelReportService->download($spreadsheet, $filename);
    }

    /**
     * Exportar apartados filtrados a PDF
     */
    public function exportPdf(Request $request)
    {
        $query = $this->buildFilteredQuery($request);
        $apartados = $query->get();
        
        // Preparar filtros usando el helper
        $filtros = $this->buildFiltersList($request);
        
        // Obtener estilos base del servicio
        $styles = $this->pdfReportService->getBaseStyles();
        
        // Generar PDF usando el servicio
        $filename = $this->pdfReportService->generateFilename('reporte_apartados');
        
        return $this->pdfReportService->generate(
            'apartados.pdf-report',
            compact('apartados', 'filtros', 'styles'),
            $filename
        );
    }

    /**
     * Construir query con filtros aplicados
     */
    private function buildFilteredQuery(Request $request)
    {
        $query = Apartado::with(['cliente', 'detalles.libro', 'abonos']);

        // Filtro por estado
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        // Filtro por cliente
        if ($request->filled('cliente_id')) {
            $query->where('cliente_id', $request->cliente_id);
        }

        // Filtro por fechas
        if ($request->filled('fecha_desde')) {
            $query->whereDate('fecha_apartado', '>=', $request->fecha_desde);
        }
        if ($request->filled('fecha_hasta')) {
            $query->whereDate('fecha_apartado', '<=', $request->fecha_hasta);
        }

        return $query->orderBy('fecha_apartado', 'desc');
    }

    /**
     * Construir lista de filtros aplicados
     */
    private function buildFiltersList(Request $request): array
    {
        $filtros = [];
        
        if ($request->filled('cliente_id')) {
            $cliente = Cliente::find($request->cliente_id);
            if ($cliente) {
                $filtros[] = 'Cliente: ' . $cliente->nombre;
            }
        }
        
        if ($request->filled('estado')) {
            $estadoLabels = [
                'activo' => 'Estado: Activo',
                'liquidado' => 'Estado: Liquidado',
                'cancelado' => 'Estado: Cancelado'
            ];
            $filtros[] = $estadoLabels[$request->estado] ?? 'Estado: ' . $request->estado;
        }
        
        if ($request->filled('fecha_desde')) {
            $filtros[] = 'Desde: ' . date('d/m/Y', strtotime($request->fecha_desde));
        }
        
        if ($request->filled('fecha_hasta')) {
            $filtros[] = 'Hasta: ' . date('d/m/Y', strtotime($request->fecha_hasta));
        }
        
        if (empty($filtros)) {
            $filtros[] = 'Sin filtros aplicados';
        }
        
        return $filtros;
    }

    /**
     * API: Crear apartado desde app móvil
     */
    public function apiStore(Request $request)
    {
        if (!$this->hasValidMobileRole($request)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes autorización para crear apartados.'
            ], 403);
        }

        try {
            // Validar datos
            $validated = $request->validate([
                'tipo_inventario' => 'nullable|in:general,subinventario',
                'subinventario_id' => 'nullable|exists:subinventarios,id',
                'cod_congregante' => 'required|string',
                'cliente_id' => 'required|exists:clientes,id',
                'fecha_apartado' => 'required|date',
                'enganche' => 'required|numeric|min:0',
                'metodo_pago' => 'nullable|in:efectivo,tarjeta,transferencia,no_especificado',
                'fecha_limite' => 'nullable|date|after:today',
                'observaciones' => 'nullable|string|max:500',
                'usuario' => 'required|string',
                'libros' => 'required|array|min:1',
                'libros.*.libro_id' => 'required|exists:libros,id',
                'libros.*.cantidad' => 'required|integer|min:1',
                'libros.*.precio_unitario' => 'required|numeric|min:0',
                'libros.*.descuento' => 'nullable|numeric|min:0|max:100',
            ], [
                'tipo_inventario.in' => 'El tipo de inventario debe ser "general" o "subinventario"',
                'cod_congregante.required' => 'Falta el token de usuario',
                'cliente_id.required' => 'Debe seleccionar un cliente',
                'fecha_apartado.required' => 'La fecha de apartado es obligatoria',
                'enganche.required' => 'El enganche es obligatorio',
                'enganche.min' => 'El enganche debe ser mayor o igual a 0',
                'libros.required' => 'Debe agregar al menos un libro',
                'libros.min' => 'Debe agregar al menos un libro',
                'fecha_limite.after' => 'La fecha límite debe ser posterior a hoy',
                'usuario.required' => 'Falta el nombre del usuario',
            ]);

            // Determinar tipo de inventario (por defecto subinventario para retrocompatibilidad)
            $tipoInventario = $validated['tipo_inventario'] ?? 'subinventario';
            $esAdmin = $this->isAdminFromRequest($request);

            if ($tipoInventario === 'general' && !$esAdmin) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permiso para crear apartados en el inventario general. Solo Admin Librería y Supervisor.'
                ], 403);
            }
            
            // Si es subinventario, validar que se proporcione subinventario_id
            if ($tipoInventario === 'subinventario') {
                if (empty($validated['subinventario_id'])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Debe especificar el punto de venta (subinventario_id)'
                    ], 422);
                }

                // Validar que el subinventario esté activo
                $subinventario = \App\Models\SubInventario::find($validated['subinventario_id']);
                if ($subinventario->estado !== 'activo') {
                    return response()->json([
                        'success' => false,
                        'message' => 'El punto de venta no está activo'
                    ], 422);
                }
            } else {
                $subinventario = null;
            }

            DB::beginTransaction();

            // Generar folio único
            $folio = $this->codeGenerator->generateCode('Apartado', 'AP');

            // Calcular monto total y validar stock según tipo de inventario
            $montoTotal = 0;
            foreach ($validated['libros'] as &$libroData) {
                $libro = Libro::find($libroData['libro_id']);
                
                if (!$esAdmin) {
                    $libroData['precio_unitario'] = $libro->precio;
                }
                
                if ($tipoInventario === 'subinventario') {
                    // Verificar que el libro esté en el subinventario
                    $libroSubinventario = DB::table('subinventario_libro')
                        ->where('subinventario_id', $validated['subinventario_id'])
                        ->where('libro_id', $libroData['libro_id'])
                        ->first();

                    if (!$libroSubinventario) {
                        throw new \Exception("El libro '{$libro->nombre}' no está en este subinventario");
                    }

                    // Verificar stock disponible en subinventario
                    if ($libroSubinventario->cantidad < $libroData['cantidad']) {
                        throw new \Exception("Cantidad insuficiente para '{$libro->nombre}'. Disponible: {$libroSubinventario->cantidad}");
                    }
                } else {
                    // Verificar stock disponible en inventario general
                    $reservadoGeneral = DB::table('apartado_detalles as ad')
                        ->join('apartados as a', 'a.id', '=', 'ad.apartado_id')
                        ->where('ad.libro_id', $libro->id)
                        ->where('a.tipo_inventario', 'general')
                        ->where('a.estado', 'activo')
                        ->sum('ad.cantidad');
                    $stockDisponible = $libro->stock - $reservadoGeneral;
                    if ($stockDisponible < $libroData['cantidad']) {
                        throw new \Exception("Stock insuficiente en inventario general para '{$libro->nombre}'. Disponible: {$stockDisponible}");
                    }
                }

                // Calcular subtotal con descuento
                $precio = $libroData['precio_unitario'];
                $descuento = $libroData['descuento'] ?? 0;
                if ($descuento > 0) {
                    $precio -= ($precio * $descuento / 100);
                }
                $subtotal = $precio * $libroData['cantidad'];
                $montoTotal += $subtotal;
            }
            unset($libroData);

            // Validar que el enganche no sea mayor al total
            if ($validated['enganche'] > $montoTotal) {
                throw new \Exception('El enganche no puede ser mayor al monto total del apartado');
            }

            // Preparar observaciones con información del origen
            $observacionesBase = $validated['observaciones'] ?? '';
            if ($tipoInventario === 'subinventario') {
                $observacionesBase .= "\n--- DATOS DE SUBINVENTARIO ---\nSubinventario ID: {$validated['subinventario_id']}\nDescripción: {$subinventario->nombre_display}";
            } else {
                $observacionesBase .= "\n--- INVENTARIO GENERAL ---\nApartado desde inventario general";
            }

            // Crear apartado
            $apartado = Apartado::create([
                'folio' => $folio,
                'cliente_id' => $validated['cliente_id'],
                'fecha_apartado' => $validated['fecha_apartado'],
                'monto_total' => $montoTotal,
                'enganche' => $validated['enganche'],
                'saldo_pendiente' => $montoTotal - $validated['enganche'],
                'fecha_limite' => $validated['fecha_limite'] ?? null,
                'estado' => 'activo',
                'observaciones' => $observacionesBase,
                'usuario' => $validated['usuario'],
                'tipo_inventario' => $tipoInventario,
                'subinventario_id' => $tipoInventario === 'subinventario' ? $validated['subinventario_id'] : null,
            ]);

            // Crear detalles y actualizar stock
            foreach ($validated['libros'] as $libroData) {
                $libro = Libro::find($libroData['libro_id']);
                
                $precio = $libroData['precio_unitario'];
                $descuento = $libroData['descuento'] ?? 0;
                if ($descuento > 0) {
                    $precio -= ($precio * $descuento / 100);
                }
                $subtotal = $precio * $libroData['cantidad'];

                ApartadoDetalle::create([
                    'apartado_id' => $apartado->id,
                    'libro_id' => $libroData['libro_id'],
                    'cantidad' => $libroData['cantidad'],
                    'precio_unitario' => $libroData['precio_unitario'],
                    'descuento' => $descuento,
                    'subtotal' => $subtotal,
                ]);

            }

            $this->stockService->reserveApartado($apartado);

            // Si hubo enganche, crear el primer abono
            if ($validated['enganche'] > 0) {
                $enganche = $apartado->abonos()->create([
                    'fecha_abono' => $validated['fecha_apartado'],
                    'monto' => $validated['enganche'],
                    'saldo_anterior' => $montoTotal,
                    'saldo_nuevo' => $montoTotal - $validated['enganche'],
                    'metodo_pago' => $validated['metodo_pago'] ?? 'no_especificado',
                    'observaciones' => 'Enganche inicial desde app móvil',
                    'usuario' => $validated['usuario'],
                ]);
                $this->ingresoCajaService->registrarAbono($enganche, 'automatico', 'enganche_apartado');
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Apartado creado exitosamente',
                'data' => [
                    'apartado_id' => $apartado->id,
                    'folio' => $apartado->folio,
                    'monto_total' => number_format($apartado->monto_total, 2),
                    'enganche' => number_format($apartado->enganche, 2),
                    'saldo_pendiente' => number_format($apartado->saldo_pendiente, 2),
                    'estado' => $apartado->estado,
                    'fecha_apartado' => $apartado->fecha_apartado->format('Y-m-d'),
                    'fecha_limite' => $apartado->fecha_limite ? $apartado->fecha_limite->format('Y-m-d') : null,
                ]
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            $requestData = $request->except(['_token']);
            if (isset($requestData['cod_congregante'])) {
                $requestData['cod_congregante'] = '***';
            }
            if (isset($requestData['password'])) {
                $requestData['password'] = '***';
            }
            
            Log::error('Error al crear apartado desde API', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $requestData,
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el apartado: ' . $e->getMessage()
            ], 500);
        }
    }
}
