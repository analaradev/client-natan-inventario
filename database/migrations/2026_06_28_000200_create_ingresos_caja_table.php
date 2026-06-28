<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingresos_caja', function (Blueprint $table) {
            $table->id();
            $table->dateTime('fecha');
            $table->decimal('monto', 10, 2);
            $table->string('metodo_pago', 30)->default('no_especificado');
            $table->string('concepto', 40);
            $table->foreignId('venta_id')->nullable()->constrained('ventas')->nullOnDelete();
            $table->foreignId('apartado_id')->nullable()->constrained('apartados')->nullOnDelete();
            $table->foreignId('pago_id')->nullable()->constrained('pagos')->nullOnDelete();
            $table->foreignId('abono_id')->nullable()->constrained('abonos')->nullOnDelete();
            $table->foreignId('subinventario_id')->nullable()->constrained('subinventarios')->nullOnDelete();
            $table->string('tipo_inventario', 30)->default('general');
            $table->string('origen', 30)->default('automatico');
            $table->string('estado', 30)->default('activo');
            $table->string('usuario')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['fecha', 'estado']);
            $table->index(['metodo_pago', 'concepto']);
            $table->index(['tipo_inventario', 'subinventario_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingresos_caja');
    }
};
