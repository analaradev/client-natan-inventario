<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Pagination\LengthAwarePaginator;

class UsuarioController extends Controller
{
    /**
     * Muestra el listado de usuarios
     */
    public function index(Request $request)
    {
        $codCongregante = session('codCongregante');
        $page = $request->get('page', 1); // Laravel usa 'page' en base 1
        $pagina = $page - 1; // La API usa base 0
        $termino = trim($request->get('search', ''));
        
        $congregantes = [];
        $totalActivos = 0;
        $totalNuevos = 0;
        $error = null;

        try {
            if (!empty($termino) && ctype_digit($termino)) {
                $congregantePorId = $this->buscarCongregantePorId($codCongregante, $termino);

                if ($congregantePorId) {
                    $congregantesArray = [$congregantePorId];
                    $totalActivos = ($congregantePorId['CODSTATUS'] ?? '') == '2' ? 1 : 0;
                    $totalNuevos = ($congregantePorId['CODSTATUS'] ?? '') == '1' ? 1 : 0;

                    $congregantes = new LengthAwarePaginator(
                        $congregantesArray,
                        1,
                        1,
                        $page,
                        [
                            'path' => $request->url(),
                            'query' => $request->query(),
                        ]
                    );

                    return view('usuarios.index', compact('congregantes', 'totalActivos', 'totalNuevos', 'error'));
                }
            }

            // Determinar si es búsqueda o listado completo
            $endpoint = !empty($termino) 
                ? 'https://www.sistemasdevida.com/pan/rest2/index.php/congregante/buscar_paginado'
                : 'https://www.sistemasdevida.com/pan/rest2/index.php/congregante/todos';

            $params = [
                'codCongregante' => $codCongregante,
                'pagina' => $pagina
            ];

            // Agregar término de búsqueda si existe
            if (!empty($termino)) {
                $params['termino'] = $termino;
            }

            $response = Http::post($endpoint, $params);

            if ($response->successful()) {
                $data = $response->json();
                
                if (!$data['error']) {
                    $congregantesArray = $data['congregantes'] ?? [];
                    $totalRegistros = $data['total_registros'] ?? 0;
                    $totalActivos = $data['total_activos'] ?? 0;
                    $totalNuevos = $data['total_nuevos'] ?? 0;
                    
                    // Calcular items por página basado en los items actuales
                    $perPage = count($congregantesArray) > 0 ? count($congregantesArray) : 10;
                    
                    // Crear paginador de Laravel
                    $congregantes = new LengthAwarePaginator(
                        $congregantesArray,
                        $totalRegistros,
                        $perPage,
                        $page,
                        [
                            'path' => $request->url(),
                            'query' => $request->query(),
                        ]
                    );
                } else {
                    $error = 'Error al obtener congregantes de la API';
                }
            } else {
                $error = 'No se pudo conectar con el servicio de congregantes';
                Log::error('Error al obtener congregantes', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
            }
        } catch (\Exception $e) {
            $error = 'Error al procesar la solicitud: ' . $e->getMessage();
            Log::error('Excepción al obtener congregantes', [
                'message' => $e->getMessage()
            ]);
        }

        // Si hubo error, crear paginador vacío
        if ($error && empty($congregantes)) {
            $congregantes = new LengthAwarePaginator([], 0, 20, 1);
        }

        return view('usuarios.index', compact('congregantes', 'totalActivos', 'totalNuevos', 'error'));
    }

    /**
     * Busca un congregante por CODCONGREGANTE exacto.
     */
    private function buscarCongregantePorId($codCongregante, string $idCongregante): ?array
    {
        $response = Http::post('https://www.sistemasdevida.com/pan/rest2/index.php/congregante/obtener_detallado', [
            'codCongregante' => $codCongregante,
            'idCongregante' => $idCongregante
        ]);

        if (!$response->successful()) {
            Log::warning('No se pudo buscar congregante por ID', [
                'idCongregante' => $idCongregante,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        $data = $response->json();

        if (($data['error'] ?? true) || empty($data['congregante']) || !is_array($data['congregante'])) {
            return null;
        }

        return $this->normalizarCongreganteParaListado($data['congregante'], $idCongregante);
    }

    /**
     * Asegura que el detalle del congregante tenga los campos que usa la tabla.
     */
    private function normalizarCongreganteParaListado(array $congregante, string $idCongregante): array
    {
        return array_merge([
            'CODCONGREGANTE' => $idCongregante,
            'NOMBRE' => '',
            'APELLIDOS' => '',
            'CEL' => '',
            'TELCASA' => '',
            'CIUDAD' => '',
            'CODSTATUS' => '',
            'MAIL' => '',
        ], $congregante, [
            'CODCONGREGANTE' => $congregante['CODCONGREGANTE']
                ?? $congregante['codCongregante']
                ?? $idCongregante,
        ]);
    }

    /**
     * Muestra el formulario de creación
     */
    public function create()
    {
        return view('usuarios.create');
    }

    /**
     * Muestra los detalles de un congregante
     */
    public function show($id)
    {
        $codCongregante = session('codCongregante');
        
        try {
            $response = Http::post('https://www.sistemasdevida.com/pan/rest2/index.php/congregante/obtener_detallado', [
                'codCongregante' => $codCongregante,
                'idCongregante' => $id
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                if (!$data['error']) {
                    $congregante = $data['congregante'];
                    $roles = $data['roles'] ?? [];
                    
                    return view('usuarios.show', compact('congregante', 'roles'));
                } else {
                    return redirect()->route('usuarios.index')
                        ->with('error', 'No se pudo obtener la información del congregante');
                }
            } else {
                Log::error('Error al obtener detalles del congregante', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return redirect()->route('usuarios.index')
                    ->with('error', 'Error al conectar con el servicio');
            }
        } catch (\Exception $e) {
            Log::error('Excepción al obtener detalles del congregante', [
                'message' => $e->getMessage()
            ]);
            return redirect()->route('usuarios.index')
                ->with('error', 'Error al procesar la solicitud: ' . $e->getMessage());
        }
    }

    /**
     * Muestra el formulario de edición de roles
     */
    public function edit($id)
    {
        $codCongregante = session('codCongregante');
        
        try {
            $response = Http::post('https://www.sistemasdevida.com/pan/rest2/index.php/congregante/obtener_detallado', [
                'codCongregante' => $codCongregante,
                'idCongregante' => $id
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                if (!$data['error']) {
                    $congregante = $data['congregante'];
                    $rolesActuales = $data['roles'] ?? [];
                    
                    // Roles permitidos para asignar (Vendedor y Administrador Librería)
                    $rolesDisponibles = [
                        ['CODROL' => '18', 'ROL' => 'VENDEDOR', 'DESCRIPCION_ROL' => 'Vendedor de la librería'],
                        ['CODROL' => '19', 'ROL' => 'ADMIN LIBRERÍA', 'DESCRIPCION_ROL' => 'Administrador de la librería'],
                        ['CODROL' => '20', 'ROL' => 'SUPERVISOR', 'DESCRIPCION_ROL' => 'Supervisor de la librería'],
                    ];
                    
                    return view('usuarios.edit', compact('congregante', 'rolesActuales', 'rolesDisponibles'));
                } else {
                    return redirect()->route('usuarios.index')
                        ->with('error', 'No se pudo obtener la información del congregante');
                }
            } else {
                Log::error('Error al obtener detalles del congregante', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return redirect()->route('usuarios.index')
                    ->with('error', 'Error al conectar con el servicio');
            }
        } catch (\Exception $e) {
            Log::error('Excepción al obtener detalles del congregante', [
                'message' => $e->getMessage()
            ]);
            return redirect()->route('usuarios.index')
                ->with('error', 'Error al procesar la solicitud: ' . $e->getMessage());
        }
    }

    /**
     * Actualiza los roles de un congregante
     */
    public function update(Request $request, $id)
    {
        $codCongregante = session('codCongregante');
        
        $request->validate([
            'roles' => 'array',
            'roles.*' => 'in:18,19,20', // Vendedor (18), admin librería (19), supervisor (20)
        ]);

        try {
            // Obtener roles actuales
            $response = Http::post('https://www.sistemasdevida.com/pan/rest2/index.php/congregante/obtener_detallado', [
                'codCongregante' => $codCongregante,
                'idCongregante' => $id
            ]);

            if (!$response->successful()) {
                return back()->with('error', 'No se pudo obtener información del congregante');
            }

            $data = $response->json();
            $rolesActuales = collect($data['roles'] ?? [])
                ->whereIn('CODROL', ['18', '19', '20'])
                ->pluck('CODROL')
                ->toArray();

            $rolesNuevos = $request->input('roles', []);

            // Determinar roles a agregar y remover
            $rolesAgregar = array_diff($rolesNuevos, $rolesActuales);
            $rolesRemover = array_diff($rolesActuales, $rolesNuevos);

            $erroresRoles = [];

            // Agregar roles nuevos
            foreach ($rolesAgregar as $rol) {
                $rolResponse = Http::post('https://www.sistemasdevida.com/pan/rest2/index.php/rol/crear', [
                    'codCongregante' => $codCongregante,
                    'idCongregante' => $id,
                    'rol' => $rol
                ]);

                if (!$rolResponse->successful() || ($rolResponse->json()['error'] ?? true)) {
                    Log::error('Error al agregar rol', [
                        'codCongregante' => $codCongregante,
                        'idCongregante' => $id,
                        'rol' => $rol,
                        'status' => $rolResponse->status(),
                        'body' => $rolResponse->body()
                    ]);
                    $erroresRoles[] = 'No se pudo asignar el rol ' . $rol . '.';
                }
            }

            // Remover roles
            foreach ($rolesRemover as $rol) {
                $rolResponse = Http::post('https://www.sistemasdevida.com/pan/rest2/index.php/rol/baja', [
                    'codCongregante' => $codCongregante,
                    'idCongregante' => $id,
                    'rol' => $rol
                ]);

                if (!$rolResponse->successful() || ($rolResponse->json()['error'] ?? true)) {
                    Log::error('Error al remover rol', [
                        'codCongregante' => $codCongregante,
                        'idCongregante' => $id,
                        'rol' => $rol,
                        'status' => $rolResponse->status(),
                        'body' => $rolResponse->body()
                    ]);
                    $erroresRoles[] = 'No se pudo remover el rol ' . $rol . '.';
                }
            }

            if (!empty($erroresRoles)) {
                return redirect()->route('usuarios.show', $id)
                    ->with('warning', 'Roles actualizados con errores: ' . implode(' ', $erroresRoles));
            }

            return redirect()->route('usuarios.show', $id)
                ->with('success', 'Roles actualizados correctamente');

        } catch (\Exception $e) {
            Log::error('Excepción al actualizar roles', [
                'message' => $e->getMessage()
            ]);
            
            return back()->with('error', 'Error al procesar la solicitud: ' . $e->getMessage());
        }
    }

    /**
     * Almacena un nuevo usuario (congregante + credenciales)
     */
    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
            'apellidos' => 'required|string|max:255',
            'usuario' => 'required|string|max:50',
            'password' => 'required|string|min:4',
            'rol' => 'required|integer',
        ]);

        try {
            // PASO 1: Crear congregante
            $congreganteResponse = Http::post('https://sistemasdevida.com/pan/altaCongregante.php', [
                'nombre' => $request->nombre,
                'apellidos' => $request->apellidos,
                'fecAlta' => now()->format('d/m/Y'),
                'fecLibro1' => '',
                'sexo' => 'O',
                'edoCivil' => 'S',
                'telCasa' => '',
                'horario' => '',
                'telCelular' => '',
                'calle' => '',
                'entreCalle' => '',
                'colonia' => '',
                'codPostal' => '',
                'ciudad' => '',
                'necesidad' => '',
                'via' => '',
                'observaciones' => '',
                'otraIgl' => 0,
                'iglesia' => '',
                'verifica' => '',
                'fecNac' => '',
                'mail' => '',
                'plataforma' => '0',
                'platAsignacion' => '0',
                'afirmador' => 0,
                'casavida' => 0
            ]);

            if (!$congreganteResponse->successful()) {
                Log::error('Error al crear congregante', [
                    'status' => $congreganteResponse->status(),
                    'body' => $congreganteResponse->body()
                ]);
                return back()->with('error', 'Error al crear el congregante en el sistema externo.')
                    ->withInput();
            }

            $congreganteData = $congreganteResponse->json();
            $idCongregante = $congreganteData['id'] ?? null;

            if (!$idCongregante) {
                Log::error('No se obtuvo ID del congregante', ['response' => $congreganteData]);
                return back()->with('error', 'No se pudo obtener el ID del congregante.')
                    ->withInput();
            }

            // PASO 2: Asignar rol
            $rolResponse = Http::post('https://sistemasdevida.com/pan/altaRol.php', [
                'idCon' => $idCongregante,
                'rol' => $request->rol
            ]);

            if (!$rolResponse->successful()) {
                Log::error('Error al asignar rol', [
                    'idCon' => $idCongregante,
                    'status' => $rolResponse->status(),
                    'body' => $rolResponse->body()
                ]);
                // Continuamos aunque falle el rol
            }

            // PASO 3: Crear credenciales (SIN CIFRADO - contraseña plana)
            $usuarioResponse = Http::post('https://sistemasdevida.com/pan/usuarios.php', [
                'idCon' => $idCongregante,
                'us' => $request->usuario,
                'pass' => $request->password  // Contraseña SIN cifrado
            ]);

            if (!$usuarioResponse->successful()) {
                Log::error('Error al crear usuario', [
                    'idCon' => $idCongregante,
                    'status' => $usuarioResponse->status(),
                    'body' => $usuarioResponse->body()
                ]);
                return back()->with('error', 'Congregante creado, pero error al crear credenciales de acceso.')
                    ->withInput();
            }

            return redirect()->route('usuarios.index')
                ->with('success', 'Usuario creado exitosamente. ID Congregante: ' . $idCongregante);

        } catch (\Exception $e) {
            Log::error('Excepción al crear usuario', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return back()->with('error', 'Error al procesar la solicitud: ' . $e->getMessage())
                ->withInput();
        }
    }
}
