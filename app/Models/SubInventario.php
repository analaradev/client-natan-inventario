<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SubInventario extends Model
{
    protected $table = 'subinventarios';
    
    protected $fillable = [
        'fecha_subinventario',
        'descripcion',
        'estado',
        'usuario',
        'observaciones',
    ];

    protected $casts = [
        'fecha_subinventario' => 'date',
    ];

    /**
     * Relación muchos a muchos con libros
     */
    public function libros(): BelongsToMany
    {
        return $this->belongsToMany(Libro::class, 'subinventario_libro', 'subinventario_id', 'libro_id')
                    ->withPivot('cantidad')
                    ->withTimestamps();
    }

    /**
     * Accessor para obtener el nombre de visualización del subinventario
     * Si descripcion está vacía, usa la fecha formateada
     */
    public function getNombreDisplayAttribute()
    {
        if (!empty($this->descripcion)) {
            return $this->descripcion;
        }
        
        // Si no hay descripción, usar la fecha formateada
        return 'Subinventario del ' . $this->fecha_subinventario->format('d/m/Y');
    }


    /**
     * Scope para filtrar sub-inventarios activos
     */
    public function scopeActivos($query)
    {
        return $query->where('estado', 'activo');
    }

    /**
     * Scope para filtrar sub-inventarios completados
     */
    public function scopeCompletados($query)
    {
        return $query->where('estado', 'completado');
    }

    /**
     * Scope para filtrar sub-inventarios cancelados
     */
    public function scopeCancelados($query)
    {
        return $query->where('estado', 'cancelado');
    }

    /**
     * Scope para filtrar por fecha
     */
    public function scopePorFecha($query, $fecha)
    {
        return $query->whereDate('fecha_subinventario', $fecha);
    }

    /**
     * Obtener el label del estado
     */
    public function getEstadoLabel()
    {
        return match($this->estado) {
            'activo' => 'Activo',
            'completado' => 'Completado',
            'cancelado' => 'Cancelado',
            default => 'Desconocido',
        };
    }

    /**
     * Obtener el color del badge según el estado
     */
    public function getBadgeColor()
    {
        return match($this->estado) {
            'activo' => 'bg-blue-100 text-blue-800',
            'completado' => 'bg-green-100 text-green-800',
            'cancelado' => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    /**
     * Obtener el icono según el estado
     */
    public function getIcon()
    {
        return match($this->estado) {
            'activo' => 'fas fa-box-open',
            'completado' => 'fas fa-check-circle',
            'cancelado' => 'fas fa-times-circle',
            default => 'fas fa-question-circle',
        };
    }

    /**
     * Calcular el total de libros en sub-inventario
     */
    public function getTotalLibros()
    {
        return $this->libros->count();
    }

    /**
     * Calcular el total de unidades en sub-inventario
     */
    public function getTotalUnidades()
    {
        return $this->libros->sum('pivot.cantidad');
    }

    /**
     * Activar el sub-inventario (reservar el inventario)
     */
    public function activar()
    {
        if ($this->estado !== 'activo') {
            return false;
        }

        foreach ($this->libros as $libro) {
            $cantidad = $libro->pivot->cantidad;
            
            // Verificar que hay stock suficiente
            if ($libro->stock < $cantidad) {
                return false;
            }

            // Reducir stock disponible y aumentar stock en sub-inventario
            $libro->decrement('stock', $cantidad);
            $libro->increment('stock_subinventario', $cantidad); // Cambiaremos este nombre después con migración
        }

        return true;
    }

    /**
     * Devolver parcialmente el sub-inventario (si no se vendió todo)
     */
    public function devolverParcial($libroId, $cantidadDevolver)
    {
        if ($this->estado !== 'activo') {
            return false;
        }

        $libro = $this->libros()->where('libro_id', $libroId)->first();
        
        if (!$libro || $cantidadDevolver > $libro->pivot->cantidad) {
            return false;
        }

        // Devolver al stock y reducir el sub-inventario
        $libro->increment('stock', $cantidadDevolver);
        $libro->decrement('stock_subinventario', $cantidadDevolver); // Cambiaremos este nombre después con migración

        // Actualizar la cantidad en la tabla pivote
        $this->libros()->updateExistingPivot($libroId, [
            'cantidad' => $libro->pivot->cantidad - $cantidadDevolver
        ]);

        return true;
    }
}
