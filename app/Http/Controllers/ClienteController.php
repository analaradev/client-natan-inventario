<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use Illuminate\Http\Request;

use App\Traits\HasRoleChecks;

class ClienteController extends Controller
{
    use HasRoleChecks;
    /**
     * Buscar clientes por nombre
     */
    public function search(Request $request)
    {
        $search = $request->get('q');
        
        $query = Cliente::query();
        
        // Si hay búsqueda, filtrar por nombre o teléfono
        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                  ->orWhere('telefono', 'like', "%{$search}%");
            });
        }
        
        // Obtener resultados (limitados a 20 para la carga inicial)
        $clientes = $query->orderBy('nombre', 'asc')
            ->limit(20)
            ->get(['id', 'nombre', 'telefono']);

        return response()->json($clientes);
    }

    /**
     * Mostrar todos los clientes
     */
    public function index(Request $request)
    {
        // Contar solo ventas no canceladas
        $query = Cliente::withCount(['ventas' => function($query) {
            $query->where('estado', '!=', 'cancelada');
        }]);

        // Búsqueda
        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                  ->orWhere('telefono', 'like', "%{$search}%");
            });
        }

        // Ordenamiento
        switch ($request->get('ordenar', 'reciente')) {
            case 'nombre_asc':
                $query->orderBy('nombre', 'asc');
                break;
            case 'nombre_desc':
                $query->orderBy('nombre', 'desc');
                break;
            case 'mas_ventas':
                $query->orderBy('ventas_count', 'desc');
                break;
            default:
                $query->latest();
        }

        $clientes = $query->paginate(10);
        
        $totalClientes = Cliente::count();

        return view('clientes.index', compact('clientes', 'totalClientes'));
    }

    /**
     * Mostrar formulario de creación
     */
    public function create(Request $request)
    {
        // Guardar URL de retorno si viene desde ventas
        if ($request->has('return_url')) {
            $request->session()->put('return_to_ventas', $request->get('return_url'));
        }
        
        return view('clientes.create');
    }

    /**
     * Crear un nuevo cliente
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'telefono' => 'nullable|string|max:20',
        ], [
            'nombre.required' => 'El nombre del cliente es obligatorio',
            'nombre.max' => 'El nombre no puede exceder 255 caracteres',
            'telefono.max' => 'El teléfono no puede exceder 20 caracteres',
        ]);

        // Si es una petición AJAX (desde el formulario de ventas)
        if ($request->ajax()) {
            $cliente = Cliente::create($validated);
            return response()->json([
                'success' => true,
                'cliente' => $cliente
            ]);
        }

        // Si es una petición normal
        $cliente = Cliente::create($validated);

        // Verificar si viene desde el formulario de ventas
        $returnUrl = $request->session()->get('return_to_ventas');
        
        if ($returnUrl) {
            // Limpiar la sesión de return_url
            $request->session()->forget('return_to_ventas');
            
            // Guardar el ID del cliente recién creado en sesión (SOLO para ventas)
            $request->session()->flash('nuevo_cliente_id', $cliente->id);
            $request->session()->flash('nuevo_cliente_nombre', $cliente->nombre);
            $request->session()->flash('nuevo_cliente_telefono', $cliente->telefono);
            
            return redirect($returnUrl)
                ->with('success', 'Cliente registrado exitosamente. Puedes continuar con la venta.');
        }

        // Si NO viene desde ventas, redirigir al index de clientes sin guardar en sesión
        return redirect()->route('clientes.index')
            ->with('success', 'Cliente registrado exitosamente');
    }

    /**
     * Mostrar detalles de un cliente
     */
    public function show(Cliente $cliente)
    {
        $ventas = $cliente->ventas()->latest()->paginate(10, ['*'], 'ventas_page');
        $apartados = $cliente->apartados()
            ->withCount('detalles')
            ->latest()
            ->paginate(10, ['*'], 'apartados_page');

        return view('clientes.show', compact('cliente', 'ventas', 'apartados'));
    }

    /**
     * Mostrar formulario de edición
     */
    public function edit(Cliente $cliente)
    {
        return view('clientes.edit', compact('cliente'));
    }

    /**
     * Actualizar un cliente
     */
    public function update(Request $request, Cliente $cliente)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'telefono' => 'nullable|string|max:20',
        ], [
            'nombre.required' => 'El nombre del cliente es obligatorio',
            'nombre.max' => 'El nombre no puede exceder 255 caracteres',
            'telefono.max' => 'El teléfono no puede exceder 20 caracteres',
        ]);

        $cliente->update($validated);

        return redirect()->route('clientes.index')
            ->with('success', 'Cliente actualizado exitosamente');
    }

    /**
     * Eliminar un cliente
     */
    public function destroy(Cliente $cliente)
    {
        // Verificar si el cliente tiene ventas
        if ($cliente->ventas()->count() > 0) {
            return redirect()->route('clientes.index')
                ->with('error', 'No se puede eliminar el cliente porque tiene ventas asociadas');
        }

        // Verificar si el cliente tiene apartados asociados
        if ($cliente->apartados()->count() > 0) {
            return redirect()->route('clientes.index')
                ->with('error', 'No se puede eliminar el cliente porque tiene apartados asociados');
        }

        $cliente->delete();

        return redirect()->route('clientes.index')
            ->with('success', 'Cliente eliminado exitosamente');
    }

    /**
     * API - Obtener lista de clientes
     */
    public function apiIndex(Request $request)
    {
        if (!$this->hasValidMobileRole($request)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes autorización para acceder a los clientes.'
            ], 403);
        }

        try {
            $query = Cliente::query();

            // Búsqueda por nombre o teléfono
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('nombre', 'like', "%{$search}%")
                      ->orWhere('telefono', 'like', "%{$search}%");
                });
            }

            // Ordenar por nombre
            $query->orderBy('nombre', 'asc');

            // Determinar si usar paginación
            if ($request->filled('sin_paginacion') && $request->sin_paginacion == '1') {
                // Si no es admin/supervisor y no hay término de búsqueda, no permitir descargar toda la base de datos sin paginar.
                $esAdmin = $this->isAdminFromRequest($request);
                if (!$esAdmin && !$request->filled('search')) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Búsqueda requerida para listar clientes sin paginación'
                    ], 400);
                }

                $clientes = $query->get();
                
                return response()->json([
                    'success' => true,
                    'data' => $clientes->map(function($cliente) {
                        return [
                            'id' => $cliente->id,
                            'nombre' => $cliente->nombre,
                            'telefono' => $cliente->telefono ?: null,
                        ];
                    }),
                    'total' => $clientes->count(),
                ]);
            }

            // Con paginación
            $perPage = (int) $request->get('per_page', 50);
            if ($perPage < 1 || $perPage > 250) {
                $perPage = 50;
            }
            $clientes = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $clientes->map(function($cliente) {
                    return [
                        'id' => $cliente->id,
                        'nombre' => $cliente->nombre,
                        'telefono' => $cliente->telefono ?: null,
                    ];
                }),
                'pagination' => [
                    'current_page' => $clientes->currentPage(),
                    'per_page' => $clientes->perPage(),
                    'total' => $clientes->total(),
                    'last_page' => $clientes->lastPage(),
                    'from' => $clientes->firstItem(),
                    'to' => $clientes->lastItem(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener clientes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * API: Buscar clientes para autocompletado
     */
    public function apiBuscar(Request $request)
    {
        $query = Cliente::query();

        if ($request->filled('q')) {
            $search = $request->q;
            $query->where(function($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                  ->orWhere('telefono', 'like', "%{$search}%");
            });
        }

        $clientes = $query->orderBy('nombre', 'asc')
            ->limit(10)
            ->get(['id', 'nombre', 'telefono']);

        return response()->json($clientes);
    }
}
