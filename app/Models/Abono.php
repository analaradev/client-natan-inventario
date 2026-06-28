<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\SoftDeletes;

class Abono extends Model
{
    use Auditable, SoftDeletes;
    protected $fillable = [
        'apartado_id',
        'fecha_abono',
        'monto',
        'saldo_anterior',
        'saldo_nuevo',
        'metodo_pago',
        'comprobante',
        'observaciones',
        'usuario',
    ];

    protected $casts = [
        'fecha_abono' => 'date',
        'monto' => 'decimal:2',
        'saldo_anterior' => 'decimal:2',
        'saldo_nuevo' => 'decimal:2',
    ];

    /**
     * Relación con Apartado
     */
    public function apartado(): BelongsTo
    {
        return $this->belongsTo(Apartado::class)->withTrashed();
    }

    /**
     * Obtener label del método de pago
     */
    public function getMetodoPagoLabel(): string
    {
        return match($this->metodo_pago) {
            'efectivo' => 'Efectivo',
            'transferencia' => 'Transferencia',
            'tarjeta' => 'Tarjeta',
            default => 'Desconocido',
        };
    }
}
