<?php

namespace App\Http\Controllers;

use App\Models\Venta;
use App\Models\Libro;
use App\Models\Movimiento;
use App\Models\SubInventario;
use App\Services\CodeGeneratorService;
use App\Services\ExcelReportService;
use App\Services\PdfReportService;
use App\Services\InventoryStockService;
use App\Services\IngresoCajaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Traits\HasRoleChecks;

class VentaController extends Controller
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
        $query = Venta::with(['movimientos.libro', 'cliente', 'pagos', 'apartado']);

        // Debug: Log de filtros recibidos
        \Log::info('Filtros recibidos en VentaController.index:', $request->all());

        // ===== FILTROS PARA REPORTES =====

        // Filtro por rango de fechas (MUY IMPORTANTE PARA REPORTES)
        if ($request->filled('fecha_desde')) {
            $query->where('fecha_venta', '>=', $request->fecha_desde);
        }
        if ($request->filled('fecha_hasta')) {
            $query->where('fecha_venta', '<=', $request->fecha_hasta);
        }

        // Filtro por cliente específico
        if ($request->filled('cliente_id')) {
            $query->where('cliente_id', $request->cliente_id);
        }

        // Filtro por estado de la venta
        if ($request->filled('estado')) {
            $query->estado($request->estado);
        }

        // Filtro por tipo de pago
        if ($request->filled('tipo_pago')) {
            $query->tipoPago($request->tipo_pago);
        }

        // Filtro por estado de pago (para ventas a plazos)
        if ($request->filled('estado_pago')) {
            $query->estadoPago($request->estado_pago);
        }

        // Filtro por ventas a plazos
        if ($request->filled('es_a_plazos')) {
            if ($request->es_a_plazos == '1') {
                $query->ventasAPlazo();
            } elseif ($request->es_a_plazos == '0') {
                $query->where('es_a_plazos', false);
            }
        }

        // Filtro por apartados
        if ($request->filled('es_apartado')) {
            if ($request->es_apartado == '1') {
                $query->esApartado(true);
            } elseif ($request->es_apartado == '0') {
                $query->esApartado(false);
            }
        }

        // Filtro por ventas vencidas (a plazos que pasaron su fecha límite sin pagar)
        if ($request->filled('vencidas') && $request->vencidas == '1') {
            $query->ventasVencidas();
        }

        // Filtro por rango de montos
        if ($request->filled('monto_min')) {
            $query->where('total', '>=', $request->monto_min);
        }
        if ($request->filled('monto_max')) {
            $query->where('total', '<=', $request->monto_max);
        }

        // Filtro por libro específico vendido
        if ($request->filled('libro_id')) {
            $query->conLibro($request->libro_id);
        }

        // Filtro por tipo de inventario (general o sub-inventario)
        if ($request->filled('tipo_inventario')) {
            $query->where('tipo_inventario', $request->tipo_inventario);
        }

        // Filtro por sub-inventario específico
        if ($request->filled('subinventario_id')) {
            if ($request->subinventario_id === 'general') {
                // Filtrar solo ventas del inventario general
                $query->where(function ($q) {
                    $q->where('tipo_inventario', '!=', 'subinventario')
                      ->orWhereNull('subinventario_id');
                });
            } else {
                // Filtrar por sub-inventario específico
                $query->where('subinventario_id', $request->subinventario_id);
            }
        }

        // Búsqueda general (ID, cliente, observaciones)
        if ($request->filled('search')) {
            $query->search($request->search);
        }

        // Ordenar
        $ordenar = $request->get('ordenar', 'reciente');
        switch ($ordenar) {
            case 'antiguo':
                $query->orderBy('id', 'asc');
                break;
            case 'monto_mayor':
                $query->orderBy('total', 'desc');
                break;
            case 'monto_menor':
                $query->orderBy('total', 'asc');
                break;
            case 'cliente':
                $query->leftJoin('clientes', 'ventas.cliente_id', '=', 'clientes.id')
                      ->orderBy('clientes.nombre', 'asc')
                      ->select('ventas.*');
                break;
            case 'saldo_mayor':
                $query->orderByRaw('(total - total_pagado) DESC');
                break;
            default: // reciente
                $query->orderBy('id', 'desc');
                break;
        }

        $ventas = $query->paginate(10)->withQueryString();

        // Calcular estadísticas para la vista
        $estadisticas = $this->calcularEstadisticas($query);

        // Obtener clientes para el filtro
        $clientes = \App\Models\Cliente::orderBy('nombre')->get();
        
        // Obtener libros para el filtro
        $libros = \App\Models\Libro::orderBy('nombre')->get();

        // Obtener sub-inventarios para el filtro
        $subinventarios = \App\Models\SubInventario::orderBy('descripcion')->get();

        return view('ventas.index', compact('ventas', 'estadisticas', 'clientes', 'libros', 'subinventarios'));
    }

    /**
     * Calcular estadísticas de las ventas filtradas
     */
    private function calcularEstadisticas($query)
    {
        // Clonar query para no afectar la paginación
        $queryStats = clone $query;
        
        // Limpiar bindings y cláusulas de select y orderBy para optimizar
        $queryStats->getQuery()->orders = null;
        $queryStats->getQuery()->columns = null;

        // Ejecutar las agregaciones directamente en la base de datos
        $stats = $queryStats->selectRaw("
            COUNT(CASE WHEN estado != 'cancelada' THEN 1 END) as total_ventas,
            SUM(CASE WHEN estado != 'cancelada' THEN total ELSE 0 END) as total_monto,
            SUM(CASE WHEN estado != 'cancelada' THEN total_pagado ELSE 0 END) as total_pagado,
            SUM(CASE WHEN estado != 'cancelada' THEN (total - total_pagado) ELSE 0 END) as total_pendiente,
            COUNT(CASE WHEN estado = 'completada' THEN 1 END) as ventas_completadas,
            COUNT(CASE WHEN estado = 'cancelada' THEN 1 END) as ventas_canceladas,
            COUNT(CASE WHEN estado != 'cancelada' AND es_a_plazos = 1 THEN 1 END) as ventas_a_plazos,
            COUNT(CASE WHEN estado != 'cancelada' AND es_a_plazos = 1 AND estado_pago != 'completado' AND fecha_limite IS NOT NULL AND fecha_limite < ? THEN 1 END) as ventas_vencidas
        ", [now()->toDateString()])->first();

        return [
            'total_ventas' => (int) ($stats->total_ventas ?? 0),
            'total_monto' => (float) ($stats->total_monto ?? 0),
            'total_pagado' => (float) ($stats->total_pagado ?? 0),
            'total_pendiente' => (float) ($stats->total_pendiente ?? 0),
            'ventas_completadas' => (int) ($stats->ventas_completadas ?? 0),
            'ventas_canceladas' => (int) ($stats->ventas_canceladas ?? 0),
            'ventas_a_plazos' => (int) ($stats->ventas_a_plazos ?? 0),
            'ventas_vencidas' => (int) ($stats->ventas_vencidas ?? 0),
        ];
    }

    public function create()
    {
        // Obtener libros con stock disponible en inventario general
        $libros = Libro::where('stock', '>', 0)
            ->orderBy('nombre')
            ->get();

        // Obtener subinventarios activos con sus libros
        $subinventarios = \App\Models\SubInventario::where('estado', 'activo')
            ->with('libros')
            ->orderBy('fecha_subinventario', 'desc')
            ->get()
            ->map(function($sub) {
                $sub->libros_data = $sub->libros->map(function($l) {
                    return [
                        'id' => $l->id,
                        'nombre' => $l->nombre,
                        'codigo_barras' => $l->codigo_barras,
                        'precio' => $l->precio,
                        'stock' => $l->pivot->cantidad  // Usar 'stock' para que sea consistente con el componente
                    ];
                });
                return $sub;
            });

        return view('ventas.create', compact('libros', 'subinventarios'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'tipo_inventario' => 'required|in:general,subinventario',
                'subinventario_id' => 'nullable|required_if:tipo_inventario,subinventario|exists:subinventarios,id',
                'cliente_id' => 'nullable|exists:clientes,id',
                'fecha_venta' => 'required|date',
                'tipo_pago' => 'nullable|in:contado,credito,mixto',
                'metodo_pago' => 'nullable|in:efectivo,tarjeta,transferencia,no_especificado',
                'descuento_global' => 'nullable|numeric|min:0|max:100',
                'observaciones' => 'nullable|string|max:500',
                'es_a_plazos' => 'nullable|boolean',
                'tiene_envio' => 'nullable|boolean',
                'costo_envio' => 'nullable|numeric|min:0',
                'fecha_limite' => 'nullable|date',
                
                // Movimientos
                'libros' => 'required|array|min:1',
                'libros.*.libro_id' => 'required|exists:libros,id',
                'libros.*.cantidad' => 'required|integer|min:1',
                'libros.*.descuento' => 'nullable|numeric|min:0|max:100',
                'libros.*.precio_custom' => 'nullable|numeric|min:0',
            ], [
                'tipo_inventario.required' => 'Debes seleccionar el tipo de inventario',
                'subinventario_id.required_if' => 'Debes seleccionar un subinventario',
                'fecha_venta.required' => 'La fecha de venta es obligatoria',
                'libros.required' => 'Debes agregar al menos un libro a la venta',
                'libros.min' => 'Debes agregar al menos un libro a la venta',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Rethrow para que se maneje normalmente
            throw $e;
        }

        DB::beginTransaction();
        try {
            $esAPLazos = false;
            $validated['tipo_pago'] = 'contado';
            $tipoInventario = $validated['tipo_inventario'];
            $subinventarioId = $validated['subinventario_id'] ?? null;
            
            
            
            // Validar que si es a plazos, debe tener cliente
            if ($esAPLazos && empty($validated['cliente_id'])) {
                throw new \DomainException('Las ventas a plazos requieren un cliente asignado');
            }
            
            // Validar stock según el tipo de inventario
            if ($tipoInventario === 'general') {
                // Validar siempre, incluso en ventas a plazos, para no sobrevender stock reservado
                $this->lockAndValidateGeneralStock($validated['libros']);
            } else {
                // Validación para subinventario
                $this->lockAndValidateSubinventarioStock($subinventarioId, $validated['libros']);
            }

            // Crear la venta
            $venta = Venta::create([
                'cliente_id' => $validated['cliente_id'],
                'fecha_venta' => $validated['fecha_venta'],
                'tipo_pago' => 'contado',
                'metodo_pago' => $validated['metodo_pago'] ?? 'no_especificado',
                'descuento_global' => $validated['descuento_global'] ?? 0,
                'estado' => 'completada',
                'observaciones' => $validated['observaciones'] ?? '',
                'usuario' => session('username'),
                'tipo_inventario' => $tipoInventario,
                'subinventario_id' => $subinventarioId,
                'es_a_plazos' => false,
                'tiene_envio' => isset($validated['tiene_envio']) && $validated['tiene_envio'],
                'costo_envio' => isset($validated['tiene_envio']) && $validated['tiene_envio'] ? ($validated['costo_envio'] ?? 0) : 0,
                'fecha_limite' => null,
                'estado_pago' => 'completado',
                'total_pagado' => 0,
            ]);

            // Crear los movimientos asociados
            foreach ($validated['libros'] as $item) {
                $libro = Libro::findOrFail($item['libro_id']);

                // Determinar el precio unitario a usar
                // Si es admin y especificó un precio personalizado, usarlo
                $precioUnitario = $libro->precio;
                
                if ($this->isAdmin() && isset($item['precio_custom']) && !empty($item['precio_custom'])) {
                    $precioUnitario = floatval($item['precio_custom']);
                }

                // Crear movimiento
                $movimiento = Movimiento::create([
                    'venta_id' => $venta->id,
                    'libro_id' => $libro->id,
                    'tipo_movimiento' => 'salida',
                    'tipo_salida' => 'venta',
                    'cantidad' => $item['cantidad'],
                    'precio_unitario' => $precioUnitario,
                    'descuento' => $item['descuento'] ?? 0,
                    'fecha' => $validated['fecha_venta'],
                    'observaciones' => "Venta #{$venta->id}" . ($tipoInventario === 'subinventario' ? " - SubInv #{$subinventarioId}" : ''),
                    'usuario' => session('username'),
                ]);

            }



            // Calcular y actualizar totales de la venta
            $venta->actualizarTotales();
            
            $venta->total_pagado = $venta->total;
            $venta->save();
            $this->ingresoCajaService->registrarVenta($venta);

            DB::commit();

            return redirect()->route('ventas.show', $venta)
                ->with('success', 'Venta registrada exitosamente');

        } catch (\DomainException $e) {
            DB::rollBack();
            return back()->withErrors(['error' => $e->getMessage()])
                ->withInput();
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error al crear venta: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return back()->withErrors(['error' => 'Error al registrar la venta: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Venta $venta)
    {
        $venta->load(['movimientos.libro', 'cliente', 'apartado', 'subinventario', 'pagos']);
        
        return view('ventas.show', compact('venta'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Venta $venta)
    {
        // Solo permitir editar a usuarios con rol ADMIN LIBRERIA
        if (!$this->isAdmin()) {
            return redirect()->route('ventas.index')
                ->with('error', 'No tienes permisos para editar ventas. Solo administradores.');
        }

        if ($venta->tipo_inventario === 'subinventario' || $venta->es_a_plazos || $venta->pagos()->exists()) {
            return redirect()->route('ventas.show', $venta)
                ->with('error', 'Por seguridad de inventario, las ventas de subinventario o con pagos no se pueden editar. Cancela la operación y regístrala nuevamente.');
        }

        // Cargar relaciones necesarias
        $venta->load(['movimientos.libro', 'cliente', 'pagos']);

        // Obtener todos los libros disponibles (con stock > 0)
        $libros = Libro::where('stock', '>', 0)
            ->orderBy('nombre')
            ->get();

        // Agregar los libros que están en esta venta pero que podrían no tener stock
        // o incluso haber sido eliminados (para permitir su edición)
        $librosEnVenta = $venta->movimientos->pluck('libro_id')->toArray();
        $librosAdicionales = Libro::whereIn('id', $librosEnVenta)
            ->whereNotIn('id', $libros->pluck('id'))
            ->orderBy('nombre')
            ->get();
        
        // Combinar ambas colecciones
        $libros = $libros->merge($librosAdicionales);

        // No necesitamos subinventarios en edición
        $subinventarios = collect([]);

        return view('ventas.edit', compact('venta', 'libros', 'subinventarios'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Venta $venta)
    {
        // Solo permitir actualizar a usuarios con rol ADMIN LIBRERIA
        if (!$this->isAdmin()) {
            return redirect()->route('ventas.index')
                ->with('error', 'No tienes permisos para editar ventas. Solo administradores.');
        }


        if ($venta->tipo_inventario === 'subinventario' || $venta->es_a_plazos || $venta->pagos()->exists()) {
            return redirect()->route('ventas.show', $venta)
                ->with('error', 'Por seguridad de inventario, las ventas de subinventario o con pagos no se pueden editar.');
        }

        $validated = $request->validate([
            'cliente_id' => 'nullable|exists:clientes,id',
            'fecha_venta' => 'required|date',
            'tipo_pago' => 'nullable|in:contado,credito,mixto',
            'metodo_pago' => 'nullable|in:efectivo,tarjeta,transferencia,no_especificado',
            'observaciones' => 'nullable|string|max:500',
            'descuento_global' => 'nullable|numeric|min:0|max:100',
            'es_a_plazos' => 'nullable|boolean',
            'tiene_envio' => 'nullable|boolean',
            'costo_envio' => 'nullable|numeric|min:0',
            'fecha_limite' => 'nullable|date',
            
            // Movimientos
            'libros' => 'required|array|min:1',
            'libros.*.libro_id' => 'required|exists:libros,id',
            'libros.*.cantidad' => 'required|integer|min:1',
            'libros.*.descuento' => 'nullable|numeric|min:0|max:100',
            'libros.*.precio_custom' => 'nullable|numeric|min:0',
        ], [
            'fecha_venta.required' => 'La fecha de venta es obligatoria',
            'libros.required' => 'Debes agregar al menos un libro a la venta',
            'libros.min' => 'Debes agregar al menos un libro a la venta',
        ]);

        DB::beginTransaction();
        try {
            $venta = Venta::whereKey($venta->id)->lockForUpdate()->firstOrFail();
            $esAPLazos = false;
            $validated['tipo_pago'] = 'contado';

            if ($esAPLazos) {
                throw new \DomainException('No se puede convertir una venta existente en venta a plazos. Registra una nueva venta.');
            }
            
            // Validar que si es a plazos, debe tener cliente
            if ($esAPLazos && empty($validated['cliente_id'])) {
                throw new \DomainException('Las ventas a plazos requieren un cliente asignado');
            }

            // Lock all books (old and new) in sorted order to prevent deadlocks and ensure consistent state
            $oldBookIds = $venta->movimientos()->pluck('libro_id')->toArray();
            $newBookIds = collect($validated['libros'])->pluck('libro_id')->toArray();
            $allBookIds = array_unique(array_merge($oldBookIds, $newBookIds));
            sort($allBookIds);
            if (!empty($allBookIds)) {
                Libro::whereIn('id', $allBookIds)->lockForUpdate()->get();
            }

            // Eliminar todos los movimientos actuales (usando each->delete para que se disparen los eventos de Eloquent)
            $venta->movimientos->each->delete();

            // Calcular totales
            $subtotal = 0;
            $descuentoGlobal = $validated['descuento_global'] ?? 0;

            // Crear los nuevos movimientos
            foreach ($validated['libros'] as $item) {
                $libro = Libro::whereKey($item['libro_id'])->lockForUpdate()->firstOrFail();
                $cantidad = $item['cantidad'];
                $descuentoItem = $item['descuento'] ?? 0;
                
                // Determinar el precio unitario a usar
                // Si es admin y especificó un precio personalizado, usarlo
                $precioUnitario = $libro->precio;
                
                if ($this->isAdmin() && isset($item['precio_custom']) && !empty($item['precio_custom'])) {
                    $precioUnitario = floatval($item['precio_custom']);
                }
                
                $precioConDescuento = $precioUnitario * (1 - $descuentoItem / 100);
                $subtotalItem = $precioConDescuento * $cantidad;
                
                $subtotal += $subtotalItem;

                // Crear movimiento
                Movimiento::create([
                    'libro_id' => $libro->id,
                    'venta_id' => $venta->id,
                    'tipo_movimiento' => 'salida',
                    'tipo_salida' => 'venta',
                    'cantidad' => $cantidad,
                    'precio_unitario' => $precioUnitario,
                    'descuento' => $descuentoItem,
                    'observaciones' => 'Actualización de venta',
                    'usuario' => session('username'),
                    'fecha' => $validated['fecha_venta'],
                ]);
            }



            // Calcular total con descuento global y costo de envío
            $descuentoMonto = $subtotal * ($descuentoGlobal / 100);
            $total = $subtotal - $descuentoMonto;
            
            // Sumar costo de envío si aplica
            $costoEnvio = isset($validated['tiene_envio']) && $validated['tiene_envio'] ? ($validated['costo_envio'] ?? 0) : 0;
            if ($costoEnvio > 0) {
                $total += $costoEnvio;
            }

            // Actualizar la venta
            $venta->update([
                'cliente_id' => $validated['cliente_id'],
                'fecha_venta' => $validated['fecha_venta'],
                'tipo_pago' => 'contado',
                'metodo_pago' => $validated['metodo_pago'] ?? $venta->metodo_pago ?? 'no_especificado',
                'subtotal' => $subtotal,
                'descuento_global' => $descuentoGlobal,
                'total' => $total,
                'total_pagado' => $total,
                'observaciones' => $validated['observaciones'] ?? '',
                'es_a_plazos' => false,
                'tiene_envio' => isset($validated['tiene_envio']) && $validated['tiene_envio'],
                'costo_envio' => $costoEnvio,
                'fecha_limite' => null,
                'estado_pago' => 'completado',
            ]);

            $this->ingresoCajaService->registrarVenta($venta->fresh());

            DB::commit();

            return redirect()->route('ventas.show', $venta)
                ->with('success', 'Venta actualizada exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Error al actualizar la venta: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Venta $venta)
    {
        // No permitir eliminar ventas completadas
        if ($venta->estado === 'completada') {
            return back()->with('error', 'No se pueden eliminar ventas completadas');
        }

        DB::beginTransaction();
        try {
            // El stock ya fue restaurado al cancelar. Eliminar nunca debe mover existencias.
            if ($venta->estado === 'cancelada') {
                $venta->movimientos()->delete();
            }

            $venta->delete();
            DB::commit();

            return redirect()->route('ventas.index')
                ->with('success', 'Venta eliminada exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error al eliminar la venta: ' . $e->getMessage());
        }
    }

    /**
     * Cancelar una venta
     */
    public function cancelar(Venta $venta)
    {
        DB::beginTransaction();
        try {
            $venta = Venta::whereKey($venta->id)->lockForUpdate()->firstOrFail();
            if ($venta->estado === 'cancelada') {
                DB::rollBack();
                return back()->with('warning', 'Esta venta ya está cancelada');
            }

            $movimientos = $venta->movimientos()->where('tipo_movimiento', 'salida')->with('libro')->get();
            
            // Lock books in sorted order to prevent deadlocks
            $libroIds = $movimientos->pluck('libro_id')->unique()->toArray();
            sort($libroIds);
            if (!empty($libroIds)) {
                Libro::whereIn('id', $libroIds)->lockForUpdate()->get();
            }

            // Restaurar el stock y registrar movimientos de entrada
            foreach ($movimientos as $movimiento) {
                $libro = $movimiento->libro;
                
                // Verificar si la venta era de un subinventario
                $esDeSubinventario = ($venta->tipo_inventario === 'subinventario' && !is_null($venta->subinventario_id));
                $subinventarioId = $venta->subinventario_id;
                
                // Registrar movimiento de entrada por cancelación de venta
                $observaciones = 'Cancelación de venta #' . $venta->id;
                if ($esDeSubinventario) {
                    $observaciones .= ' - Devuelto a SubInv #' . $subinventarioId;
                }
                
                \App\Models\Movimiento::create([
                    'libro_id' => $libro->id,
                    'tipo_movimiento' => 'entrada',
                    'tipo_entrada' => 'devolucion',
                    'cantidad' => $movimiento->cantidad,
                    'precio_unitario' => $movimiento->precio_unitario,
                    'observaciones' => $observaciones,
                    'fecha' => now(),
                    'venta_id' => $venta->id,
                    'usuario' => session('username', 'Sistema'),
                ]);
            }

            // Si la venta se generó de un apartado, reactivarlo y volver a reservar su stock
            if ($venta->apartado_id) {
                $apartado = \App\Models\Apartado::whereKey($venta->apartado_id)->lockForUpdate()->firstOrFail();
                $apartado->update([
                    'estado' => 'activo',
                    'venta_id' => null,
                ]);
                $this->stockService->reserveApartado($apartado);
            }

            $venta->update(['estado' => 'cancelada']);
            $this->ingresoCajaService->cancelarPorVenta($venta);

            DB::commit();

            return back()->with('success', 'Venta cancelada exitosamente. Stock restaurado y movimientos registrados.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error al cancelar la venta: ' . $e->getMessage());
        }
    }

    /**
     * Exportar ventas filtradas a Excel con DOS HOJAS
     * Hoja 1: Resumen de Ventas
     * Hoja 2: Detalle de Productos por Venta
     */
    public function exportExcel(Request $request)
    {
        // Construir query con filtros
        $query = $this->buildFilteredQuery($request);
        $totalCount = (clone $query)->count();
        
        // Crear spreadsheet
        $spreadsheet = $this->excelReportService->createSpreadsheet();
        
        // ===== HOJA 1: RESUMEN DE VENTAS =====
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('Resumen de Ventas');
        
        // Título
        $row = $this->excelReportService->setTitle($sheet1, 'REPORTE DE VENTAS - RESUMEN', 'J', 1);
        $row++; // Espacio
        
        // Filtros aplicados
        $filtros = $this->buildFiltersList($request);
        $row = $this->excelReportService->setFilters($sheet1, $filtros, $row);
        
        // Estadísticas generales
        if ($totalCount > 0) {
            $ventasActivasQuery = (clone $query)->where('estado', '!=', 'cancelada');
            $ventasActivasCount = $ventasActivasQuery->count();
            $totalMonto = $ventasActivasQuery->sum('total');
            $totalUnidades = \App\Models\Movimiento::whereIn('venta_id', (clone $ventasActivasQuery)->select('id'))->sum('cantidad');
            $ventasConEnvio = (clone $ventasActivasQuery)->where('tiene_envio', true)->count();
            $canceladasCount = (clone $query)->where('estado', 'cancelada')->count();
            
            $sheet1->setCellValue('A' . $row, 'ESTADÍSTICAS GENERALES (EXCLUYENDO CANCELADAS):');
            $sheet1->getStyle('A' . $row)->getFont()->setBold(true);
            $row++;
            
            $sheet1->setCellValue('A' . $row, 'Total de ventas activas: ' . $ventasActivasCount);
            $row++;
            $sheet1->setCellValue('A' . $row, 'Monto total: $' . number_format($totalMonto, 2));
            $row++;
            $sheet1->setCellValue('A' . $row, 'Unidades vendidas: ' . $totalUnidades);
            $row++;
            $sheet1->setCellValue('A' . $row, 'Ventas con envío: ' . $ventasConEnvio);
            $row++;
            $sheet1->setCellValue('A' . $row, 'Ventas canceladas: ' . $canceladasCount);
            $row += 2; // Espacio
        }
        
        // Encabezados de tabla resumen
        $headers = ['ID Venta', 'Fecha', 'Cliente', 'Origen', 'Apartado', '# Libros', 'Total Unidades', 'Tipo Pago', 'Descuento', 'Total Venta', 'Con Envío', 'Estado'];
        $row = $this->excelReportService->setTableHeaders($sheet1, $headers, $row);
        
        // Eager load relationships for lazy loading
        $lazyQuery = (clone $query)->with(['movimientos.libro', 'cliente', 'apartado', 'subinventario']);

        // Datos del resumen
        $dataSummary = [];
        foreach ($lazyQuery->lazy(250) as $venta) {
            // Determinar origen
            $origen = 'General';
            if ($venta->tipo_inventario === 'subinventario' && $venta->subinventario) {
                $origen = 'SubInv #' . $venta->subinventario->id;
            }
            
            // Determinar si es apartado
            $apartado = 'No';
            if ($venta->esApartado() && $venta->apartado) {
                $apartado = 'Sí (Apt #' . $venta->apartado->id . ')';
            }
            
            $dataSummary[] = [
                $venta->id,
                $venta->fecha_venta->format('d/m/Y H:i'),
                $venta->cliente?->nombre ?: 'Sin cliente',
                $origen,
                $apartado,
                $venta->movimientos->count(),
                $venta->movimientos->sum('cantidad'),
                $venta->getTipoPagoLabel(),
                $venta->descuento_global ? $venta->descuento_global . '%' : '0%',
                '$' . number_format($venta->total, 2),
                $venta->tiene_envio ? 'Sí' : 'No',
                $venta->getEstadoUnificadoLabel(),
            ];
        }
        
        $this->excelReportService->fillData($sheet1, $dataSummary, $row);
        $this->excelReportService->autoSizeColumns($sheet1, ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L']);
        
        // ===== HOJA 2: DETALLE DE PRODUCTOS =====
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Detalle de Productos');
        
        // Título
        $row2 = $this->excelReportService->setTitle($sheet2, 'REPORTE DE VENTAS - DETALLE DE PRODUCTOS', 'I', 1);
        $row2++; // Espacio
        
        // Encabezados del detalle
        $headersDetail = ['ID Venta', 'Fecha Venta', 'Cliente', 'Nombre Libro', 'Cantidad', 'Precio Unitario', 'Descuento %', 'Subtotal', 'Estado Venta'];
        $row2 = $this->excelReportService->setTableHeaders($sheet2, $headersDetail, $row2);
        
        // Datos del detalle
        $dataDetail = [];
        foreach ($lazyQuery->lazy(250) as $venta) {
            foreach ($venta->movimientos as $movimiento) {
                $subtotal = ($movimiento->precio_unitario - ($movimiento->precio_unitario * $movimiento->descuento / 100)) * $movimiento->cantidad;
                
                $dataDetail[] = [
                    $venta->id,
                    $venta->fecha_venta->format('d/m/Y H:i'),
                    $venta->cliente?->nombre ?: 'Sin cliente',
                    $movimiento->libro?->nombre ?: 'Producto eliminado',
                    $movimiento->cantidad,
                    '$' . number_format($movimiento->precio_unitario, 2),
                    $movimiento->descuento ? $movimiento->descuento . '%' : '0%',
                    '$' . number_format($subtotal, 2),
                    $venta->getEstadoUnificadoLabel(),
                ];
            }
        }
        
        $this->excelReportService->fillData($sheet2, $dataDetail, $row2);
        $this->excelReportService->autoSizeColumns($sheet2, ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I']);
        
        // Descargar
        $filename = $this->excelReportService->generateFilename('reporte_ventas_detallado');
        $this->excelReportService->download($spreadsheet, $filename);
    }

    /**
     * Exportar ventas filtradas a PDF
     * Nota: Solo funciona con menos de 50 ventas para evitar problemas de memoria
     */
    public function exportPdf(Request $request)
    {
        try {
            // Construir query con filtros
            $query = $this->buildFilteredQuery($request);
            $totalCount = (clone $query)->count();
            
            // Validar que no haya demasiadas ventas (DOMPDF tiene limitaciones de memoria)
            if ($totalCount > 50) {
                return response()->json([
                    'error' => true,
                    'message' => 'El reporte PDF solo soporta hasta 50 ventas. Tienes ' . $totalCount . ' ventas en los resultados. Por favor, usa el formato Excel para reportes más grandes, o aplica filtros adicionales para reducir los resultados.'
                ], 422);
            }
            
            $ventas = $query->with(['movimientos.libro', 'cliente', 'apartado', 'subinventario'])->get();
            
            // Preparar filtros
            $filtros = $this->buildFiltersList($request);
            
            // Calcular estadísticas (excluyendo canceladas)
            $ventasActivasQuery = (clone $query)->where('estado', '!=', 'cancelada');
            
            $estadisticas = [
                'total' => $ventasActivasQuery->count(),
                'monto_total' => $ventasActivasQuery->sum('total'),
                'unidades_vendidas' => \App\Models\Movimiento::whereIn('venta_id', (clone $ventasActivasQuery)->select('id'))->sum('cantidad'),
                'ventas_con_envio' => (clone $ventasActivasQuery)->where('tiene_envio', true)->count(),
                'completadas' => (clone $query)->where('estado', 'completada')->count(),
                'canceladas' => (clone $query)->where('estado', 'cancelada')->count(),
            ];
            
            // Obtener estilos base
            $styles = $this->pdfReportService->getBaseStyles();
            
            // Generar PDF
            $filename = $this->pdfReportService->generateFilename('reporte_ventas_detallado');
            
            return $this->pdfReportService->generate(
                'ventas.pdf-report',
                compact('ventas', 'filtros', 'estadisticas', 'styles'),
                $filename,
                ['orientation' => 'landscape'] // Landscape para más columnas
            );
        } catch (\Exception $e) {
            \Log::error('Error al generar PDF de ventas', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return response()->json([
                'error' => true,
                'message' => 'Error al generar el PDF: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Construir query con filtros (helper privado)
     */
    private function buildFilteredQuery(Request $request)
    {
        $query = Venta::with(['movimientos.libro', 'cliente', 'pagos', 'subinventario', 'apartado']);

        // Filtro por rango de fechas
        if ($request->filled('fecha_desde')) {
            $query->where('fecha_venta', '>=', $request->fecha_desde);
        }
        if ($request->filled('fecha_hasta')) {
            $query->where('fecha_venta', '<=', $request->fecha_hasta);
        }

        // Filtro por cliente
        if ($request->filled('cliente_id')) {
            $query->where('cliente_id', $request->cliente_id);
        }

        // Filtro por estado
        if ($request->filled('estado')) {
            $query->estado($request->estado);
        }

        // Filtro por tipo de pago
        if ($request->filled('tipo_pago')) {
            $query->tipoPago($request->tipo_pago);
        }

        // Filtro por estado de pago
        if ($request->filled('estado_pago')) {
            $query->estadoPago($request->estado_pago);
        }

        // Filtro por apartados
        if ($request->filled('es_apartado')) {
            if ($request->es_apartado == '1') {
                $query->esApartado(true);
            } elseif ($request->es_apartado == '0') {
                $query->esApartado(false);
            }
        }

        // Filtro por ventas vencidas
        if ($request->filled('vencidas') && $request->vencidas == '1') {
            $query->ventasVencidas();
        }

        // Filtro por libro
        if ($request->filled('libro_id')) {
            $query->conLibro($request->libro_id);
        }

        // Filtro por tipo de inventario (general o sub-inventario)
        if ($request->filled('tipo_inventario')) {
            $query->where('tipo_inventario', $request->tipo_inventario);
        }

        // Filtro por sub-inventario específico
        if ($request->filled('subinventario_id')) {
            if ($request->subinventario_id === 'general') {
                // Filtrar solo ventas del inventario general
                $query->where(function ($q) {
                    $q->where('tipo_inventario', '!=', 'subinventario')
                      ->orWhereNull('subinventario_id');
                });
            } else {
                // Filtrar por sub-inventario específico
                $query->where('subinventario_id', $request->subinventario_id);
            }
        }

        // Ordenar por fecha más reciente
        $query->orderBy('fecha_venta', 'desc');

        return $query;
    }

    /**
     * Construir lista de filtros aplicados (helper privado)
     */
    private function buildFiltersList(Request $request): array
    {
        $filtros = [];
        
        if ($request->filled('cliente_id')) {
            $cliente = \App\Models\Cliente::find($request->cliente_id);
            if ($cliente) {
                $filtros[] = 'Cliente: ' . $cliente->nombre;
            }
        }

        if ($request->filled('libro_id')) {
            $libro = \App\Models\Libro::find($request->libro_id);
            if ($libro) {
                $filtros[] = 'Libro: ' . $libro->nombre;
            }
        }

        if ($request->filled('estado')) {
            $filtros[] = 'Estado: ' . ucfirst($request->estado);
        }

        if ($request->filled('tipo_pago')) {
            $filtros[] = 'Tipo de pago: ' . ucfirst($request->tipo_pago);
        }

        if ($request->filled('estado_pago')) {
            $filtros[] = 'Estado de pago: ' . ucfirst($request->estado_pago);
        }

        if ($request->filled('vencidas') && $request->vencidas == '1') {
            $filtros[] = 'Ventas: Solo vencidas';
        }

        if ($request->filled('subinventario_id')) {
            if ($request->subinventario_id === 'general') {
                $filtros[] = 'Origen: Inventario General';
            } else {
                $subinventario = \App\Models\SubInventario::find($request->subinventario_id);
                if ($subinventario) {
                    $filtros[] = 'Origen: Sub-Inventario #' . $subinventario->id . ' - ' . $subinventario->descripcion;
                }
            }
        }

        if ($request->filled('fecha_desde') && $request->filled('fecha_hasta')) {
            $filtros[] = 'Período: ' . $request->fecha_desde . ' al ' . $request->fecha_hasta;
        } elseif ($request->filled('fecha_desde')) {
            $filtros[] = 'Desde: ' . $request->fecha_desde;
        } elseif ($request->filled('fecha_hasta')) {
            $filtros[] = 'Hasta: ' . $request->fecha_hasta;
        }

        if (empty($filtros)) {
            $filtros[] = 'Sin filtros aplicados - Mostrando todas las ventas';
        }

        return $filtros;
    }

    /**
     * API - Crear una nueva venta desde la app móvil
     */
    /**
     * API - Crear venta desde app móvil
     * Soporta: ventas al contado, a crédito, con cliente, con envío
     */
    public function apiStore(Request $request)
    {
        if (!$this->hasValidMobileRole($request)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes autorización para realizar ventas.'
            ], 403);
        }

        $validated = $request->validate([
            // Datos básicos
            'subinventario_id' => 'required|exists:subinventarios,id',
            'cod_congregante' => 'required|string', // Para validar acceso
            'cliente_id' => 'nullable|exists:clientes,id',
            'fecha_venta' => 'required|date',
            'tipo_pago' => 'nullable|in:contado,credito,mixto',
            'metodo_pago' => 'nullable|in:efectivo,tarjeta,transferencia,no_especificado',
            'descuento_global' => 'nullable|numeric|min:0|max:100',
            'observaciones' => 'nullable|string|max:500',
            'usuario' => 'required|string',
            
            // Envío (opcional)
            'tiene_envio' => 'nullable|boolean',
            'costo_envio' => 'nullable|numeric|min:0',
            'direccion_envio' => 'nullable|string|max:500',
            'telefono_envio' => 'nullable|string|max:20',
            
            // Libros
            'libros' => 'required|array|min:1',
            'libros.*.libro_id' => 'required|exists:libros,id',
            'libros.*.cantidad' => 'required|integer|min:1',
            'libros.*.descuento' => 'nullable|numeric|min:0|max:100',
        ], [
            'subinventario_id.required' => 'Debes seleccionar un punto de venta',
            'cod_congregante.required' => 'Token de usuario requerido',
            'libros.required' => 'Debes agregar al menos un libro',
            'libros.min' => 'Debes agregar al menos un libro',
        ]);

        if (!$this->isAdminFromRequest($request) && $this->payloadHasDiscount($validated)) {
            return response()->json([
                'success' => false,
                'message' => 'Solo Admin Librería o Supervisor pueden aplicar descuentos.'
            ], 403);
        }

        DB::beginTransaction();
        try {
            $codCongreganteValidado = $request->attributes->get('validated_cod_congregante', $validated['cod_congregante']);
            $validated['tipo_pago'] = 'contado';

            // 1. Bloquear y validar stock del subinventario antes de crear la venta.
            $stockLocks = $this->lockAndValidateSubinventarioStock(
                $validated['subinventario_id'],
                $validated['libros']
            );
            $subinventario = $stockLocks['subinventario'];

            // 5. VALIDAR ENVÍO
            $tieneEnvio = isset($validated['tiene_envio']) && $validated['tiene_envio'];
            $costoEnvio = $tieneEnvio ? ($validated['costo_envio'] ?? 0) : 0;

            // 6. CREAR LA VENTA
            $venta = Venta::create([
                'cliente_id' => $validated['cliente_id'] ?? null,
                'fecha_venta' => $validated['fecha_venta'],
                'tipo_pago' => 'contado',
                'metodo_pago' => $validated['metodo_pago'] ?? 'no_especificado',
                'descuento_global' => $validated['descuento_global'] ?? 0,
                'estado' => 'completada',
                'observaciones' => $validated['observaciones'] ?? 'Venta desde app móvil',
                'usuario' => $validated['usuario'],
                'tipo_inventario' => 'subinventario',
                'subinventario_id' => $validated['subinventario_id'],
                'es_a_plazos' => false,
                'tiene_envio' => $tieneEnvio,
                'costo_envio' => $costoEnvio,
                'estado_pago' => 'completado',
                'total_pagado' => 0,
            ]);

            // 7. CREAR MOVIMIENTOS Y ACTUALIZAR STOCK
            foreach ($validated['libros'] as $item) {
                $libro = Libro::findOrFail($item['libro_id']);

                // Crear movimiento de salida
                Movimiento::create([
                    'venta_id' => $venta->id,
                    'libro_id' => $libro->id,
                    'tipo_movimiento' => 'salida',
                    'tipo_salida' => 'venta',
                    'cantidad' => $item['cantidad'],
                    'precio_unitario' => $libro->precio,
                    'descuento' => $item['descuento'] ?? 0,
                    'fecha' => $validated['fecha_venta'],
                    'observaciones' => "Venta #{$venta->id} - App Móvil - SubInv #{$validated['subinventario_id']}",
                    'usuario' => $validated['usuario'],
                ]);

            }

            // 8. CALCULAR TOTALES
            $venta->actualizarTotales();
            
            $venta->total_pagado = $venta->total;
            
            $venta->save();

            // 9. GUARDAR INFORMACIÓN DE ENVÍO EN OBSERVACIONES (simplificado)
            // El sistema de envíos usa relación muchos-a-muchos que se gestiona aparte
            if ($tieneEnvio && isset($validated['direccion_envio'])) {
                $observacionesEnvio = "\n--- DATOS DE ENVÍO ---\n";
                $observacionesEnvio .= "Dirección: " . $validated['direccion_envio'] . "\n";
                $observacionesEnvio .= "Teléfono: " . ($validated['telefono_envio'] ?? 'N/A') . "\n";
                $observacionesEnvio .= "Costo: $" . $costoEnvio;
                
                $venta->observaciones = ($venta->observaciones ?? '') . $observacionesEnvio;
                $venta->save();
            }

            $this->ingresoCajaService->registrarVenta($venta);

            DB::commit();

            \Log::info('Venta creada desde API móvil', [
                'venta_id' => $venta->id,
                'usuario' => $validated['usuario'],
                'cod_congregante' => $codCongreganteValidado,
                'subinventario_id' => $validated['subinventario_id'],
                'total' => $venta->total,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            // 10. PREPARAR RESPUESTA
            return response()->json([
                'success' => true,
                'message' => 'Venta creada exitosamente',
                'data' => [
                    'venta_id' => $venta->id,
                    'subtotal' => $venta->subtotal,
                    'descuento' => $venta->descuento,
                    'costo_envio' => $venta->costo_envio,
                    'total' => $venta->total,
                    'total_pagado' => $venta->total_pagado,
                    'saldo_pendiente' => $venta->total - $venta->total_pagado,
                    'estado_pago' => $venta->estado_pago,
                    'tiene_envio' => $venta->tiene_envio,
                ]
            ], 201);

        } catch (\DomainException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            $requestData = $request->all();
            if (isset($requestData['cod_congregante'])) {
                $requestData['cod_congregante'] = '***';
            }
            if (isset($requestData['password'])) {
                $requestData['password'] = '***';
            }
            \Log::error('Error al crear venta desde API móvil', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $requestData
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la venta: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * API - Listar puntos de venta según rol
     * Vendedor: subinventarios asignados (sin inventario general).
     * Admin Librería y Supervisor: inventario general y todos los subinventarios.
     */
    public function apiPuntosVenta(Request $request)
    {
        if (!$this->hasValidMobileRole($request)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes autorización para acceder a los puntos de venta.'
            ], 403);
        }

        $tieneAccesoTotal = $this->isAdminFromRequest($request);

        if ($tieneAccesoTotal) {
            $inventarioGeneral = [
                'tipo' => 'general',
                'nombre' => 'Inventario General',
                'descripcion' => 'Inventario principal',
                'total_libros' => Libro::where('stock', '>', 0)->count(),
                'total_unidades' => Libro::where('stock', '>', 0)->sum('stock')
            ];

            $subinventarios = SubInventario::where('estado', 'activo')
                ->get(['id', 'descripcion', 'fecha_subinventario', 'estado', 'observaciones'])
                ->map(function ($subinventario) {
                    return $this->formatSubinventarioPuntoVenta($subinventario);
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'inventario_general' => $inventarioGeneral,
                    'subinventarios' => $subinventarios,
                    'total_subinventarios' => $subinventarios->count()
                ]
            ], 200);
        }

        $subinventarios = SubInventario::where('estado', 'activo')
            ->get(['id', 'descripcion', 'fecha_subinventario', 'estado', 'observaciones'])
            ->map(function ($subinventario) {
                return $this->formatSubinventarioPuntoVenta($subinventario);
            });

        return response()->json([
            'success' => true,
            'message' => 'Subinventarios encontrados',
            'data' => [
                'subinventarios' => $subinventarios,
                'total_subinventarios' => $subinventarios->count()
            ]
        ], 200);
    }

    /**
     * API - Listar todos los puntos de venta (Admin Librería)
     * Devuelve inventario general y todos los subinventarios activos
     */
    public function apiAdminPuntosVenta(Request $request)
    {
        if (!$this->isAdmin() && !$this->isAdminFromRequest($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Acceso denegado. Se requiere rol Admin Librería o Supervisor.'
            ], 403);
        }

        $inventarioGeneral = [
            'tipo' => 'general',
            'nombre' => 'Inventario General',
            'descripcion' => 'Inventario principal',
            'total_libros' => Libro::where('stock', '>', 0)->count(),
            'total_unidades' => Libro::where('stock', '>', 0)->sum('stock')
        ];

        $subinventarios = SubInventario::where('estado', 'activo')
            ->get(['id', 'descripcion', 'fecha_subinventario', 'estado', 'observaciones'])
            ->map(function ($subinventario) {
                $stats = DB::table('subinventario_libro')
                    ->where('subinventario_id', $subinventario->id)
                    ->where('cantidad', '>', 0)
                    ->selectRaw('COUNT(DISTINCT libro_id) as total_libros, SUM(cantidad) as total_unidades')
                    ->first();

                return [
                    'id' => $subinventario->id,
                    'descripcion' => $subinventario->descripcion,
                    'fecha_subinventario' => $subinventario->fecha_subinventario,
                    'estado' => $subinventario->estado,
                    'observaciones' => $subinventario->observaciones,
                    'total_libros' => $stats->total_libros ?? 0,
                    'total_unidades' => $stats->total_unidades ?? 0
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'inventario_general' => $inventarioGeneral,
                'subinventarios' => $subinventarios,
                'total_subinventarios' => $subinventarios->count()
            ]
        ], 200);
    }

    /**
     * API - Crear venta desde app móvil (Admin Librería)
     * Permite vender desde inventario general o cualquier subinventario
     */
    public function apiStoreAdmin(Request $request)
    {
        if (!$this->isAdmin() && !$this->isAdminFromRequest($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Acceso denegado. Se requiere rol Admin Librería o Supervisor.'
            ], 403);
        }

        $validated = $request->validate([
            'tipo_inventario' => 'required|in:general,subinventario',
            'subinventario_id' => 'nullable|required_if:tipo_inventario,subinventario|exists:subinventarios,id',
            'cliente_id' => 'nullable|exists:clientes,id',
            'fecha_venta' => 'required|date',
            'tipo_pago' => 'nullable|in:contado,credito,mixto',
            'metodo_pago' => 'nullable|in:efectivo,tarjeta,transferencia,no_especificado',
            'descuento_global' => 'nullable|numeric|min:0|max:100',
            'observaciones' => 'nullable|string|max:500',
            'usuario' => 'required|string',

            'tiene_envio' => 'nullable|boolean',
            'costo_envio' => 'nullable|numeric|min:0',
            'direccion_envio' => 'nullable|string|max:500',
            'telefono_envio' => 'nullable|string|max:20',

            'libros' => 'required|array|min:1',
            'libros.*.libro_id' => 'required|exists:libros,id',
            'libros.*.cantidad' => 'required|integer|min:1',
            'libros.*.descuento' => 'nullable|numeric|min:0|max:100',
        ], [
            'tipo_inventario.required' => 'Debes seleccionar el tipo de inventario',
            'subinventario_id.required_if' => 'Debes seleccionar un subinventario',
            'libros.required' => 'Debes agregar al menos un libro',
            'libros.min' => 'Debes agregar al menos un libro',
        ]);
        DB::beginTransaction();

        try {
            $validated['tipo_pago'] = 'contado';

            $tieneEnvio = isset($validated['tiene_envio']) && $validated['tiene_envio'];
            $costoEnvio = $tieneEnvio ? ($validated['costo_envio'] ?? 0) : 0;

            $tipoInventario = $validated['tipo_inventario'];
            $subinventario = null;

            if ($tipoInventario === 'subinventario') {
                $stockLocks = $this->lockAndValidateSubinventarioStock(
                    $validated['subinventario_id'],
                    $validated['libros']
                );
                $subinventario = $stockLocks['subinventario'];
            } else {
                $this->lockAndValidateGeneralStock($validated['libros']);
            }

            $venta = Venta::create([
                'cliente_id' => $validated['cliente_id'] ?? null,
                'fecha_venta' => $validated['fecha_venta'],
                'tipo_pago' => 'contado',
                'metodo_pago' => $validated['metodo_pago'] ?? 'no_especificado',
                'descuento_global' => $validated['descuento_global'] ?? 0,
                'estado' => 'completada',
                'observaciones' => $validated['observaciones'] ?? 'Venta desde app móvil (Admin)',
                'usuario' => $validated['usuario'],
                'tipo_inventario' => $tipoInventario,
                'subinventario_id' => $tipoInventario === 'subinventario' ? $validated['subinventario_id'] : null,
                'es_a_plazos' => false,
                'tiene_envio' => $tieneEnvio,
                'costo_envio' => $costoEnvio,
                'estado_pago' => 'completado',
                'total_pagado' => 0,
            ]);

            foreach ($validated['libros'] as $item) {
                $libro = Libro::findOrFail($item['libro_id']);

                Movimiento::create([
                    'venta_id' => $venta->id,
                    'libro_id' => $libro->id,
                    'tipo_movimiento' => 'salida',
                    'tipo_salida' => 'venta',
                    'cantidad' => $item['cantidad'],
                    'precio_unitario' => $libro->precio,
                    'descuento' => $item['descuento'] ?? 0,
                    'fecha' => $validated['fecha_venta'],
                    'observaciones' => "Venta #{$venta->id} - App Móvil - " . ($tipoInventario === 'subinventario' ? "SubInv #{$validated['subinventario_id']}" : 'Inventario General'),
                    'usuario' => $validated['usuario'],
                ]);

            }

            $venta->actualizarTotales();

            $venta->total_pagado = $venta->total;

            if ($tieneEnvio && isset($validated['direccion_envio'])) {
                $observacionesEnvio = "\n--- DATOS DE ENVÍO ---\n";
                $observacionesEnvio .= "Dirección: " . $validated['direccion_envio'] . "\n";
                $observacionesEnvio .= "Teléfono: " . ($validated['telefono_envio'] ?? 'N/A') . "\n";
                $observacionesEnvio .= "Costo: $" . $costoEnvio;

                $venta->observaciones = ($venta->observaciones ?? '') . $observacionesEnvio;
            }

            $venta->save();

            $this->ingresoCajaService->registrarVenta($venta);

            DB::commit();

            \Log::info('Venta creada desde API móvil admin', [
                'venta_id' => $venta->id,
                'usuario' => $validated['usuario'],
                'cod_congregante' => $request->attributes->get('validated_cod_congregante'),
                'tipo_inventario' => $tipoInventario,
                'subinventario_id' => $tipoInventario === 'subinventario' ? $validated['subinventario_id'] : null,
                'total' => $venta->total,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Venta creada exitosamente',
                'data' => [
                    'venta_id' => $venta->id,
                    'subtotal' => $venta->subtotal,
                    'descuento' => $venta->descuento,
                    'costo_envio' => $venta->costo_envio,
                    'total' => $venta->total,
                    'total_pagado' => $venta->total_pagado,
                    'saldo_pendiente' => $venta->total - $venta->total_pagado,
                    'estado_pago' => $venta->estado_pago,
                    'tiene_envio' => $venta->tiene_envio,
                ]
            ], 201);

        } catch (\DomainException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            $requestData = $request->all();
            if (isset($requestData['cod_congregante'])) {
                $requestData['cod_congregante'] = '***';
            }
            if (isset($requestData['password'])) {
                $requestData['password'] = '***';
            }
            \Log::error('Error al crear venta desde API admin', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $requestData
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al crear la venta: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Agrupa cantidades por libro para validar el stock real solicitado.
     */
    private function aggregateBookQuantities(array $items): array
    {
        $quantities = [];

        foreach ($items as $item) {
            $libroId = (int) $item['libro_id'];
            $cantidad = (int) $item['cantidad'];

            if ($cantidad <= 0) {
                continue;
            }

            $quantities[$libroId] = ($quantities[$libroId] ?? 0) + $cantidad;
        }

        ksort($quantities);

        return $quantities;
    }

    /**
     * Bloquea el stock del inventario general hasta que termine la transaccion.
     */
    private function lockAndValidateGeneralStock(array $items): array
    {
        $lockedBooks = [];

        foreach ($this->aggregateBookQuantities($items) as $libroId => $cantidad) {
            $libro = Libro::whereKey($libroId)->lockForUpdate()->firstOrFail();

            if ((int) $libro->stock < $cantidad) {
                throw new \DomainException(
                    "Stock insuficiente para '{$libro->nombre}'. Disponible: {$libro->stock}"
                );
            }

            $lockedBooks[$libroId] = $libro;
        }

        return $lockedBooks;
    }

    /**
     * Bloquea el stock del subinventario hasta que termine la transaccion.
     */
    private function lockAndValidateSubinventarioStock(int $subinventarioId, array $items): array
    {
        $subinventario = SubInventario::whereKey($subinventarioId)
            ->lockForUpdate()
            ->firstOrFail();

        if ($subinventario->estado !== 'activo') {
            throw new \DomainException('El subinventario no está activo');
        }

        $lockedBooks = [];
        $lockedPivots = [];

        foreach ($this->aggregateBookQuantities($items) as $libroId => $cantidad) {
            $libro = Libro::whereKey($libroId)->lockForUpdate()->firstOrFail();
            $pivot = DB::table('subinventario_libro')
                ->where('subinventario_id', $subinventarioId)
                ->where('libro_id', $libroId)
                ->lockForUpdate()
                ->first();

            if (!$pivot) {
                throw new \DomainException("El libro '{$libro->nombre}' no está en este subinventario");
            }

            if ((int) $pivot->cantidad < $cantidad) {
                throw new \DomainException(
                    "Cantidad insuficiente para '{$libro->nombre}'. Disponible: {$pivot->cantidad}"
                );
            }

            $lockedBooks[$libroId] = $libro;
            $lockedPivots[$libroId] = $pivot;
        }

        return [
            'subinventario' => $subinventario,
            'libros' => $lockedBooks,
            'pivots' => $lockedPivots,
        ];
    }

    private function formatSubinventarioPuntoVenta(SubInventario $subinventario): array
    {
        $stats = DB::table('subinventario_libro')
            ->where('subinventario_id', $subinventario->id)
            ->where('cantidad', '>', 0)
            ->selectRaw('COUNT(DISTINCT libro_id) as total_libros, SUM(cantidad) as total_unidades')
            ->first();

        return [
            'id' => $subinventario->id,
            'descripcion' => $subinventario->descripcion,
            'fecha_subinventario' => $subinventario->fecha_subinventario,
            'estado' => $subinventario->estado,
            'observaciones' => $subinventario->observaciones,
            'total_libros' => $stats->total_libros ?? 0,
            'total_unidades' => $stats->total_unidades ?? 0
        ];
    }

}
