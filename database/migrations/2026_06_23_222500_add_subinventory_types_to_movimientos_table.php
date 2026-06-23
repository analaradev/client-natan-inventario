<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Agrega al esquema los tipos que el sistema ya utiliza al transferir
     * existencias entre el inventario general y los subinventarios.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(<<<'SQL'
            ALTER TABLE movimientos
            MODIFY tipo_entrada ENUM(
                'compra',
                'devolucion',
                'ajuste_positivo',
                'donacion_recibida',
                'devolucion_subinventario'
            ) NULL
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE movimientos
            MODIFY tipo_salida ENUM(
                'venta',
                'perdida',
                'ajuste_negativo',
                'donacion_entregada',
                'prestamo',
                'transferencia_subinventario'
            ) NULL
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // Convertir primero los valores nuevos para no truncar información
        // si alguna vez se revierte esta migración.
        DB::table('movimientos')
            ->where('tipo_entrada', 'devolucion_subinventario')
            ->update(['tipo_entrada' => 'ajuste_positivo']);

        DB::table('movimientos')
            ->where('tipo_salida', 'transferencia_subinventario')
            ->update(['tipo_salida' => 'ajuste_negativo']);

        DB::statement(<<<'SQL'
            ALTER TABLE movimientos
            MODIFY tipo_entrada ENUM(
                'compra',
                'devolucion',
                'ajuste_positivo',
                'donacion_recibida'
            ) NULL
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE movimientos
            MODIFY tipo_salida ENUM(
                'venta',
                'perdida',
                'ajuste_negativo',
                'donacion_entregada',
                'prestamo'
            ) NULL
        SQL);
    }
};
