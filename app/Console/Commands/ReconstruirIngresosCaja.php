<?php

namespace App\Console\Commands;

use App\Services\IngresoCajaService;
use Illuminate\Console\Command;

class ReconstruirIngresosCaja extends Command
{
    protected $signature = 'caja:reconstruir-ingresos {--force : Ejecuta sin pedir confirmacion}';

    protected $description = 'Reconstruye ingresos de caja desde ventas, pagos y abonos existentes.';

    public function handle(IngresoCajaService $ingresoCajaService): int
    {
        if (!$this->option('force') && !$this->confirm('Esto revisara ventas, pagos y abonos historicos. Deseas continuar?')) {
            $this->info('Operacion cancelada.');
            return self::SUCCESS;
        }

        $this->info('Reconstruyendo ingresos de caja...');
        $resultado = $ingresoCajaService->reconstruirHistoricos();

        $this->info('Reconstruccion terminada.');
        $this->line('Creados o actualizados: ' . $resultado['creados']);
        $this->line('Ingresos activos: ' . $resultado['activos']);
        $this->line('Ingresos cancelados: ' . $resultado['cancelados']);

        return self::SUCCESS;
    }
}
