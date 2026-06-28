<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pago extends Model
{
    use Auditable, SoftDeletes;
    protected $fillable = [
        'venta_id',
        'fecha_pago',
        'monto',
        'metodo_pago',
        'comprobante',
        'notas',
    ];

    protected $casts = [
        'fecha_pago' => 'date',
        'monto' => 'decimal:2',
    ];

    /**
     * Relación con Venta
     */
    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class)->withTrashed();
    }

    /**
     * Obtener el label del tipo de pago
     */
    public function getTipoPagoLabel()
    {
        return match($this->metodo_pago) {
            'contado' => 'Contado',
            'credito' => 'Crédito',
            default => 'Desconocido',
        };
    }
}
