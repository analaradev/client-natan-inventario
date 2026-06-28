<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\SoftDeletes;

class Libro extends Model
{
    use Auditable, SoftDeletes;

    protected $table = 'libros';

    protected $fillable = [
        'nombre',
        'codigo_barras',
        'precio',
        'stock',
        'stock_subinventario',
    ];

    protected $casts = [
        'precio' => 'double',
        'stock' => 'integer',
        'stock_subinventario' => 'integer',
    ];

    // Relación con movimientos
    public function movimientos(): HasMany
    {
        return $this->hasMany(Movimiento::class);
    }

    /**
     * Relación con sub-inventarios
     */
    public function subinventarios()
    {
        return $this->belongsToMany(SubInventario::class, 'subinventario_libro', 'libro_id', 'subinventario_id')
                    ->withPivot('cantidad')
                    ->withTimestamps();
    }

    /**
     * Obtener el stock total (inventario general + subinventarios + apartados de subinventario)
     */
    public function getStockTotalAttribute()
    {
        $subinventarioReservado = (int) \DB::table('apartado_detalles as ad')
            ->join('apartados as a', 'a.id', '=', 'ad.apartado_id')
            ->where('ad.libro_id', $this->id)
            ->where('a.tipo_inventario', 'subinventario')
            ->where('a.estado', 'activo')
            ->sum('ad.cantidad');

        return $this->stock + ($this->stock_subinventario ?? 0) + $subinventarioReservado;
    }

    /**
     * Obtener el stock reservado en apartados de tipo general
     */
    public function getStockApartadoGeneralAttribute()
    {
        return (int) \DB::table('apartado_detalles as ad')
            ->join('apartados as a', 'a.id', '=', 'ad.apartado_id')
            ->where('ad.libro_id', $this->id)
            ->where('a.tipo_inventario', 'general')
            ->where('a.estado', 'activo')
            ->sum('ad.cantidad');
    }

    /**
     * Obtener el stock reservado en apartados de tipo subinventario
     */
    public function getStockApartadoSubinventarioAttribute()
    {
        return (int) \DB::table('apartado_detalles as ad')
            ->join('apartados as a', 'a.id', '=', 'ad.apartado_id')
            ->where('ad.libro_id', $this->id)
            ->where('a.tipo_inventario', 'subinventario')
            ->where('a.estado', 'activo')
            ->sum('ad.cantidad');
    }

    /**
     * Obtener el stock disponible en bodega general (stock físico menos reservado en apartados generales)
     */
    public function getStockGeneralDisponibleAttribute()
    {
        return max(0, $this->stock - $this->stock_apartado_general);
    }
}
