<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimientos', function (Blueprint $table) {
            $table->foreignId('subinventario_id')
                ->nullable()
                ->after('venta_id')
                ->constrained('subinventarios')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('movimientos', function (Blueprint $table) {
            $table->dropForeign(['subinventario_id']);
            $table->dropColumn('subinventario_id');
        });
    }
};
