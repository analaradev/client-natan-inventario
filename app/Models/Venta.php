<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\SoftDeletes;

class Venta extends Model
{
    use HasFactory, Auditable, SoftDeletes;

    protected $fillable = [
        'cliente_id',
        'apartado_id',
        'fecha_venta',
        'tipo_pago',
        'subtotal',
        'descuento_global',
        'total',
        'estado',
        'tiene_envio',
        'costo_envio',
        'observaciones',
        'usuario',
        'tipo_inventario',
        'subinventario_id',
        'es_a_plazos',
        'total_pagado',
        'estado_pago',
        'fecha_limite',
    ];

    protected $casts = [
        'fecha_venta' => 'date',
        'subtotal' => 'decimal:2',
        'descuento_global' => 'decimal:2',
        'total' => 'decimal:2',
        'es_a_plazos' => 'boolean',
        'tiene_envio' => 'boolean',
        'costo_envio' => 'decimal:2',
        'total_pagado' => 'decimal:2',
        'fecha_limite' => 'date',
    ];

    /**
     * Relación: Una venta tiene muchos movimientos
     */
    public function movimientos()
    {
        return $this->hasMany(Movimiento::class);
    }

    /**
     * Relación: Una venta pertenece a un cliente (opcional)
     */
    public function cliente()
    {
        return $this->belongsTo(Cliente::class)->withTrashed();
    }

    /**
     * Relación: Una venta tiene muchos pagos
     */
    public function pagos()
    {
        return $this->hasMany(Pago::class);
    }

    /**
     * Relación: Una venta puede estar en muchos envíos (muchos a muchos)
     */
    public function envios()
    {
        return $this->belongsToMany(Envio::class, 'envio_venta')
                    ->withTimestamps();
    }

    /**
     * Relación: Una venta puede originarse de un apartado
     */
    public function apartado()
    {
        return $this->belongsTo(Apartado::class);
    }

    /**
     * Relación: Una venta puede provenir de un subinventario (punto de venta)
     */
    public function subinventario()
    {
        return $this->belongsTo(SubInventario::class)->withTrashed();
    }

    /**
     * Calcular el subtotal sumando todos los movimientos
     */
    public function calcularSubtotal()
    {
        return $this->movimientos->sum(function ($movimiento) {
            $precioConDescuento = $movimiento->precio_unitario;
            
            if ($movimiento->descuento) {
                $precioConDescuento -= ($movimiento->precio_unitario * $movimiento->descuento / 100);
            }
            
            return $precioConDescuento * $movimiento->cantidad;
        });
    }

    /**
     * Calcular el total aplicando descuento global y sumando costo de envío
     */
    public function calcularTotal()
    {
        $subtotal = $this->calcularSubtotal();
        
        if ($this->descuento_global > 0) {
            $descuento = ($subtotal * $this->descuento_global) / 100;
            $total = $subtotal - $descuento;
        } else {
            $total = $subtotal;
        }
        
        // Sumar el costo de envío si aplica
        if ($this->tiene_envio && $this->costo_envio > 0) {
            $total += $this->costo_envio;
        }
        
        return $total;
    }

    /**
     * Actualizar totales de la venta
     */
    public function actualizarTotales()
    {
        $this->subtotal = $this->calcularSubtotal();
        $this->total = $this->calcularTotal();
        $this->save();
    }

