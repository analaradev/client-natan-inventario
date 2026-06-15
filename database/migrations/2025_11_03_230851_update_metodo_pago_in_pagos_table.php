<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // Primero cambiamos la columna a VARCHAR temporal
        DB::statement('ALTER TABLE pagos MODIFY COLUMN metodo_pago VARCHAR(50)');
        
        // Actualizamos todos los valores existentes a 'contado'
        DB::table('pagos')->update(['metodo_pago' => 'contado']);
        
        // Finalmente cambiamos a ENUM con los nuevos valores
        DB::statement("ALTER TABLE pagos MODIFY COLUMN metodo_pago ENUM('contado', 'credito') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // Primero cambiamos la columna a VARCHAR temporal
        DB::statement('ALTER TABLE pagos MODIFY COLUMN metodo_pago VARCHAR(50)');
        
        // Actualizamos los valores de vuelta
        DB::table('pagos')->where('metodo_pago', 'contado')->update(['metodo_pago' => 'efectivo']);
        DB::table('pagos')->where('metodo_pago', 'credito')->update(['metodo_pago' => 'efectivo']);
        
        // Finalmente cambiamos a ENUM con los valores originales
        DB::statement("ALTER TABLE pagos MODIFY COLUMN metodo_pago ENUM('efectivo', 'transferencia', 'tarjeta') NOT NULL DEFAULT 'efectivo'");
    }
};
