<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Consolidar datos históricos antes de impedir nuevos duplicados.
        $duplicates = DB::table('subinventario_libro')
            ->select('subinventario_id', 'libro_id', DB::raw('SUM(cantidad) as cantidad'), DB::raw('MIN(id) as keep_id'))
            ->groupBy('subinventario_id', 'libro_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            DB::table('subinventario_libro')->where('id', $duplicate->keep_id)
                ->update(['cantidad' => $duplicate->cantidad, 'updated_at' => now()]);

            DB::table('subinventario_libro')
                ->where('subinventario_id', $duplicate->subinventario_id)
                ->where('libro_id', $duplicate->libro_id)
                ->where('id', '!=', $duplicate->keep_id)
                ->delete();
        }

        Schema::table('subinventario_libro', function (Blueprint $table) {
            $table->unique(['subinventario_id', 'libro_id'], 'subinventario_libro_unique');
        });
    }

    public function down(): void
    {
        Schema::table('subinventario_libro', function (Blueprint $table) {
            $table->dropUnique('subinventario_libro_unique');
        });
    }
};
