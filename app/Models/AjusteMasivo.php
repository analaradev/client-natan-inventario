<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AjusteMasivo extends Model
{
    protected $table = 'ajustes_masivos';

    protected $fillable = [
        'folio',
        'origen_stock',
        'subinventario_id',
        'tipo_movimiento',
        'tipo_entrada',
        'tipo_salida',
        'fecha',
        'observaciones',
        'usuario',
        'total_lineas',
        'total_unidades',
    ];

    protected $casts = [
        'fecha' => 'date',
        'total_lineas' => 'integer',
        'total_unidades' => 'integer',
    ];

    public function movimientos(): HasMany
    {
        return $this->hasMany(Movimiento::class);
    }

    public function subinventario(): BelongsTo
    {
        return $this->belongsTo(SubInventario::class, 'subinventario_id')->withTrashed();
    }

    public function getOrigenLabel(): string
    {
        if ($this->subinventario) {
            return 'Subinventario #' . $this->subinventario->id . ' - ' . ($this->subinventario->descripcion ?? 'Sin descripción');
        }

        return 'Inventario general';
    }
}
