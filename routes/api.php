<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SubInventarioController;
use App\Http\Controllers\VentaController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\InventarioController;
use App\Http\Controllers\ApartadoController;
use App\Http\Controllers\AbonoMovilController;
use App\Http\Controllers\AuthController;

// API Routes
Route::prefix('v1')->middleware('throttle:20,1')->group(function () {
    Route::post('/login', [AuthController::class, 'apiLogin']);
});

Route::prefix('v1')->middleware(['throttle:60,1', 'secure.api', 'mobile.role'])->group(function () {
    // SubInventarios
    Route::get('/subinventarios', [SubInventarioController::class, 'apiIndex']);
    Route::get('/mis-subinventarios/{cod_congregante}', [SubInventarioController::class, 'apiMisSubinventarios']);
    Route::get('/subinventarios/{id}/libros', [SubInventarioController::class, 'apiLibrosSubinventario']);
    Route::get('/mis-libros-disponibles/{cod_congregante}', [SubInventarioController::class, 'apiMisLibrosDisponibles']);
    
    // Búsqueda de libros
    Route::get('/libros', [InventarioController::class, 'apiListarLibros']);
    Route::get('/libros/buscar-codigo/{codigo}', [InventarioController::class, 'apiBuscarPorCodigo']);
    Route::get('/libros/{id}/disponibilidad', [InventarioController::class, 'apiDisponibilidadLibro']);
    
    // Clientes
    Route::get('/clientes', [ClienteController::class, 'apiIndex']);
    
    // Ventas
    Route::post('/ventas', [VentaController::class, 'apiStore']);
    
    // Apartados
    Route::post('/apartados', [ApartadoController::class, 'apiStore']);
    
    // Abonos Móvil - Buscar apartados
    Route::prefix('movil')->group(function () {
        // Puntos de venta según rol: vendedor, admin librería o supervisor
        Route::get('/puntos-venta', [VentaController::class, 'apiPuntosVenta']);
        Route::get('/libros-disponibles', [SubInventarioController::class, 'apiTestListarTodosLibros']);

        // Subinventarios para usuarios normales y admin/supervisor
        Route::get('/subinventarios/mis-subinventarios/{cod_congregante}', [SubInventarioController::class, 'apiMisSubinventarios']);
        
        // Admin Librería - Puntos de venta y ventas
        Route::get('/admin/puntos-venta', [VentaController::class, 'apiAdminPuntosVenta']);
        Route::post('/admin/ventas', [VentaController::class, 'apiStoreAdmin']);

        // Clientes
        Route::get('/clientes', [AbonoMovilController::class, 'listarClientes']);
        Route::post('/clientes', [AbonoMovilController::class, 'crearCliente']);
        
        // Apartados
        Route::get('/apartados', [AbonoMovilController::class, 'listarApartados']);
        Route::get('/apartados/buscar-folio/{folio?}', [AbonoMovilController::class, 'buscarPorFolio']);
        Route::get('/apartados/buscar-cliente', [AbonoMovilController::class, 'buscarPorCliente']);
        Route::get('/apartados/{apartado_id}/abonos', [AbonoMovilController::class, 'historialAbonos']);
        
        // Abonos
        Route::post('/abonos', [AbonoMovilController::class, 'registrarAbono']);
    });
});

// Herramientas de diagnóstico: nunca exponerlas en producción.
if (app()->environment('local', 'testing')) {
    Route::prefix('v1/test')->group(function () {
        Route::get('/todos-los-libros', [SubInventarioController::class, 'apiTestListarTodosLibros']);
    });
}

Route::get('/reparar-datos-produccion/{secret_key}', function ($secret_key) {
    $configuredKey = env('PRODUCTION_REPAIR_KEY') ?: env('DB_MIGRATIONS_KEY');

    if (empty($configuredKey) || !hash_equals($configuredKey, $secret_key)) {
        return response()->json([
            'success' => false,
            'message' => 'Acceso no autorizado',
        ], 403);
    }

    if (request()->query('confirm') !== 'REPARAR') {
        return response()->json([
            'success' => false,
            'message' => 'Ruta protegida. Para ejecutar la reparacion agrega ?confirm=REPARAR al final de la URL.',
            'acciones' => [
                'Sincroniza stock_subinventario y stock_apartado.',
                'Corrige stock general negativo a 0.',
                'Reconstruye ingresos de caja desde ventas, pagos y abonos historicos.',
            ],
        ], 409);
    }

    try {
        \Illuminate\Support\Facades\Artisan::call('inventario:sincronizar');
        $inventarioOutput = \Illuminate\Support\Facades\Artisan::output();

        \Illuminate\Support\Facades\Artisan::call('caja:reconstruir-ingresos', [
            '--force' => true,
        ]);
        $cajaOutput = \Illuminate\Support\Facades\Artisan::output();

        return response()->json([
            'success' => true,
            'message' => 'Reparacion de datos completada correctamente. No se modifico la estructura de la base de datos.',
            'output' => [
                'inventario' => $inventarioOutput,
                'caja' => $cajaOutput,
            ],
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error al reparar datos de produccion.',
            'error' => $e->getMessage(),
        ], 500);
    }
});

// Operaciones destructivas disponibles únicamente en desarrollo local.
if (app()->environment('local', 'testing')) {
Route::get('/run-migrations/{secret_key}', function ($secret_key) {
    // Clave secreta para seguridad - cargada desde .env
    $configuredKey = env('DB_MIGRATIONS_KEY');
    if (empty($configuredKey) || $secret_key !== $configuredKey) {
        return response()->json([
            'success' => false,
            'message' => 'Acceso no autorizado'
        ], 403);
    }
    
    try {
        // Ejecutar migraciones
        \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
        
        $output = \Illuminate\Support\Facades\Artisan::output();
        
        return response()->json([
            'success' => true,
            'message' => 'Migraciones ejecutadas correctamente',
            'output' => $output
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error al ejecutar migraciones',
            'error' => $e->getMessage()
        ], 500);
    }
});

// Ruta para agregar columnas directamente (EMERGENCIA)
Route::get('/fix-envios-table/{secret_key}', function ($secret_key) {
    $configuredKey = env('DB_MIGRATIONS_KEY');
    if (empty($configuredKey) || $secret_key !== $configuredKey) {
        return response()->json([
            'success' => false,
            'message' => 'Acceso no autorizado'
        ], 403);
    }
    
    try {
        // Verificar si las columnas ya existen
        $hasColumns = \Illuminate\Support\Facades\Schema::hasColumns('envios', [
            'tipo_generacion', 'periodo_inicio', 'periodo_fin'
        ]);
        
        if ($hasColumns) {
            return response()->json([
                'success' => true,
                'message' => 'Las columnas ya existen en la tabla envios'
            ]);
        }
        
        // Agregar columnas directamente
        \Illuminate\Support\Facades\DB::statement("
            ALTER TABLE envios 
            ADD COLUMN tipo_generacion ENUM('manual', 'automatico') DEFAULT 'manual' AFTER estado_pago,
            ADD COLUMN periodo_inicio DATE NULL AFTER tipo_generacion,
            ADD COLUMN periodo_fin DATE NULL AFTER periodo_inicio
        ");
        
        return response()->json([
            'success' => true,
            'message' => 'Columnas agregadas exitosamente a la tabla envios'
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error al modificar la tabla',
            'error' => $e->getMessage()
        ], 500);
    }
});
}
