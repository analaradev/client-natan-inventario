<?php

namespace App\Console\Commands;

use App\Models\Libro;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SincronizarStock extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inventario:sincronizar';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Sincroniza y alinea stock_subinventario y stock_apartado de los libros con los datos reales de subinventarios y apartados activos, y corrige stocks negativos a 0';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando sincronización de stock de libros y corrección de inconsistencias...');

        DB::beginTransaction();
        try {
            $libros = Libro::all();
            $cambiosRealizados = 0;

            foreach ($libros as $libro) {
                // 1. Calcular cantidad real en subinventarios activos
                $subCalculado = (int) DB::table('subinventario_libro as sl')
                    ->join('subinventarios as s', 'sl.subinventario_id', '=', 's.id')
                    ->where('sl.libro_id', $libro->id)
                    ->where('s.estado', 'activo')
                    ->sum('sl.cantidad');

                // 2. Calcular cantidad real en apartados activos
                $apartadoCalculado = (int) DB::table('apartado_detalles as ad')
                    ->join('apartados as a', 'ad.apartado_id', '=', 'a.id')
                    ->where('ad.libro_id', $libro->id)
                    ->where('a.estado', 'activo')
                    ->sum('ad.cantidad');

                // Asegurar que los cálculos no sean negativos
                $subCalculado = max(0, $subCalculado);
                $apartadoCalculado = max(0, $apartadoCalculado);

                $modificado = false;
                $logMsg = "Libro #{$libro->id} ({$libro->nombre}):";

                // Validar stock_subinventario
                if ((int)$libro->stock_subinventario !== $subCalculado) {
                    $logMsg .= " stock_subinventario: {$libro->stock_subinventario} -> {$subCalculado}.";
                    $libro->stock_subinventario = $subCalculado;
                    $modificado = true;
                }

                // Validar stock_apartado
                if ((int)$libro->stock_apartado !== $apartadoCalculado) {
                    $logMsg .= " stock_apartado: {$libro->stock_apartado} -> {$apartadoCalculado}.";
                    $libro->stock_apartado = $apartadoCalculado;
                    $modificado = true;
                }

                // Validar stock general (bodega) negativo
                if ($libro->stock < 0) {
                    $logMsg .= " stock de bodega negativo corregido: {$libro->stock} -> 0.";
                    $libro->stock = 0;
                    $modificado = true;
                }

                if ($modificado) {
                    $libro->save();
                    $this->line($logMsg);
                    $cambiosRealizados++;
                }
            }

            DB::commit();
            $this->info("Sincronización y corrección completada con éxito. Se actualizaron {$cambiosRealizados} libros.");
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Ocurrió un error durante la sincronización: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
