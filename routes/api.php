<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SubInventarioController;
use App\Http\Controllers\VentaController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\InventarioController;
use App\Http\Controllers\ApartadoController;
use App\Http\Controllers\AbonoMovilController;

// API Routes
Route::prefix('v1')->group(function () {
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

// API Routes sin versión (para uso interno)
Route::get('/apartados/buscar', [ApartadoController::class, 'apiBuscar']);
Route::get('/clientes/buscar', [ClienteController::class, 'apiBuscar']);

// Operaciones destructivas disponibles únicamente en desarrollo local.
if (app()->environment('local')) {
Route::get('/run-migrations/{secret_key}', function ($secret_key) {
    // Clave secreta para seguridad - cargada desde .env
    $configuredKey = env('DB_MIGRATIONS_KEY', 'pan_de_vida_2026_migrations');
    if ($secret_key !== $configuredKey) {
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
    $configuredKey = env('DB_MIGRATIONS_KEY', 'pan_de_vida_2026_migrations');
    if ($secret_key !== $configuredKey) {
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
