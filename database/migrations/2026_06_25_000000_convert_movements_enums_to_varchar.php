<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('movimientos', function (Blueprint $table) {
            $table->string('tipo_entrada', 255)->nullable()->change();
            $table->string('tipo_salida', 255)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('movimientos', function (Blueprint $table) {
            if (Schema::getConnection()->getDriverName() === 'mysql') {
                $table->enum('tipo_entrada', [
                    'compra',
                    'devolucion',
                    'ajuste_positivo',
                    'donacion_recibida',
                    'devolucion_subinventario'
                ])->nullable()->change();

                $table->enum('tipo_salida', [
                    'venta',
                    'perdida',
                    'ajuste_negativo',
                    'donacion_entregada',
                    'prestamo',
                    'transferencia_subinventario'
                ])->nullable()->change();
            } else {
                $table->string('tipo_entrada', 255)->nullable()->change();
                $table->string('tipo_salida', 255)->nullable()->change();
            }
        });
    }
};
