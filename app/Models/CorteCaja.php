<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CorteCaja extends Model
{
    use SoftDeletes;

    protected $table = 'cortes_caja';

    protected $fillable = [
        'fecha_corte',
        'tipo_inventario',
        'subinventario_id',
        'total_efectivo',
        'total_tarjeta',
        'total_transferencia',
        'total_no_especificado',
        'total_sistema',
        'total_reportado',
        'diferencia',
        'estado',
        'usuario_cierre',
        'observaciones',
    ];

    protected $casts = [
        'fecha_corte' => 'date',
        'total_efectivo' => 'decimal:2',
        'total_tarjeta' => 'decimal:2',
        'total_transferencia' => 'decimal:2',
        'total_no_especificado' => 'decimal:2',
        'total_sistema' => 'decimal:2',
        'total_reportado' => 'decimal:2',
        'diferencia' => 'decimal:2',
    ];

    public function ingresos(): BelongsToMany
    {
        return $this->belongsToMany(IngresoCaja::class, 'corte_caja_ingreso')
            ->withTimestamps();
    }

    public function subinventario(): BelongsTo
    {
        return $this->belongsTo(SubInventario::class)->withTrashed();
    }
}
