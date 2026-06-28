<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApartadoDetalle extends Model
{
    protected $fillable = [
        'apartado_id',
        'libro_id',
        'cantidad',
        'precio_unitario',
        'descuento',
        'subtotal',
    ];

    protected $casts = [
        'cantidad' => 'integer',
        'precio_unitario' => 'decimal:2',
        'descuento' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    /**
     * Relación con Apartado
     */
    public function apartado(): BelongsTo
    {
        return $this->belongsTo(Apartado::class);
    }

    /**
     * Relación con Libro
     */
    public function libro(): BelongsTo
    {
        return $this->belongsTo(Libro::class)->withTrashed();
    }

    /**
     * Calcular subtotal automáticamente
     */
    public function calcularSubtotal(): float
    {
        $precio = $this->precio_unitario;
        
        if ($this->descuento > 0) {
            $precio -= ($precio * $this->descuento / 100);
        }
        
        return $precio * $this->cantidad;
    }
}
