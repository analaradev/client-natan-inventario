<?php

namespace App\Http\Controllers;

use App\Models\SubInventario;
use App\Models\Libro;
use App\Services\ExcelReportService;
use App\Services\PdfReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class SubInventarioController extends Controller
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
        $query = SubInventario::with('libros');

        // Filtro por estado
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        // Filtro por fecha
        if ($request->filled('fecha')) {
            $query->porFecha($request->fecha);
        }

        // Búsqueda por descripción
        if ($request->filled('search')) {
            $query->where('descripcion', 'like', '%' . $request->search . '%');
        }

        // Ordenar
        $ordenar = $request->get('ordenar', 'reciente');
        switch ($ordenar) {
            case 'antiguo':
                $query->orderBy('fecha_subinventario', 'asc');
                break;
            case 'fecha_asc':
                $query->orderBy('fecha_subinventario', 'asc');
                break;
            case 'fecha_desc':
                $query->orderBy('fecha_subinventario', 'desc');
                break;
            default: // reciente
                $query->orderBy('created_at', 'desc');
                break;
        }

        $subinventarios = $query->paginate(15)->withQueryString();

        return view('subinventarios.index', compact('subinventarios'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // Solo mostrar libros con stock disponible
        $libros = Libro::where('stock', '>', 0)
            ->orderBy('nombre')
            ->get();

        return view('subinventarios.create', compact('libros'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'fecha_subinventario' => 'required|date',
            'descripcion' => 'nullable|string|max:255',
            'observaciones' => 'nullable|string|max:500',
            
            // Libros
            'libros' => 'required|array|min:1',
            'libros.*.libro_id' => 'required|exists:libros,id',
            'libros.*.cantidad' => 'required|integer|min:1',
        ], [
            'fecha_subinventario.required' => 'La fecha del sub-inventario es obligatoria',
            'libros.required' => 'Debes agregar al menos un libro al sub-inventario',
            'libros.min' => 'Debes agregar al menos un libro al sub-inventario',
        ]);

        DB::beginTransaction();
        try {
            // Validar stock de todos los libros primero (ahora stock representa el inventario general directamente)
            foreach ($validated['libros'] as $item) {
                $libro = Libro::findOrFail($item['libro_id']);
                
                if ($libro->stock < $item['cantidad']) {
                    return back()->withErrors([
                        'error' => "Stock en inventario general insuficiente para '{$libro->nombre}'. Stock disponible: {$libro->stock}"
                    ])->withInput();
                }
            }

            // Crear el sub-inventario
            $subinventario = SubInventario::create([
                'fecha_subinventario' => $validated['fecha_subinventario'],
                'descripcion' => $validated['descripcion'],
                'estado' => 'activo',
                'observaciones' => $validated['observaciones'],
                'usuario' => 'Admin', // Cambiar por auth()->user()->name
            ]);

            // Asociar los libros con sus cantidades
            foreach ($validated['libros'] as $item) {
                $subinventario->libros()->attach($item['libro_id'], [
                    'cantidad' => $item['cantidad']
                ]);

                // Restar del inventario general e incrementar contador de subinventarios
                $libro = Libro::findOrFail($item['libro_id']);
                $libro->decrement('stock', $item['cantidad']);
                $libro->increment('stock_subinventario', $item['cantidad']);
            }

            DB::commit();

            return redirect()->route('subinventarios.show', $subinventario)
                ->with('success', 'Sub-inventario creado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Error al crear el sub-inventario: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(SubInventario $subinventario)
    {
        // Obtener libros paginados (15 por página)
        $libros = $subinventario->libros()->paginate(15);
        
        return view('subinventarios.show', compact('subinventario', 'libros'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(SubInventario $subinventario)
    {
        // Solo permitir editar sub-inventarios activos
        if ($subinventario->estado !== 'activo') {
            return redirect()->route('subinventarios.index')
                ->with('warning', 'Solo se pueden editar sub-inventarios activos');
        }

        // Cargar la relación de libros si no está cargada
        $subinventario->load('libros');

        // Obtener todos los libros y calcular stock disponible para este subinventario
        $libros = Libro::orderBy('nombre')->get()->map(function($libro) use ($subinventario) {
            // Buscar si este libro ya está en este subinventario
            $cantidadEnEsteSubinv = $subinventario->libros->where('id', $libro->id)->first()?->pivot->cantidad ?? 0;
            
            // Stock disponible para asignar = stock en inventario general + lo que ya tiene este subinventario
            // Esto permite que al editar puedas aumentar/reducir la cantidad
            $libro->stock_disponible_edicion = $libro->stock + $cantidadEnEsteSubinv;
            
            return $libro;
        });

        return view('subinventarios.edit', compact('subinventario', 'libros'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, SubInventario $subinventario)
    {
        // Solo permitir actualizar sub-inventarios activos
        if ($subinventario->estado !== 'activo') {
            return redirect()->route('subinventarios.index')
                ->with('warning', 'Solo se pueden editar sub-inventarios activos');
        }

        // Cargar la relación de libros para las validaciones
        $subinventario->load('libros');

        $validated = $request->validate([
            'fecha_subinventario' => 'required|date',
            'descripcion' => 'nullable|string|max:255',
            'observaciones' => 'nullable|string|max:500',
            
            // Libros
            'libros' => 'required|array|min:1',
            'libros.*.libro_id' => 'required|exists:libros,id',
            'libros.*.cantidad' => 'required|integer|min:0',
        ]);

        // Validar que no haya libros duplicados
        $libroIds = collect($validated['libros'])->pluck('libro_id');
        if ($libroIds->count() !== $libroIds->unique()->count()) {
            return back()->withErrors([
                'error' => 'No puedes agregar el mismo libro más de una vez. Por favor, verifica la lista.'
            ])->withInput();
        }

        DB::beginTransaction();
        try {
            // Validar stock ANTES de hacer cambios, considerando lo que ya tiene este subinventario
            foreach ($validated['libros'] as $item) {
                $libro = Libro::findOrFail($item['libro_id']);
                
                // Cantidad actual de este libro en este subinventario
                $cantidadActualEnSub = $subinventario->libros->where('id', $item['libro_id'])->first()?->pivot->cantidad ?? 0;
                
                // Stock disponible = stock en inventario general + lo que ya tiene este subinventario
                $stockDisponible = $libro->stock + $cantidadActualEnSub;
                
                if ($stockDisponible < $item['cantidad']) {
                    DB::rollBack();
                    return back()->withErrors([
                        'error' => "Stock disponible insuficiente para '{$libro->nombre}'. Stock disponible: {$stockDisponible}"
                    ])->withInput();
                }
            }
            
            // Primero devolver el stock de los libros actuales al inventario general
            foreach ($subinventario->libros as $libro) {
                $libro->increment('stock', $libro->pivot->cantidad);
                $libro->decrement('stock_subinventario', $libro->pivot->cantidad);
            }

            // Actualizar el sub-inventario
            $subinventario->update([
                'fecha_subinventario' => $validated['fecha_subinventario'],
                'descripcion' => $validated['descripcion'],
                'observaciones' => $validated['observaciones'],
            ]);

            // Desasociar libros anteriores
            $subinventario->libros()->detach();

            // Asociar los nuevos libros con sus cantidades
            foreach ($validated['libros'] as $item) {
                $subinventario->libros()->attach($item['libro_id'], [
                    'cantidad' => $item['cantidad']
                ]);

                // Restar del inventario general e incrementar contador de subinventarios
                $libro = Libro::findOrFail($item['libro_id']);
                $libro->decrement('stock', $item['cantidad']);
                $libro->increment('stock_subinventario', $item['cantidad']);
            }

            DB::commit();

            return redirect()->route('subinventarios.show', $subinventario)
                ->with('success', 'Sub-inventario actualizado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Error al actualizar el sub-inventario: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SubInventario $subinventario)
    {
        // No permitir eliminar sub-inventarios completados
        if ($subinventario->estado === 'completado') {
            return back()->with('error', 'No se pueden eliminar sub-inventarios completados');
        }

        DB::beginTransaction();
        try {
            // Si está activo, devolver el stock al inventario general
            if ($subinventario->estado === 'activo') {
                foreach ($subinventario->libros as $libro) {
                    $libro->increment('stock', $libro->pivot->cantidad);
                    $libro->decrement('stock_subinventario', $libro->pivot->cantidad);
                }
            }

            $subinventario->delete();
            DB::commit();

            return redirect()->route('subinventarios.index')
                ->with('success', 'Sub-inventario eliminado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error al eliminar el sub-inventario: ' . $e->getMessage());
        }
    }

    /**
     * Completar un sub-inventario
     */
    public function completar(SubInventario $subinventario)
    {
        if ($subinventario->estado !== 'activo') {
            return back()->with('warning', 'Este sub-inventario no está activo');
        }

        DB::beginTransaction();
        try {
            // Solo reducir el contador de stock en subinventarios
            // (el stock general ya se restó cuando se creó el subinventario)
            foreach ($subinventario->libros as $libro) {
                $libro->decrement('stock_subinventario', $libro->pivot->cantidad);
            }

            $subinventario->update(['estado' => 'completado']);
            DB::commit();

            return back()->with('success', 'Sub-inventario completado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error al completar el sub-inventario: ' . $e->getMessage());
        }
    }

    /**
     * Cancelar un sub-inventario (devolver inventario)
     */
    public function cancelar(SubInventario $subinventario)
    {
        if ($subinventario->estado === 'cancelado') {
            return back()->with('warning', 'Este sub-inventario ya está cancelado');
        }

        DB::beginTransaction();
        try {
            // Si estaba activo, devolver el stock del sub-inventario
            if ($subinventario->estado === 'activo') {
                foreach ($subinventario->libros as $libro) {
                    $libro->decrement('stock_subinventario', $libro->pivot->cantidad); // Cambiaremos este nombre después
                }
            }

            $subinventario->update(['estado' => 'cancelado']);
            DB::commit();

            return back()->with('success', 'Sub-inventario cancelado exitosamente. Stock devuelto al inventario.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error al cancelar el sub-inventario: ' . $e->getMessage());
        }
    }

    /**
     * Devolver parcialmente libros del sub-inventario
     */
    public function devolverParcial(Request $request, SubInventario $subinventario)
    {
        if ($subinventario->estado !== 'activo') {
            return back()->with('warning', 'Este sub-inventario no está activo');
        }

        $validated = $request->validate([
            'libro_id' => 'required|exists:libros,id',
            'cantidad' => 'required|integer|min:1',
        ]);

        DB::beginTransaction();
        try {
            $libro = $subinventario->libros()->where('libro_id', $validated['libro_id'])->first();
            
            if (!$libro) {
                return back()->with('error', 'Este libro no está en el sub-inventario');
            }

            if ($validated['cantidad'] > $libro->pivot->cantidad) {
                return back()->with('error', 'La cantidad a devolver es mayor a la cantidad en sub-inventario');
            }

            // Devolver al stock general
            $libroModel = Libro::findOrFail($validated['libro_id']);
            $libroModel->increment('stock', $validated['cantidad']);
            $libroModel->decrement('stock_subinventario', $validated['cantidad']);

            // Actualizar la cantidad a 0 en la tabla pivote (mantener el libro en la lista)
            $subinventario->libros()->updateExistingPivot($validated['libro_id'], [
                'cantidad' => 0
            ]);

            DB::commit();

            return back()->with('success', 'Stock devuelto exitosamente. La cantidad se ha puesto a 0.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error al devolver el stock: ' . $e->getMessage());
        }
    }

    /**
     * API - Obtener lista de subinventarios
     */
    public function apiIndex(Request $request)
    {
        $query = SubInventario::with('libros:id,nombre,codigo_barras');

        // Filtro por estado
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        // Filtro por fecha
        if ($request->filled('fecha')) {
            $query->whereDate('fecha_subinventario', $request->fecha);
        }

        // Búsqueda por descripción
        if ($request->filled('search')) {
            $query->where('descripcion', 'like', '%' . $request->search . '%');
        }

        // Ordenar
        $ordenar = $request->get('ordenar', 'reciente');
        switch ($ordenar) {
            case 'antiguo':
                $query->orderBy('fecha_subinventario', 'asc');
                break;
            case 'fecha_asc':
                $query->orderBy('fecha_subinventario', 'asc');
                break;
            case 'fecha_desc':
                $query->orderBy('fecha_subinventario', 'desc');
                break;
            default: // reciente
                $query->orderBy('created_at', 'desc');
                break;
        }

        $subinventarios = $query->paginate($request->get('per_page', 15));

        return response()->json($subinventarios);
    }

    /**
     * API - Obtener subinventarios asignados a un usuario específico (sin libros)
     */
    public function apiMisSubinventarios(Request $request, $codCongregante)
    {
        $esAdmin = $this->hasAdminAccess($request);

        if ($esAdmin) {
            // Admin Librería y Supervisor: Todos los subinventarios + Inventario General
            $subinventarios = SubInventario::where('estado', 'activo')
                ->get(['id', 'descripcion', 'fecha_subinventario', 'estado', 'observaciones'])
                ->map(function($subinventario) {
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

            // Agregar el Inventario General (ID 0) al inicio
            $totalLibrosGeneral = Libro::where('stock', '>', 0)->count();
            $totalUnidadesGeneral = Libro::where('stock', '>', 0)->sum('stock');

            $inventarioGeneral = [
                'tipo' => 'general',
                'nombre' => 'Inventario General',
                'descripcion' => 'Inventario principal',
                'total_libros' => $totalLibrosGeneral,
                'total_unidades' => $totalUnidadesGeneral
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'inventario_general' => $inventarioGeneral,
                    'subinventarios' => $subinventarios,
                    'total_subinventarios' => $subinventarios->count()
                ]
            ], 200);
        }

        // Vendedores: Todos los subinventarios activos (sin inventario general)
        $subinventarios = SubInventario::where('estado', 'activo')
            ->get(['id', 'descripcion', 'fecha_subinventario', 'estado', 'observaciones'])
            ->map(function($subinventario) {
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
            'message' => 'Subinventarios encontrados',
            'data' => $subinventarios
        ], 200);
    }

    /**
     * API - Obtener libros disponibles de un subinventario específico
     */
    public function apiLibrosSubinventario(Request $request, $id)
    {
        // Log para debugging
        \Log::info('apiLibrosSubinventario llamada', [
            'id' => $id,
            'cod_congregante' => $request->cod_congregante,
            'hasAdminAccess' => $this->hasAdminAccess($request),
            'roles_request' => $request->query('roles'),
        ]);

        $esAdmin = $this->hasAdminAccess($request);

        // ID 0 = Inventario General (todos los libros del sistema)
        if ($id == 0) {
            // Solo Admin Librería y Supervisor pueden ver el inventario general
            if (!$esAdmin) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes acceso al inventario general. Solo Admin Librería y Supervisor.'
                ], 403);
            }

            // Obtener TODOS los libros con stock disponible
            $libros = Libro::where('stock', '>', 0)
                ->orderBy('nombre')
                ->get()
                ->map(function($libro) {
                    return [
                        'id' => $libro->id,
                        'nombre' => $libro->nombre,
                        'codigo_barras' => $libro->codigo_barras,
                        'precio' => $libro->precio,
                        'stock_general' => $libro->stock,
                        'cantidad_disponible' => $libro->stock
                    ];
                });

            return response()->json([
                'success' => true,
                'message' => 'Inventario general encontrado',
                'data' => [
                    'subinventario' => [
                        'id' => 0,
                        'descripcion' => 'Inventario General',
                        'fecha_subinventario' => now(),
                        'estado' => 'activo'
                    ],
                    'total_libros' => $libros->count(),
                    'total_unidades' => $libros->sum('cantidad_disponible'),
                    'libros' => $libros
                ]
            ], 200);
        }

        // Verificar que el subinventario existe y está activo
        $subinventario = SubInventario::where('id', $id)
            ->where('estado', 'activo')
            ->first();
        
        if (!$subinventario) {
            return response()->json([
                'success' => false,
                'message' => 'Subinventario no encontrado o no activo'
            ], 404);
        }
        

        
        // Obtener libros con stock disponible
        $libros = $subinventario->libros()
            ->wherePivot('cantidad', '>', 0)
            ->get()
            ->map(function($libro) {
                return [
                    'id' => $libro->id,
                    'nombre' => $libro->nombre,
                    'codigo_barras' => $libro->codigo_barras,
                    'precio' => $libro->precio,
                    'stock_general' => $libro->stock,
                    'cantidad_disponible' => $libro->pivot->cantidad
                ];
            });
        
        return response()->json([
            'success' => true,
            'message' => 'Libros encontrados',
            'data' => [
                'subinventario' => [
                    'id' => $subinventario->id,
                    'descripcion' => $subinventario->descripcion,
                    'fecha_subinventario' => $subinventario->fecha_subinventario,
                    'estado' => $subinventario->estado
                ],
                'total_libros' => $libros->count(),
                'total_unidades' => $libros->sum('cantidad_disponible'),
                'libros' => $libros
            ]
        ], 200);
    }

    /**
     * API - Obtener todos los libros disponibles para vender
     * Incluye libros de todos los subinventarios activos con filtros y búsqueda
     */
    public function apiMisLibrosDisponibles(Request $request, $codCongregante)
    {
        // 1. Obtener IDs de todos los subinventarios activos (para todos los usuarios)
        $subinventariosIds = SubInventario::where('estado', 'activo')->pluck('id');

        if ($subinventariosIds->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'No hay subinventarios activos para consultar.',
                'data' => []
            ], 200);
        }

        // 2. Construir query base - libros en subinventarios activos del vendedor
        $query = DB::table('libros')
            ->join('subinventario_libro', 'libros.id', '=', 'subinventario_libro.libro_id')
            ->join('subinventarios', 'subinventario_libro.subinventario_id', '=', 'subinventarios.id')
            ->whereIn('subinventarios.id', $subinventariosIds)
            ->where('subinventarios.estado', 'activo')
            ->where('subinventario_libro.cantidad', '>', 0);

        // 3. Aplicar filtros opcionales
        
        // Filtro: Buscar por nombre
        if ($request->filled('buscar')) {
            $query->where('libros.nombre', 'like', '%' . $request->buscar . '%');
        }

        // Filtro: Precio mínimo
        if ($request->filled('precio_min')) {
            $query->where('libros.precio', '>=', $request->precio_min);
        }

        // Filtro: Precio máximo
        if ($request->filled('precio_max')) {
            $query->where('libros.precio', '<=', $request->precio_max);
        }

        // Filtro: Subinventario específico
        if ($request->filled('subinventario_id')) {
            $query->where('subinventarios.id', $request->subinventario_id);
        }

        // 4. Agrupar por libro y sumar cantidades de todos los subinventarios
        $query->select(
            'libros.id',
            'libros.nombre',
            'libros.codigo_barras',
            'libros.precio',
            DB::raw('SUM(subinventario_libro.cantidad) as cantidad_total_disponible'),
            DB::raw('GROUP_CONCAT(DISTINCT CONCAT(subinventarios.id, ":", subinventario_libro.cantidad) SEPARATOR "|") as subinventarios_detalle')
        )
        ->groupBy('libros.id', 'libros.nombre', 'libros.codigo_barras', 'libros.precio');

        // 5. Ordenamiento
        $orderBy = $request->get('ordenar', 'nombre');
        $orderDirection = $request->get('direccion', 'asc');
        
        $allowedOrderBy = ['nombre', 'precio', 'cantidad_total_disponible'];
        if (in_array($orderBy, $allowedOrderBy)) {
            if ($orderBy === 'cantidad_total_disponible') {
                $query->orderByRaw('cantidad_total_disponible ' . $orderDirection);
            } else {
                $query->orderBy('libros.' . $orderBy, $orderDirection);
            }
        }

        // 6. Ejecutar query y obtener resultados
        $libros = $query->get();

        // 7. Formatear resultados con detalle de subinventarios
        $librosFormateados = $libros->map(function($libro) use ($subinventariosIds) {
            // Parsear detalle de subinventarios
            $subinventariosDetalle = [];
            if ($libro->subinventarios_detalle) {
                $detalles = explode('|', $libro->subinventarios_detalle);
                foreach ($detalles as $detalle) {
                    list($subId, $cantidad) = explode(':', $detalle);
                    $subinventariosDetalle[] = [
                        'subinventario_id' => (int)$subId,
                        'cantidad' => (int)$cantidad
                    ];
                }
            }

            return [
                'id' => $libro->id,
                'nombre' => $libro->nombre,
                'codigo_barras' => $libro->codigo_barras,
                'precio' => $libro->precio,
                'cantidad_total_disponible' => (int)$libro->cantidad_total_disponible,
                'subinventarios' => $subinventariosDetalle,
                'puede_vender' => true // Este vendedor SÍ puede vender este libro
            ];
        });

        // 8. Información adicional del vendedor
        $totalLibrosUnicos = $librosFormateados->count();
        $totalUnidades = $librosFormateados->sum('cantidad_total_disponible');
        
        // Obtener nombres de subinventarios
        $subinventariosInfo = SubInventario::whereIn('id', $subinventariosIds)
            ->where('estado', 'activo')
            ->get(['id', 'descripcion', 'fecha_subinventario'])
            ->map(function($sub) {
                return [
                    'id' => $sub->id,
                    'descripcion' => $sub->descripcion ?? 'Sin descripción',
                    'fecha' => $sub->fecha_subinventario->format('Y-m-d')
                ];
            });

        return response()->json([
            'success' => true,
            'message' => 'Libros disponibles para vender',
            'data' => [
                'vendedor' => [
                    'cod_congregante' => $codCongregante,
                    'total_subinventarios' => $subinventariosInfo->count(),
                    'subinventarios' => $subinventariosInfo
                ],
                'resumen' => [
                    'total_libros_unicos' => $totalLibrosUnicos,
                    'total_unidades' => $totalUnidades
                ],
                'libros' => $librosFormateados->values()
            ]
        ], 200);
    }

    /**
     * Determina si el request tiene acceso admin/supervisor
     */
    private function hasAdminAccess(Request $request): bool
    {
        // 1. Verificar sesión (para web)
        $rolesSesion = session('roles', []);
        if ($this->hasAdminRole($rolesSesion)) {
            return true;
        }

        // 2. Verificar request (query params, body o headers)
        $rolesRequest = $this->getRolesFromRequest($request);
        if ($this->hasAdminRole($rolesRequest)) {
            return true;
        }

        // 3. FALLBACK: Si envió cod_congregante pero no roles, buscar en API externa
        if ($request->filled('cod_congregante')) {
            $roles = $this->getRolesFromAPI($request->cod_congregante);
            if ($this->hasAdminRole($roles)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Obtener roles del congregante desde la API externa
     */
    private function getRolesFromAPI(string $codCongregante): array
    {
        try {
            $response = \Illuminate\Support\Facades\Http::timeout(3)->get(
                'https://www.sistemasdevida.com/pan/rest2/index.php/app/roles/' . $codCongregante
            );

            if ($response->successful()) {
                $data = $response->json();
                return $data['roles'] ?? [];
            }
        } catch (\Exception $e) {
            \Log::warning('No se pudieron obtener roles de API externa', [
                'cod_congregante' => $codCongregante,
                'error' => $e->getMessage()
            ]);
        }

        return [];
    }

    /**
     * Extrae roles desde request body o header
     */
    private function getRolesFromRequest(Request $request): array
    {
        // Intentar desde query params primero (para GET requests desde móvil)
        $roles = $request->query('roles', null);
        
        // Si no está en query, buscar en body
        if (!$roles) {
            $roles = $request->input('roles', null);
        }

        if (is_string($roles)) {
            $roles = json_decode($roles, true);
        }

        if (!is_array($roles)) {
            $headerRoles = $request->header('X-Roles');
            if (is_string($headerRoles)) {
                $roles = json_decode($headerRoles, true);
            }
        }

        return is_array($roles) ? $roles : [];
    }

    /**
     * Valida rol admin/supervisor dentro de un arreglo de roles
     */
    private function hasAdminRole(array $roles): bool
    {
        if (empty($roles)) {
            return false;
        }

        foreach ($roles as $rol) {
            $rolNombre = '';
            $rolId = null;

            if (is_array($rol)) {
                $rolNombre = strtoupper(trim($rol['ROL'] ?? $rol['rol'] ?? ''));
                $rolId = $rol['ID'] ?? $rol['id'] ?? $rol['ROL_ID'] ?? $rol['rol_id'] ?? $rol['CODROL'] ?? $rol['codrol'] ?? null;
            } else {
                $rolNombre = strtoupper(trim((string) $rol));
            }

            $rolNombre = $this->normalizeRoleName($rolNombre);

            // Admin Librería (19) y Supervisor (20) tienen acceso total.
            if ($rolNombre === 'ADMIN LIBRERIA' || $rolNombre === 'SUPERVISOR') {
                return true;
            }

            // Verificar por ID (Admin Librería = 19, Supervisor = 20)
            if (
                (string) $rolId === '19' ||
                (string) $rolId === '20' ||
                $rolNombre === '19' ||
                $rolNombre === '20'
            ) {
                return true;
            }
        }

        return false;
    }

    private function normalizeRoleName(string $roleName): string
    {
        $roleName = strtoupper(trim($roleName));

        return strtr($roleName, [
            'Á' => 'A',
            'É' => 'E',
            'Í' => 'I',
            'Ó' => 'O',
            'Ú' => 'U',
            'á' => 'A',
            'é' => 'E',
            'í' => 'I',
            'ó' => 'O',
            'ú' => 'U',
        ]);
    }

    /**
     * Exportar sub-inventarios filtrados a Excel
     */
    public function exportExcel(Request $request)
    {
        $query = $this->buildFilteredQuery($request);
        $subinventarios = $query->get();
        
        // Crear spreadsheet usando el servicio
        $spreadsheet = $this->excelReportService->createSpreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Título
        $row = $this->excelReportService->setTitle($sheet, 'REPORTE DE SUB-INVENTARIOS', 'F', 1);
        $row++; // Espacio
        
        // Filtros aplicados
        $filtros = $this->buildFiltersList($request);
        $row = $this->excelReportService->setFilters($sheet, $filtros, $row);
        
        // Encabezados de tabla
        $headers = ['ID', 'Fecha Sub-Inventario', 'Descripción', 'Libros', 'Unidades Totales', 'Estado'];
        $row = $this->excelReportService->setTableHeaders($sheet, $headers, $row);
        
        // Datos
        $data = [];
        foreach ($subinventarios as $subinventario) {
            $data[] = [
                $subinventario->id,
                $subinventario->fecha_subinventario->format('d/m/Y'),
                $subinventario->descripcion,
                $subinventario->libros->count(),
                $subinventario->total_unidades,
                $this->getEstadoLabel($subinventario->estado),
            ];
        }
        
        $lastRow = $this->excelReportService->fillData($sheet, $data, $row);
        
        // Auto ajustar columnas
        $this->excelReportService->autoSizeColumns($sheet, ['A', 'B', 'C', 'D', 'E', 'F']);
        
        // Descargar
        $filename = $this->excelReportService->generateFilename('reporte_subinventarios');
        $this->excelReportService->download($spreadsheet, $filename);
    }

    /**
     * Exportar sub-inventarios filtrados a PDF
     */
    public function exportPdf(Request $request)
    {
        $query = $this->buildFilteredQuery($request);
        $subinventarios = $query->get();
        
        // Preparar filtros usando el helper
        $filtros = $this->buildFiltersList($request);
        
        // Obtener estilos base del servicio
        $styles = $this->pdfReportService->getBaseStyles();
        
        // Generar PDF usando el servicio
        $filename = $this->pdfReportService->generateFilename('reporte_subinventarios');
        
        return $this->pdfReportService->generate(
            'subinventarios.pdf-report',
            compact('subinventarios', 'filtros', 'styles'),
            $filename
        );
    }

    /**
     * Construir query con filtros aplicados
     */
    private function buildFilteredQuery(Request $request)
    {
        $query = SubInventario::with('libros');

        // Filtro por estado
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        // Filtro por fecha
        if ($request->filled('fecha')) {
            $query->porFecha($request->fecha);
        }

        // Búsqueda por descripción
        if ($request->filled('search')) {
            $query->where('descripcion', 'like', '%' . $request->search . '%');
        }

        // Ordenar
        $ordenar = $request->get('ordenar', 'reciente');
        switch ($ordenar) {
            case 'antiguo':
                $query->orderBy('fecha_subinventario', 'asc');
                break;
            case 'fecha_asc':
                $query->orderBy('fecha_subinventario', 'asc');
                break;
            case 'fecha_desc':
                $query->orderBy('fecha_subinventario', 'desc');
                break;
            default: // reciente
                $query->orderBy('created_at', 'desc');
                break;
        }

        return $query;
    }

    /**
     * Construir lista de filtros aplicados
     */
    private function buildFiltersList(Request $request): array
    {
        $filtros = [];
        
        if ($request->filled('search')) {
            $filtros[] = 'Búsqueda: ' . $request->search;
        }
        
        if ($request->filled('estado')) {
            $estadoLabels = [
                'activo' => 'Estado: Activo',
                'completado' => 'Estado: Completado',
                'cancelado' => 'Estado: Cancelado'
            ];
            $filtros[] = $estadoLabels[$request->estado] ?? 'Estado: ' . $request->estado;
        }
        
        if ($request->filled('fecha')) {
            $filtros[] = 'Fecha: ' . date('d/m/Y', strtotime($request->fecha));
        }
        
        if ($request->filled('ordenar')) {
            $ordenarLabels = [
                'reciente' => 'Orden: Más reciente',
                'antiguo' => 'Orden: Más antiguo',
                'fecha_asc' => 'Orden: Fecha ascendente',
                'fecha_desc' => 'Orden: Fecha descendente'
            ];
            $filtros[] = $ordenarLabels[$request->ordenar] ?? 'Orden: ' . $request->ordenar;
        }
        
        if (empty($filtros)) {
            $filtros[] = 'Sin filtros aplicados';
        }
        
        return $filtros;
    }

    /**
     * Helper para obtener etiqueta de estado
     */
    private function getEstadoLabel($estado): string
    {
        return match($estado) {
            'activo' => 'Activo',
            'completado' => 'Completado',
            'cancelado' => 'Cancelado',
            default => $estado
        };
    }

    /**
     * Buscar congregantes desde la API externa (AJAX)
     */
    public function buscarCongregantes(Request $request)
    {
        $codCongregante = session('codCongregante');
        $termino = $request->get('termino', '');
        
        Log::info('Búsqueda de congregantes iniciada', [
            'termino' => $termino,
            'codCongregante' => $codCongregante ? 'presente' : 'ausente',
            'usuario_auth' => auth()->user() ? 'presente' : 'ausente'
        ]);
        
        // Verificar sesión - Primero intenta con la sesión, luego con auth
        if (!$codCongregante && auth()->check()) {
            // Si el usuario está autenticado pero no hay sesión de codCongregante,
            // asignamos un placeholder para intentar la búsqueda
            $codCongregante = auth()->user()->email ?? 'GUEST_USER';
            Log::info('Usando código del usuario autenticado: ' . $codCongregante);
        }
        
        if (!$codCongregante) {
            Log::warning('No se encontró codCongregante en sesión ni usuario autenticado');
            return response()->json([
                'error' => true,
                'message' => 'Sesión no válida. Por favor, inicia sesión nuevamente.'
            ], 401);
        }
        
        if (empty($termino) || strlen(trim($termino)) < 2) {
            Log::info('Término de búsqueda muy corto', ['termino_length' => strlen($termino), 'termino' => $termino]);
            return response()->json([
                'error' => false,
                'congregantes' => [],
                'message' => 'Por favor ingresa al menos 2 caracteres'
            ]);
        }
        
        // Sanitizar el término
        $termino = trim($termino);

        try {
            $url = 'https://www.sistemasdevida.com/pan/rest2/index.php/congregante/buscar_paginado';
            $params = [
                'codCongregante' => $codCongregante,
                'termino' => $termino,
                'pagina' => 0
            ];
            
            Log::info('Llamando a API externa', [
                'url' => $url,
                'params' => $params
            ]);
            
            $response = \Illuminate\Support\Facades\Http::timeout(10)->post($url, $params);

            if ($response->successful()) {
                $data = $response->json();
                
                Log::info('Respuesta de API externa', [
                    'error' => $data['error'] ?? 'no definido',
                    'total_congregantes' => count($data['congregantes'] ?? [])
                ]);
                
                if (!$data['error']) {
                    $congregantes = collect($data['congregantes'] ?? [])
                        ->map(function($congregante) {
                            return [
                                'cod_congregante' => $congregante['CODCONGREGANTE'] ?? $congregante['codCongregante'] ?? '',
                                'nombre_completo' => trim(
                                    $congregante['NOMBREF'] ?? 
                                    (($congregante['NOMBRE'] ?? '') . ' ' . ($congregante['APELLIDOS'] ?? ''))
                                ),
                                'ciudad' => $congregante['CIUDAD'] ?? '',
                                'celular' => $congregante['CEL'] ?? '',
                            ];
                        })
                        ->filter(function($congregante) {
                            return !empty($congregante['nombre_completo']) && !empty($congregante['cod_congregante']);
                        })
                        ->take(10) // Limitar a 10 resultados
                        ->values();
                    
                    Log::info('Congregantes encontrados: ' . $congregantes->count());
                    
                    return response()->json([
                        'error' => false,
                        'congregantes' => $congregantes
                    ]);
                } else {
                    $errorMsg = $data['message'] ?? 'Error en la API externa';
                    Log::error('Error de API externa: ' . $errorMsg);
                    return response()->json([
                        'error' => true,
                        'message' => $errorMsg
                    ], 400);
                }
            }

            Log::error('API externa respondió con error', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return response()->json([
                'error' => true,
                'message' => 'La API externa no respondió correctamente (Status: ' . $response->status() . ')'
            ], 500);

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Error de conexión con API externa', [
                'message' => $e->getMessage()
            ]);
            
            return response()->json([
                'error' => true,
                'message' => 'Error de conexión: No se puede conectar con el servidor de búsqueda'
            ], 500);
            
        } catch (\Illuminate\Http\Client\RequestException $e) {
            Log::error('Excepción de solicitud', [
                'message' => $e->getMessage(),
                'response' => $e->response ? $e->response->body() : 'sin respuesta'
            ]);
            
            return response()->json([
                'error' => true,
                'message' => 'Error en la solicitud: ' . $e->getMessage()
            ], 500);
            
        } catch (\Exception $e) {
            Log::error('Excepción al buscar congregantes', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => true,
                'message' => 'Error al conectar con el servidor: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * API TESTEO - Listar TODOS los libros con información de vendibilidad
     * Indica si cada libro puede ser vendido según el subinventario seleccionado
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function apiTestListarTodosLibros(Request $request)
    {
        // Validar parámetros
        $validated = $request->validate([
            'cod_congregante' => 'nullable|string|max:50',
            'subinventario_id' => 'nullable|integer|exists:subinventarios,id',
            'buscar' => 'nullable|string|max:255',
            'con_stock' => 'nullable|boolean',
            'precio_min' => 'nullable|numeric|min:0',
            'precio_max' => 'nullable|numeric|min:0',
            'ordenar' => 'nullable|string|in:nombre,precio,stock,created_at',
            'direccion' => 'nullable|string|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100'
        ]);

        

        // Query base para obtener todos los libros
        $query = Libro::query();

        // Aplicar filtros de búsqueda
        if ($request->filled('buscar')) {
            $query->where('nombre', 'like', '%' . $validated['buscar'] . '%');
        }

        if ($request->filled('con_stock')) {
            if ($validated['con_stock']) {
                $query->where('stock', '>', 0);
            }
        }

        if ($request->filled('precio_min')) {
            $query->where('precio', '>=', $validated['precio_min']);
        }

        if ($request->filled('precio_max')) {
            $query->where('precio', '<=', $validated['precio_max']);
        }

        // Ordenamiento
        $orderBy = $validated['ordenar'] ?? 'nombre';
        $orderDirection = $validated['direccion'] ?? 'asc';
        $query->orderBy($orderBy, $orderDirection);

        // Paginación
        $perPage = $validated['per_page'] ?? 50;
        $libros = $query->paginate($perPage);

        // Si se proporciona subinventario_id, agregar información de vendibilidad
        $responseData = $libros->map(function($libro) use ($validated) {
            $data = [
                'id' => $libro->id,
                'nombre' => $libro->nombre,
                'codigo_barras' => $libro->codigo_barras,
                'precio' => $libro->precio,
                'stock' => $libro->stock,
                'stock_subinventario' => $libro->stock_subinventario,
            ];

            // Agregar información de vendibilidad si se proporciona subinventario
            if (isset($validated['subinventario_id'])) {
                // Buscar cantidad disponible en el subinventario específico
                $cantidadEnSubinv = DB::table('subinventario_libro')
                    ->where('subinventario_id', $validated['subinventario_id'])
                    ->where('libro_id', $libro->id)
                    ->where('cantidad', '>', 0)
                    ->value('cantidad');

                $data['puede_vender'] = $cantidadEnSubinv > 0;
                $data['cantidad_disponible_para_mi'] = (int)($cantidadEnSubinv ?? 0);
            }

            return $data;
        });

        // Preparar respuesta
        $response = [
            'success' => true,
            'data' => $responseData->values(),
            'pagination' => [
                'total' => $libros->total(),
                'per_page' => $libros->perPage(),
                'current_page' => $libros->currentPage(),
                'last_page' => $libros->lastPage(),
                'from' => $libros->firstItem(),
                'to' => $libros->lastItem(),
            ]
        ];

        // Si se proporcionó subinventario, agregar resumen
        if (isset($validated['subinventario_id'])) {
            $totalPuedeVender = $responseData->where('puede_vender', true)->count();
            $totalNoPuedeVender = $responseData->where('puede_vender', false)->count();

            // Contar total de libros en el subinventario (sin filtros aplicados)
            $totalLibrosEnSubinventario = DB::table('subinventario_libro')
                ->where('subinventario_id', $validated['subinventario_id'])
                ->where('cantidad', '>', 0)
                ->count();

            // Contar total de libros en el sistema (sin filtros)
            $totalLibrosSistema = Libro::count();

            $response['resumen'] = [
                'total_puede_vender' => $totalPuedeVender,
                'total_no_puede_vender' => $totalNoPuedeVender,
                'total_libros_pagina' => $responseData->count(),
                'total_libros_en_subinventario' => $totalLibrosEnSubinventario,
                'total_libros_sistema' => $totalLibrosSistema
            ];

            // Información del subinventario
            $subinventario = SubInventario::find($validated['subinventario_id']);
            if ($subinventario) {
                $response['subinventario_actual'] = [
                    'id' => $subinventario->id,
                    'descripcion' => $subinventario->descripcion,
                    'estado' => $subinventario->estado
                ];
            }
        }

        return response()->json($response, 200);
    }

    /**
     * Exportar libros de un subinventario específico a Excel
     */
    public function exportLibrosExcel(SubInventario $subinventario)
    {
        $libros = $subinventario->libros;
        
        // Crear spreadsheet usando el servicio
        $spreadsheet = $this->excelReportService->createSpreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Título
        $row = $this->excelReportService->setTitle($sheet, 'REPORTE DE LIBROS EN SUB-INVENTARIO', 'E', 1);
        $row++; // Espacio
        
        // Información del subinventario
        $infoSubinventario = [
            'ID Sub-Inventario: ' . $subinventario->id,
            'Descripción: ' . ($subinventario->descripcion ?: 'Sub-Inventario #' . $subinventario->id),
            'Fecha: ' . $subinventario->fecha_subinventario->format('d/m/Y'),
            'Estado: ' . $this->getEstadoLabel($subinventario->estado),
            'Usuario: ' . $subinventario->usuario,
        ];
        $row = $this->excelReportService->setFilters($sheet, $infoSubinventario, $row);
        
        // Encabezados de tabla
        $headers = ['ID', 'Nombre', 'Código de Barras', 'Precio', 'Cantidad en Sub-Inventario'];
        $row = $this->excelReportService->setTableHeaders($sheet, $headers, $row);
        
        // Datos
        $data = [];
        foreach ($libros as $libro) {
            $data[] = [
                $libro->id,
                $libro->nombre,
                $libro->codigo_barras ?? 'Sin código',
                '$' . number_format($libro->precio, 2),
                $libro->pivot->cantidad,
            ];
        }
        
        $lastRow = $this->excelReportService->fillData($sheet, $data, $row);
        
        // Resumen al final
        $sheet->setCellValue('D' . ($lastRow + 2), 'Total de libros:');
        $sheet->setCellValue('E' . ($lastRow + 2), $libros->count());
        $sheet->getStyle('D' . ($lastRow + 2) . ':E' . ($lastRow + 2))->getFont()->setBold(true);
        
        $sheet->setCellValue('D' . ($lastRow + 3), 'Total de unidades:');
        $sheet->setCellValue('E' . ($lastRow + 3), $libros->sum('pivot.cantidad'));
        $sheet->getStyle('D' . ($lastRow + 3) . ':E' . ($lastRow + 3))->getFont()->setBold(true);
        
        // Auto ajustar columnas
        $this->excelReportService->autoSizeColumns($sheet, ['A', 'B', 'C', 'D', 'E']);
        
        // Descargar
        $filename = $this->excelReportService->generateFilename('libros_subinventario_' . $subinventario->id);
        $this->excelReportService->download($spreadsheet, $filename);
    }

    /**
     * Exportar libros de un subinventario específico a PDF
     */
    public function exportLibrosPdf(SubInventario $subinventario)
    {
        $libros = $subinventario->libros;
        
        // Obtener estilos base del servicio
        $styles = $this->pdfReportService->getBaseStyles();
        
        // Generar PDF usando el servicio
        $filename = $this->pdfReportService->generateFilename('libros_subinventario_' . $subinventario->id);
        
        return $this->pdfReportService->generate(
            'subinventarios.libros-pdf-report',
            compact('subinventario', 'libros', 'styles'),
            $filename
        );
    }

    /**
     * Mostrar formulario para importar libros en lote
     */
    public function showImportForm(SubInventario $subinventario)
    {
        // Solo permitir importar a subinventarios activos
        if ($subinventario->estado !== 'activo') {
            return redirect()->route('subinventarios.show', $subinventario)
                ->with('warning', 'Solo se pueden importar libros a sub-inventarios activos');
        }

        return view('subinventarios.import-libros', compact('subinventario'));
    }

    /**
     * Importar libros en lote desde Excel
     * Se espera un archivo Excel con las columnas: id, cantidad
     */
    public function importLibros(Request $request, SubInventario $subinventario)
    {
        $validated = $request->validate([
            'archivo' => 'required|file|mimes:xlsx,xls,csv',
        ], [
            'archivo.required' => 'Debes seleccionar un archivo',
            'archivo.mimes' => 'El archivo debe ser Excel o CSV',
        ]);

        // Solo permitir importar a subinventarios activos
        if ($subinventario->estado !== 'activo') {
            return redirect()->route('subinventarios.show', $subinventario)
                ->with('error', 'Solo se pueden importar libros a sub-inventarios activos');
        }

        try {
            // Leer el archivo
            $file = $request->file('archivo');
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
            $spreadsheet = $reader->load($file->getPathname());
            $sheet = $spreadsheet->getActiveSheet();
            
            $librosAgregados = [];
            $errores = [];
            $fila = 2; // Comenzar desde la fila 2 (después de encabezados)
            
            foreach ($sheet->getRowIterator(2) as $row) {
                $id = $sheet->getCell('A' . $fila)->getValue();
                $cantidad = $sheet->getCell('B' . $fila)->getValue();
                
                // Si no hay datos, saltar
                if (empty($id) || empty($cantidad)) {
                    $fila++;
                    continue;
                }
                
                // Buscar el libro por ID
                $libro = Libro::find((int)$id);
                
                if (!$libro) {
                    $errores[] = "Fila $fila: Libro con ID '$id' no encontrado";
                    $fila++;
                    continue;
                }
                
                // Validar cantidad
                if (!is_numeric($cantidad) || $cantidad < 1) {
                    $errores[] = "Fila $fila: Cantidad inválida ($cantidad)";
                    $fila++;
                    continue;
                }
                
                // Validar stock disponible
                if ($libro->stock < $cantidad) {
                    $errores[] = "Fila $fila: Stock insuficiente para '{$libro->nombre}' (disponible: {$libro->stock})";
                    $fila++;
                    continue;
                }
                
                // Agregar o actualizar el libro en el subinventario
                $this->agregarLibroAlSubinventario(
                    $subinventario, 
                    $libro->id, 
                    (int)$cantidad
                );
                
                $librosAgregados[] = [
                    'nombre' => $libro->nombre,
                    'id' => $id,
                    'cantidad' => $cantidad
                ];
                
                $fila++;
            }
            
            $mensaje = count($librosAgregados) . ' libro(s) agregado(s) correctamente';
            if (!empty($errores)) {
                $mensaje .= '. ' . count($errores) . ' fila(s) con error';
            }
            
            return redirect()->route('subinventarios.show', $subinventario)
                ->with('success', $mensaje)
                ->with('libros_agregados', $librosAgregados)
                ->with('errores_importacion', $errores);
                
        } catch (\Exception $e) {
            Log::error('Error al importar libros', [
                'error' => $e->getMessage(),
                'subinventario_id' => $subinventario->id
            ]);
            
            return redirect()->route('subinventarios.show', $subinventario)
                ->with('error', 'Error al procesar el archivo: ' . $e->getMessage());
        }
    }

    /**
     * Método auxiliar para agregar o actualizar un libro en el subinventario
     */
    private function agregarLibroAlSubinventario(SubInventario $subinventario, int $libroId, int $cantidad)
    {
        // Verificar si el libro ya existe en el subinventario
        $existe = $subinventario->libros()->where('libro_id', $libroId)->exists();
        
        if ($existe) {
            // Actualizar cantidad (sumar)
            $subinventario->libros()->updateExistingPivot($libroId, [
                'cantidad' => DB::raw('cantidad + ' . $cantidad)
            ]);
        } else {
            // Agregar nuevo
            $subinventario->libros()->attach($libroId, [
                'cantidad' => $cantidad
            ]);
        }
        
        // Actualizar el stock_subinventario del libro
        $libro = Libro::find($libroId);
        $totalEnSubinventarios = DB::table('subinventario_libro')
            ->where('libro_id', $libroId)
            ->sum('cantidad');
        
        $libro->update([
            'stock_subinventario' => $totalEnSubinventarios
        ]);
    }

    /**
     * Descargar plantilla Excel para importar libros
     */
    public function descargarPlantilla(SubInventario $subinventario)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Importar Libros');
        
        // Encabezados
        $sheet->setCellValue('A1', 'ID');
        $sheet->setCellValue('B1', 'Cantidad');
        
        // Hacer los encabezados bold
        $sheet->getStyle('A1:B1')->getFont()->setBold(true);
        $sheet->getStyle('A1:B1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFD3D3D3');
        
        // Agregar algunos libros disponibles como ejemplo
        $libros = Libro::where('stock', '>', 0)->limit(5)->get();
        $row = 2;
        
        foreach ($libros as $libro) {
            $sheet->setCellValue('A' . $row, $libro->id);
            $sheet->setCellValue('B' . $row, 1);
            $row++;
        }
        
        // Auto ajustar ancho de columnas
        $sheet->getColumnDimension('A')->setAutoSize(true);
        $sheet->getColumnDimension('B')->setAutoSize(true);
        
        // Descargar
        $writer = new Xlsx($spreadsheet);
        $filename = 'Plantilla_Importar_Libros_' . date('Y-m-d_His') . '.xlsx';
        
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $writer->save('php://output');
        exit;
    }
}
