<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class IngresoCaja extends Model
{
    use SoftDeletes;

    protected $table = 'ingresos_caja';

    protected $fillable = [
        'fecha',
        'monto',
        'metodo_pago',
        'concepto',
        'venta_id',
        'apartado_id',
        'pago_id',
        'abono_id',
        'subinventario_id',
        'tipo_inventario',
        'origen',
        'estado',
        'usuario',
        'observaciones',
    ];

    protected $casts = [
        'fecha' => 'datetime',
        'monto' => 'decimal:2',
    ];

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class)->withTrashed();
    }

    public function apartado(): BelongsTo
    {
        return $this->belongsTo(Apartado::class)->withTrashed();
    }

    public function pago(): BelongsTo
    {
        return $this->belongsTo(Pago::class)->withTrashed();
    }

    public function abono(): BelongsTo
    {
        return $this->belongsTo(Abono::class)->withTrashed();
    }

    public function subinventario(): BelongsTo
    {
        return $this->belongsTo(SubInventario::class)->withTrashed();
    }

    public function cortes(): BelongsToMany
    {
        return $this->belongsToMany(CorteCaja::class, 'corte_caja_ingreso')
            ->withTimestamps();
    }

    public static function metodosPago(): array
    {
        return [
            'efectivo' => 'Efectivo',
            'tarjeta' => 'Tarjeta',
            'transferencia' => 'Transferencia',
            'no_especificado' => 'No especificado',
        ];
    }

    public static function conceptos(): array
    {
        return [
            'venta' => 'Venta',
            'pago_venta' => 'Pago de venta',
            'abono_apartado' => 'Abono de apartado',
            'enganche_apartado' => 'Enganche de apartado',
            'envio' => 'Envío cobrado',
        ];
    }
}