    /**
     * Obtener el color del badge según el estado
     */
    public function getBadgeColor()
    {
        return match($this->estado) {
            'completada' => 'bg-green-100 text-green-800',
            'pendiente' => 'bg-yellow-100 text-yellow-800',
            'cancelada' => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    /**
     * Obtener el icono según el estado
     */
    public function getIcon()
    {
        return match($this->estado) {
            'completada' => 'fas fa-check-circle',
            'pendiente' => 'fas fa-clock',
            'cancelada' => 'fas fa-times-circle',
            default => 'fas fa-question-circle',
        };
    }

    /**
     * Obtener label del tipo de pago
     */
    public function getTipoPagoLabel()
    {
        return match($this->tipo_pago) {
            'contado' => 'Contado',
            'credito' => 'Crédito',
            'mixto' => 'Mixto',
            default => 'Desconocido',
        };
    }

    /**
     * Obtener label del estado
     */
    public function getEstadoLabel()
    {
        return match($this->estado) {
            'completada' => 'Completada',
            'pendiente' => 'Pendiente',
            'cancelada' => 'Cancelada',
            default => 'Desconocido',
        };
    }

    /**
     * Scope para filtrar por estado
     */
    public function scopeEstado($query, $estado)
    {
        return $query->where('estado', $estado);
    }

    /**
     * Scope para filtrar por tipo de pago
     */
    public function scopeTipoPago($query, $tipo)
    {
        return $query->where('tipo_pago', $tipo);
    }

    /**
     * Scope para buscar por ID o cliente
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function($q) use ($search) {
            $q->where('id', 'like', "%{$search}%")
              ->orWhereHas('cliente', function($q) use ($search) {
                  $q->where('nombre', 'like', "%{$search}%");
              });
        });
    }

    /**
     * Scope para filtrar ventas a plazos
     */
    public function scopeVentasAPlazo($query)
    {
        return $query->where('es_a_plazos', true);
    }

    /**
     * Scope para filtrar por estado de pago
     */
    public function scopeEstadoPago($query, $estadoPago)
    {
        return $query->where('estado_pago', $estadoPago);
    }

    /**
     * Scope para filtrar ventas vencidas (a plazos sin pagar completamente que pasaron su fecha límite)
     */
    public function scopeVentasVencidas($query)
    {
        return $query->where('es_a_plazos', true)
                     ->where('estado_pago', '!=', 'completado')
                     ->whereNotNull('fecha_limite')
                     ->whereDate('fecha_limite', '<', now());
    }

    /**
     * Scope para filtrar ventas que contienen un libro específico
     */
    public function scopeConLibro($query, $libroId)
    {
        return $query->whereHas('movimientos', function($q) use ($libroId) {
            $q->where('libro_id', $libroId);
        });
    }

    /**
     * Scope para filtrar ventas que son apartados
     */
    public function scopeEsApartado($query, $esApartado = true)
    {
        if ($esApartado) {
            return $query->whereNotNull('apartado_id');
        }
        return $query->whereNull('apartado_id');
    }

    /**
     * Verificar si la venta es un apartado
     */
    public function esApartado()
    {
        return !is_null($this->apartado_id);
    }

    /**
     * Scope para filtrar por rango de fechas
     */
    public function scopeEntreFechas($query, $fechaDesde, $fechaHasta)
    {
        return $query->whereBetween('fecha_venta', [$fechaDesde, $fechaHasta]);
    }

    /**
     * Scope para filtrar por mes y año
     */
    public function scopePorMes($query, $mes, $anio)
    {
        return $query->whereMonth('fecha_venta', $mes)
                     ->whereYear('fecha_venta', $anio);
    }

    /**
     * Scope para filtrar ventas del día actual
     */
    public function scopeHoy($query)
    {
        return $query->whereDate('fecha_venta', today());
    }

    /**
     * Scope para filtrar ventas de la semana actual
     */
    public function scopeSemanaActual($query)
    {
        return $query->whereBetween('fecha_venta', [
            now()->startOfWeek(),
            now()->endOfWeek()
        ]);
    }

    /**
     * Scope para filtrar ventas del mes actual
     */
    public function scopeMesActual($query)
    {
        return $query->whereMonth('fecha_venta', now()->month)
                     ->whereYear('fecha_venta', now()->year);
    }

    /**
     * Calcular el total pagado sumando todos los pagos
     */
    public function calcularTotalPagado()
    {
        return $this->pagos()->sum('monto');
    }

    /**
     * Actualizar el estado de pago basado en los pagos realizados
     */
    public function actualizarEstadoPago()
    {
        if (!$this->es_a_plazos) {
            $this->estado_pago = 'completado';
            $this->total_pagado = $this->total;
            $this->save();
            return;
        }

        $totalPagado = $this->calcularTotalPagado();
        $this->total_pagado = $totalPagado;

        if ($totalPagado <= 0) {
            $this->estado_pago = 'pendiente';
        } elseif ($totalPagado >= $this->total) {
            $this->estado_pago = 'completado';
        } else {
            $this->estado_pago = 'parcial';
        }

        $this->save();
    }

    /**
     * Obtener el saldo pendiente
     */
    public function getSaldoPendienteAttribute()
    {
        return $this->total - $this->total_pagado;
    }

    /**
     * Obtener label del estado de pago
     */
    public function getEstadoPagoLabel()
    {
        return match($this->estado_pago) {
            'pendiente' => 'Pendiente',
            'parcial' => 'Pago Parcial',
            'completado' => 'Completado',
            default => 'Desconocido',
        };
    }

    /**
     * Obtener el color del badge según el estado de pago
     */
    public function getEstadoPagoBadgeColor()
    {
        return match($this->estado_pago) {
            'completado' => 'success',
            'parcial' => 'warning',
            'pendiente' => 'danger',
            default => 'secondary',
        };
    }

    /**
     * Obtener el estado unificado de la venta (considerando pagos y estado general)
     */
    public function getEstadoUnificado()
    {
        // Si está cancelada, siempre mostrar cancelada
        if ($this->estado === 'cancelada') {
            return 'cancelada';
        }

        // Si es a plazos, el estado depende del pago
        if ($this->es_a_plazos) {
            return match($this->estado_pago) {
                'pendiente' => 'pendiente_pago',
                'parcial' => 'pago_parcial',
                'completado' => 'completada',
                default => 'pendiente',
            };
        }

        // Si no es a plazos, usar el estado normal
        return $this->estado;
    }

    /**
     * Obtener el label del estado unificado
     */
    public function getEstadoUnificadoLabel()
    {
        $estado = $this->getEstadoUnificado();
        
        return match($estado) {
            'completada' => 'Completada',
            'pendiente_pago' => 'Pendiente de Pago',
            'pago_parcial' => 'Pago Parcial',
            'pendiente' => 'Pendiente',
            'cancelada' => 'Cancelada',
            default => 'Desconocido',
        };
    }

    /**
     * Obtener el color del badge para el estado unificado
     */
    public function getEstadoUnificadoBadgeColor()
    {
        $estado = $this->getEstadoUnificado();
        
        return match($estado) {
            'completada' => 'bg-green-100 text-green-800',
            'pago_parcial' => 'bg-yellow-100 text-yellow-800',
            'pendiente_pago' => 'bg-orange-100 text-orange-800',
            'pendiente' => 'bg-blue-100 text-blue-800',
            'cancelada' => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    /**
     * Obtener el icono para el estado unificado
     */
    public function getEstadoUnificadoIcon()
    {
        $estado = $this->getEstadoUnificado();
        
        return match($estado) {
            'completada' => 'fas fa-check-circle',
            'pago_parcial' => 'fas fa-clock',
            'pendiente_pago' => 'fas fa-exclamation-circle',
            'pendiente' => 'fas fa-hourglass-half',
            'cancelada' => 'fas fa-times-circle',
            default => 'fas fa-question-circle',
        };
    }
}
