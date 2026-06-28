<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\SoftDeletes;

class Apartado extends Model
{
    use Auditable, SoftDeletes;
    protected $fillable = [
        'folio',
        'cliente_id',
        'fecha_apartado',
        'monto_total',
        'enganche',
        'saldo_pendiente',
        'fecha_limite',
        'estado',
        'observaciones',
        'usuario',
        'venta_id',
        'tipo_inventario',
        'subinventario_id',
    ];

    protected $casts = [
        'fecha_apartado' => 'date',
        'fecha_limite' => 'date',
        'monto_total' => 'decimal:2',
        'enganche' => 'decimal:2',
        'saldo_pendiente' => 'decimal:2',
    ];

    /**
     * Relación con Cliente
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class)->withTrashed();
    }

    /**
     * Relación con SubInventario
     */
    public function subinventario(): BelongsTo
    {
        return $this->belongsTo(SubInventario::class)->withTrashed();
    }

    /**
     * Relación con Detalles (libros apartados)
     */
    public function detalles(): HasMany
    {
        return $this->hasMany(ApartadoDetalle::class);
    }

    /**
     * Relación con Abonos
     */
    public function abonos(): HasMany
    {
        return $this->hasMany(Abono::class);
    }

    /**
     * Relación con Venta (cuando se liquida)
     */
    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    /**
     * Calcular porcentaje pagado
     */
    public function getPorcentajePagadoAttribute(): float
    {
        if ($this->monto_total == 0) {
            return 0;
        }
        
        $pagado = $this->monto_total - $this->saldo_pendiente;
        return round(($pagado / $this->monto_total) * 100, 2);
    }

    /**
     * Calcular total pagado
     */
    public function getTotalPagadoAttribute(): float
    {
        return $this->monto_total - $this->saldo_pendiente;
    }

    /**
     * Actualizar saldo pendiente
     */
    public function actualizarSaldo(): void
    {
        $totalAbonos = $this->abonos()->sum('monto');
        $this->saldo_pendiente = $this->monto_total - $totalAbonos;
        
        // NO cambiar automáticamente a liquidado
        // El usuario debe llamar a liquidar() explícitamente para crear la venta
        
        $this->save();
    }

    /**
     * Verificar si está vencido
     */
    public function getEstaVencidoAttribute(): bool
    {
        if (!$this->fecha_limite || $this->estado !== 'activo') {
            return false;
        }
        
        return now()->greaterThan($this->fecha_limite);
    }

    /**
     * Obtener badge color según estado
     */
    public function getBadgeColor(): string
    {
        return match($this->estado) {
            'activo' => 'bg-blue-100 text-blue-800',
            'liquidado' => 'bg-green-100 text-green-800',
            'cancelado' => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    /**
     * Obtener icono según estado
     */
    public function getIcon(): string
    {
        return match($this->estado) {
            'activo' => 'fas fa-clock',
            'liquidado' => 'fas fa-check-circle',
            'cancelado' => 'fas fa-times-circle',
            default => 'fas fa-question-circle',
        };
    }

    /**
     * Obtener label del estado
     */
    public function getEstadoLabel(): string
    {
        return match($this->estado) {
            'activo' => 'Activo',
            'liquidado' => 'Liquidado',
            'cancelado' => 'Cancelado',
            default => 'Desconocido',
        };
    }

    /**
     * Scope para apartados activos
     */
    public function scopeActivos($query)
    {
        return $query->where('estado', 'activo');
    }

    /**
     * Scope para apartados liquidados
     */
    public function scopeLiquidados($query)
    {
        return $query->where('estado', 'liquidado');
    }

    /**
     * Scope para apartados cancelados
     */
    public function scopeCancelados($query)
    {
        return $query->where('estado', 'cancelado');
    }

    /**
     * Scope para apartados vencidos
     */
    public function scopeVencidos($query)
    {
        return $query->where('estado', 'activo')
                     ->whereNotNull('fecha_limite')
                     ->where('fecha_limite', '<', now());
    }
}
