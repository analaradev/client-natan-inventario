<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pagos') && DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE pagos MODIFY COLUMN metodo_pago VARCHAR(50) NOT NULL DEFAULT 'no_especificado'");
            DB::table('pagos')
                ->whereIn('metodo_pago', ['contado', 'credito'])
                ->update(['metodo_pago' => 'no_especificado']);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pagos') && DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE pagos MODIFY COLUMN metodo_pago VARCHAR(50) NOT NULL DEFAULT "contado"');
            DB::table('pagos')
                ->where('metodo_pago', 'no_especificado')
                ->update(['metodo_pago' => 'contado']);
        }
    }
};
