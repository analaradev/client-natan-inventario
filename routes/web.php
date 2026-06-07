<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventarioController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\MovimientoController;
use App\Http\Controllers\VentaController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\PagoController;
use App\Http\Controllers\EnvioController;
use App\Http\Controllers\SubInventarioController;
use App\Http\Controllers\ApartadoController;
use App\Http\Controllers\AbonoController;

// Rutas públicas de autenticación
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Rutas protegidas que requieren autenticación
Route::middleware('checkauth')->group(function () {
    
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Rutas que requieren Admin Librería
    Route::middleware('admin.libreria')->group(function () {
        // Inventario - Solo crear, editar, eliminar e importar requieren Admin Librería
        Route::get('/inventario/create', [InventarioController::class, 'create'])->name('inventario.create');
        Route::post('/inventario', [InventarioController::class, 'store'])->name('inventario.store');
        Route::get('/inventario/{inventario}/edit', [InventarioController::class, 'edit'])->name('inventario.edit');
        Route::put('/inventario/{inventario}', [InventarioController::class, 'update'])->name('inventario.update');
        Route::delete('/inventario/{inventario}', [InventarioController::class, 'destroy'])->name('inventario.destroy');
        
        // Importar Excel - Solo Admin Librería
        Route::get('/inventario-import', [InventarioController::class, 'importView'])->name('inventario.import');
        Route::get('/inventario-template', [InventarioController::class, 'downloadTemplate'])->name('inventario.download-template');
        Route::post('/inventario-import-process', [InventarioController::class, 'importProcess'])->name('inventario.import.process');
        
        // Movimientos - Solo crear, editar, eliminar requieren Admin Librería
        Route::get('/movimientos/create', [MovimientoController::class, 'create'])->name('movimientos.create');
        Route::post('/movimientos', [MovimientoController::class, 'store'])->name('movimientos.store');
        Route::get('/movimientos/{movimiento}/edit', [MovimientoController::class, 'edit'])->name('movimientos.edit');
        Route::put('/movimientos/{movimiento}', [MovimientoController::class, 'update'])->name('movimientos.update');
        Route::delete('/movimientos/{movimiento}', [MovimientoController::class, 'destroy'])->name('movimientos.destroy');
        
        // Ventas - Solo crear, editar, eliminar, cancelar requieren Admin Librería
        Route::get('/ventas/create', [VentaController::class, 'create'])->name('ventas.create');
        Route::post('/ventas', [VentaController::class, 'store'])->name('ventas.store');
        Route::get('/ventas/{venta}/edit', [VentaController::class, 'edit'])->name('ventas.edit');
        Route::put('/ventas/{venta}', [VentaController::class, 'update'])->name('ventas.update');
        Route::delete('/ventas/{venta}', [VentaController::class, 'destroy'])->name('ventas.destroy');
        Route::post('/ventas/{venta}/cancelar', [VentaController::class, 'cancelar'])->name('ventas.cancelar');
        
        // Apartados - Solo crear, editar, eliminar, liquidar, cancelar requieren Admin Librería
        Route::get('/apartados/create', [ApartadoController::class, 'create'])->name('apartados.create');
        Route::post('/apartados', [ApartadoController::class, 'store'])->name('apartados.store');
        Route::get('/apartados/{apartado}/edit', [ApartadoController::class, 'edit'])->name('apartados.edit');
        Route::put('/apartados/{apartado}', [ApartadoController::class, 'update'])->name('apartados.update');
        Route::delete('/apartados/{apartado}', [ApartadoController::class, 'destroy'])->name('apartados.destroy');
        Route::put('/apartados/{apartado}/liquidar', [ApartadoController::class, 'liquidar'])->name('apartados.liquidar');
        Route::put('/apartados/{apartado}/cancelar', [ApartadoController::class, 'cancelar'])->name('apartados.cancelar');
        
        // Abonos - Solo crear y eliminar abonos requiere Admin Librería
        Route::get('/apartados/{apartado}/abonos/crear', [AbonoController::class, 'create'])->name('apartados.abonos.create');
        Route::post('/apartados/{apartado}/abonos', [AbonoController::class, 'store'])->name('apartados.abonos.store');
        Route::delete('/abonos/{abono}', [AbonoController::class, 'destroy'])->name('apartados.abonos.destroy');
        
        // Sub-inventarios - Solo crear, editar, eliminar e importar requieren Admin Librería
        Route::get('/subinventarios/create', [SubInventarioController::class, 'create'])->name('subinventarios.create');
        Route::post('/subinventarios', [SubInventarioController::class, 'store'])->name('subinventarios.store');
        Route::get('/subinventarios/{subinventario}/edit', [SubInventarioController::class, 'edit'])->name('subinventarios.edit');
        Route::put('/subinventarios/{subinventario}', [SubInventarioController::class, 'update'])->name('subinventarios.update');
        Route::delete('/subinventarios/{subinventario}', [SubInventarioController::class, 'destroy'])->name('subinventarios.destroy');
        Route::post('/subinventarios/{subinventario}/devolver-parcial', [SubInventarioController::class, 'devolverParcial'])->name('subinventarios.devolver-parcial');
        
        // Importar libros en lote al subinventario
        Route::get('/subinventarios/{subinventario}/importar-libros', [SubInventarioController::class, 'showImportForm'])->name('subinventarios.import-form');
        Route::post('/subinventarios/{subinventario}/importar-libros', [SubInventarioController::class, 'importLibros'])->name('subinventarios.import');
        Route::get('/subinventarios/{subinventario}/descargar-plantilla', [SubInventarioController::class, 'descargarPlantilla'])->name('subinventarios.download-template');
        
        // Clientes - Solo crear, editar, eliminar requieren Admin Librería
        Route::get('/clientes/create', [ClienteController::class, 'create'])->name('clientes.create');
        Route::post('/clientes', [ClienteController::class, 'store'])->name('clientes.store');
        Route::get('/clientes/{cliente}/edit', [ClienteController::class, 'edit'])->name('clientes.edit');
        Route::put('/clientes/{cliente}', [ClienteController::class, 'update'])->name('clientes.update');
        Route::delete('/clientes/{cliente}', [ClienteController::class, 'destroy'])->name('clientes.destroy');
        
        // Envíos - Solo crear, editar, eliminar requieren Admin Librería
        Route::get('/envios/create', [EnvioController::class, 'create'])->name('envios.create');
        Route::post('/envios', [EnvioController::class, 'store'])->name('envios.store');
        Route::get('/envios/{envio}/edit', [EnvioController::class, 'edit'])->name('envios.edit');
        Route::put('/envios/{envio}', [EnvioController::class, 'update'])->name('envios.update');
        Route::delete('/envios/{envio}', [EnvioController::class, 'destroy'])->name('envios.destroy');
        Route::post('/envios-automaticos/generar-historicos', [EnvioController::class, 'generarHistoricos'])->name('envios.generar-historicos');
        
        // Usuarios - Solo crear, editar requieren Admin Librería
        Route::get('/usuarios/create', [UsuarioController::class, 'create'])->name('usuarios.create');
        Route::post('/usuarios', [UsuarioController::class, 'store'])->name('usuarios.store');
        Route::get('/usuarios/{usuario}/edit', [UsuarioController::class, 'edit'])->name('usuarios.edit');
        Route::put('/usuarios/{usuario}', [UsuarioController::class, 'update'])->name('usuarios.update');
        Route::delete('/usuarios/{usuario}', [UsuarioController::class, 'destroy'])->name('usuarios.destroy');
    });

    // Inventario - Rutas de lectura disponibles para todos
    Route::get('/inventario', [InventarioController::class, 'index'])->name('inventario.index');
    Route::get('/inventario/{inventario}', [InventarioController::class, 'show'])->name('inventario.show');

    // Inventario - Rutas de lectura disponibles para todos
    Route::get('/inventario', [InventarioController::class, 'index'])->name('inventario.index');
    Route::get('/inventario/{inventario}', [InventarioController::class, 'show'])->name('inventario.show');

    // Ruta para generar código de barras aleatorio
    Route::get('/inventario-generate-barcode', [InventarioController::class, 'generateBarcode'])->name('inventario.generate-barcode');

    // Ruta para descargar código QR
    Route::get('/inventario/{id}/qr/download', [InventarioController::class, 'downloadQR'])->name('inventario.qr.download');

    // Rutas para exportar reportes - Disponibles para todos
    Route::get('/inventario-export/excel', [InventarioController::class, 'exportExcel'])->name('inventario.export.excel');
    Route::get('/inventario-export/pdf', [InventarioController::class, 'exportPdf'])->name('inventario.export.pdf');

    // Movimientos - Rutas de lectura disponibles para todos
    Route::get('/movimientos', [MovimientoController::class, 'index'])->name('movimientos.index');
    Route::get('/movimientos/{movimiento}', [MovimientoController::class, 'show'])->name('movimientos.show');
    
    // Rutas para exportar reportes de movimientos - Disponibles para todos
    Route::get('/movimientos-export/excel', [MovimientoController::class, 'exportExcel'])->name('movimientos.export.excel');
    Route::get('/movimientos-export/pdf', [MovimientoController::class, 'exportPdf'])->name('movimientos.export.pdf');
    
    // Ventas - Rutas de lectura disponibles para todos
    Route::get('/ventas', [VentaController::class, 'index'])->name('ventas.index');
    Route::get('/ventas/{venta}', [VentaController::class, 'show'])->name('ventas.show');
    
    // Rutas para exportar reportes de ventas - Disponibles para todos
    Route::get('/ventas-export/excel', [VentaController::class, 'exportExcel'])->name('ventas.export.excel');
    Route::get('/ventas-export/pdf', [VentaController::class, 'exportPdf'])->name('ventas.export.pdf');
    
    // Rutas para exportar reportes de ventas - Disponibles para todos
    Route::get('/ventas-export/excel', [VentaController::class, 'exportExcel'])->name('ventas.export.excel');
    Route::get('/ventas-export/pdf', [VentaController::class, 'exportPdf'])->name('ventas.export.pdf');
    
    // Rutas de Usuarios - Disponibles para todos (solo lectura y edición de perfil)
    Route::resource('usuarios', UsuarioController::class)->only(['index', 'show', 'edit', 'update']);
    
    // Clientes - Rutas de lectura disponibles para todos
    Route::get('/clientes', [ClienteController::class, 'index'])->name('clientes.index');
    Route::get('/clientes/search', [ClienteController::class, 'search'])->name('clientes.search'); // Debe ir ANTES del {cliente}
    Route::get('/clientes/{cliente}', [ClienteController::class, 'show'])->name('clientes.show');
    
    // Rutas de pagos (dentro del módulo de ventas) - Disponibles para todos
    Route::get('/ventas/{venta}/pagos/crear', [PagoController::class, 'create'])->name('ventas.pagos.create');
    Route::post('/ventas/{venta}/pagos', [PagoController::class, 'store'])->name('pagos.store');
    Route::delete('/pagos/{pago}', [PagoController::class, 'destroy'])->name('pagos.destroy');
    
    // Envíos - Rutas de lectura disponibles para todos
    Route::get('/envios', [EnvioController::class, 'index'])->name('envios.index');
    Route::get('/envios/{envio}', [EnvioController::class, 'show'])->name('envios.show');
    
    // Rutas para envíos automáticos - Disponibles para todos
    Route::get('/envios-automaticos/crear', [EnvioController::class, 'crearAutomatico'])->name('envios.crear-automatico');
    Route::post('/envios-automaticos/store', [EnvioController::class, 'storeAutomatico'])->name('envios.store-automatico');
    
    // Rutas para exportar envío individual - Disponibles para todos
    Route::get('/envios/{envio}/export/excel', [EnvioController::class, 'exportIndividualExcel'])->name('envios.export-individual.excel');
    Route::get('/envios/{envio}/export/pdf', [EnvioController::class, 'exportIndividualPdf'])->name('envios.export-individual.pdf');
    
    // Rutas para marcar estado de pago en envíos - Disponibles para todos
    Route::get('/envios/{envio}/marcar-pago', [EnvioController::class, 'mostrarFormularioPago'])->name('envios.mostrar-pago');
    Route::post('/envios/{envio}/marcar-pagado', [EnvioController::class, 'marcarPagado'])->name('envios.marcar-pagado');
    Route::post('/envios/{envio}/marcar-pendiente', [EnvioController::class, 'marcarPendiente'])->name('envios.marcar-pendiente');
    
    // Rutas para exportar reportes de envíos - Disponibles para todos
    Route::get('/envios-export/excel', [EnvioController::class, 'exportExcel'])->name('envios.export.excel');
    Route::get('/envios-export/pdf', [EnvioController::class, 'exportPdf'])->name('envios.export.pdf');
    
    // Sub-inventarios - Rutas de lectura disponibles para todos
    Route::get('/subinventarios', [SubInventarioController::class, 'index'])->name('subinventarios.index');
    
    // Rutas de búsqueda y exportación de sub-inventarios - ANTES de rutas con parámetros
    Route::get('/subinventarios/buscar-congregantes', [SubInventarioController::class, 'buscarCongregantes'])->name('subinventarios.buscar-congregantes');
    Route::get('/subinventarios-export/excel', [SubInventarioController::class, 'exportExcel'])->name('subinventarios.export.excel');
    Route::get('/subinventarios-export/pdf', [SubInventarioController::class, 'exportPdf'])->name('subinventarios.export.pdf');
    
    // Rutas con parámetros - Al final para evitar conflictos
    Route::get('/subinventarios/{subinventario}', [SubInventarioController::class, 'show'])->name('subinventarios.show');
    
    // Rutas para exportar libros de un subinventario específico - Disponibles para todos
    Route::get('/subinventarios/{subinventario}/libros-export/excel', [SubInventarioController::class, 'exportLibrosExcel'])->name('subinventarios.libros.export.excel');
    Route::get('/subinventarios/{subinventario}/libros-export/pdf', [SubInventarioController::class, 'exportLibrosPdf'])->name('subinventarios.libros.export.pdf');
    

    
    // Apartados - Rutas de lectura disponibles para todos
    Route::get('/apartados', [ApartadoController::class, 'index'])->name('apartados.index');
    Route::get('/apartados/{apartado}', [ApartadoController::class, 'show'])->name('apartados.show');
    Route::get('/apartados-export/excel', [ApartadoController::class, 'exportExcel'])->name('apartados.export.excel');
    Route::get('/apartados-export/pdf', [ApartadoController::class, 'exportPdf'])->name('apartados.export.pdf');
    
});
