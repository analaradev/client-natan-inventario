<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;
use App\Helpers\AuthHelper;

class AuthController extends Controller
{
    /**
     * Muestra el formulario de login
     */
    public function showLogin()
    {
        // Si ya está autenticado, redirigir al dashboard
        if (Session::has('codCongregante')) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    /**
     * Procesa el inicio de sesión
     */
    public function login(Request $request)
    {
        $request->validate([
            'user' => 'required|string',
            'contra' => 'required|string',
        ]);

        // Login de prueba temporal para testing (solo disponible en entorno testing/local)
        if ((app()->environment('testing') || app()->environment('local')) && $request->user === 'test' && $request->contra === 'test123') {
            Session::put('codCongregante', 'TEST_TOKEN_123');
            Session::put('roles', [['ROL' => 'ADMIN LIBRERIA']]);
            Session::put('username', 'Usuario de Prueba');
            
            return redirect()->intended(route('dashboard'))
                ->with('success', 'Bienvenido al sistema');
        }

        try {
            // Llamar a la API de login
            $response = Http::post('https://www.sistemasdevida.com/pan/rest2/index.php/app/login', [
                'user' => $request->user,
                'contra' => $request->contra,
            ]);

            if (!$response->successful()) {
                Log::error('Error en API de login', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                
                return back()->with('error', 'Error al conectar con el servidor de autenticación.')
                    ->withInput($request->only('user'));
            }

            $data = $response->json();

            // Verificar si hubo error en la respuesta
            if (isset($data['error']) && $data['error'] === true) {
                return back()->with('error', 'Usuario o contraseña incorrectos.')
                    ->withInput($request->only('user'));
            }

            // Guardar el token como codCongregante en la sesión
            if (isset($data['token'])) {
                // Guardar temporalmente los roles para verificación
                if (isset($data['roles'])) {
                    Session::put('roles', $data['roles']);
                }
                
                // Verificar que el usuario tenga acceso al sistema (Admin Librería o Supervisor)
                if (!AuthHelper::canAccessSystem()) {
                    // Limpiar la sesión
                    Session::forget('roles');
                    
                    Log::warning('Usuario sin permisos de acceso al sistema', [
                        'user' => $request->user,
                        'roles' => $data['roles'] ?? []
                    ]);
                    
                    return back()->with('error', 'No tienes permisos para acceder al sistema. Solo Admin Librería y Supervisor pueden ingresar.')
                        ->withInput($request->only('user'));
                }
                
                // Usuario autorizado - guardar toda la información en sesión
                Session::put('codCongregante', $data['token']);
                
                if (isset($data['codCasaVida'])) {
                    Session::put('codCasaVida', $data['codCasaVida']);
                }
                if (isset($data['codHogar'])) {
                    Session::put('codHogar', $data['codHogar']);
                }
                
                // Guardar el nombre de usuario para mostrarlo
                Session::put('username', $request->user);

                Log::info('Usuario autenticado correctamente', [
                    'user' => $request->user,
                    'codCongregante' => $data['token']
                ]);

                return redirect()->intended(route('dashboard'))
                    ->with('success', 'Bienvenido al sistema');
            }

            // Si no hay token en la respuesta
            Log::error('Respuesta de login sin token', ['data' => $data]);
            return back()->with('error', 'Error al procesar la autenticación.')
                ->withInput($request->only('user'));

        } catch (\Exception $e) {
            Log::error('Excepción al hacer login', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return back()->with('error', 'Error al procesar la solicitud: ' . $e->getMessage())
                ->withInput($request->only('user'));
        }
    }

    /**
     * Cerrar sesión
     */
    public function logout()
    {
        Session::flush();
        
        return redirect()->route('login')
            ->with('success', 'Sesión cerrada correctamente');
    }
}
