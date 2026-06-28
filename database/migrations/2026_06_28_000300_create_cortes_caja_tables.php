<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cortes_caja', function (Blueprint $table) {
            $table->id();
            $table->date('fecha_corte');
            $table->string('tipo_inventario', 30)->default('todos');
            $table->foreignId('subinventario_id')->nullable()->constrained('subinventarios')->nullOnDelete();
            $table->decimal('total_efectivo', 10, 2)->default(0);
            $table->decimal('total_tarjeta', 10, 2)->default(0);
            $table->decimal('total_transferencia', 10, 2)->default(0);
            $table->decimal('total_no_especificado', 10, 2)->default(0);
            $table->decimal('total_sistema', 10, 2)->default(0);
            $table->decimal('total_reportado', 10, 2)->default(0);
            $table->decimal('diferencia', 10, 2)->default(0);
            $table->string('estado', 30)->default('cerrado');
            $table->string('usuario_cierre')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['fecha_corte', 'tipo_inventario']);
        });

        Schema::create('corte_caja_ingreso', function (Blueprint $table) {
            $table->id();
            $table->foreignId('corte_caja_id')->constrained('cortes_caja')->cascadeOnDelete();
            $table->foreignId('ingreso_caja_id')->constrained('ingresos_caja')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['corte_caja_id', 'ingreso_caja_id']);
            $table->unique('ingreso_caja_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('corte_caja_ingreso');
        Schema::dropIfExists('cortes_caja');
    }
};
