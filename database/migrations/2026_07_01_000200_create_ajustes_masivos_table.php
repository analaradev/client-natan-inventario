<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ajustes_masivos', function (Blueprint $table) {
            $table->id();
            $table->string('folio')->unique();
            $table->string('origen_stock')->default('general');
            $table->foreignId('subinventario_id')->nullable()->constrained('subinventarios')->nullOnDelete();
            $table->string('tipo_movimiento');
            $table->string('tipo_entrada')->nullable();
            $table->string('tipo_salida')->nullable();
            $table->date('fecha');
            $table->text('observaciones')->nullable();
            $table->string('usuario')->nullable();
            $table->unsignedInteger('total_lineas')->default(0);
            $table->unsignedInteger('total_unidades')->default(0);
            $table->timestamps();
        });

        Schema::table('movimientos', function (Blueprint $table) {
            $table->foreignId('ajuste_masivo_id')
                ->nullable()
                ->after('subinventario_id')
                ->constrained('ajustes_masivos')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('movimientos', function (Blueprint $table) {
            $table->dropForeign(['ajuste_masivo_id']);
            $table->dropColumn('ajuste_masivo_id');
        });

        Schema::dropIfExists('ajustes_masivos');
    }
};
