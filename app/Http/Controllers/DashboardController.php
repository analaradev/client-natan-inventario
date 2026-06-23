<?php

namespace App\Http\Controllers;

use App\Models\Libro;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $totalLibros = Libro::count();
        
        $librosStats = Libro::selectRaw('
            SUM(stock) as total_general,
            SUM(COALESCE(stock_subinventario, 0)) as total_subinventario,
            SUM(precio * stock) as valor_general,
            SUM(precio * COALESCE(stock_subinventario, 0)) as valor_subinventario
        ')->first();

        $subinventarioReservadoStats = \DB::table('apartado_detalles as ad')
            ->join('apartados as a', 'a.id', '=', 'ad.apartado_id')
            ->join('libros as l', 'l.id', '=', 'ad.libro_id')
            ->where('a.tipo_inventario', 'subinventario')
            ->where('a.estado', 'activo')
            ->selectRaw('
                SUM(ad.cantidad) as total_reservado,
                SUM(l.precio * ad.cantidad) as valor_reservado
            ')->first();

        $stockTotal = ($librosStats->total_general ?? 0) + ($librosStats->total_subinventario ?? 0) + ($subinventarioReservadoStats->total_reservado ?? 0);
        $valorInventario = ($librosStats->valor_general ?? 0) + ($librosStats->valor_subinventario ?? 0) + ($subinventarioReservadoStats->valor_reservado ?? 0);

        return view('dashboard', compact('totalLibros', 'stockTotal', 'valorInventario'));
    }
}
