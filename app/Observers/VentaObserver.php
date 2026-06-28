<?php

namespace App\Observers;

use App\Models\Venta;
use App\Models\Libro;

class VentaObserver
{
    /**
     * Handle the Venta "deleting" event.
     */
    public function deleting(Venta $venta): void
    {
        // 1. Soft-delete associated movements
        $venta->movimientos()->get()->each(function ($movimiento) {
            $movimiento->delete();
        });

        // 2. Soft-delete associated payments
        $venta->pagos()->get()->each(function ($pago) {
            $pago->delete();
        });

        // 3. Reactivate associated layaway (apartado) if any
        if ($venta->apartado_id) {
            $apartado = \App\Models\Apartado::find($venta->apartado_id);
            if ($apartado) {
                // Set status back to 'activo' and nullify venta_id
                $apartado->estado = 'activo';
                $apartado->venta_id = null;
                $apartado->save();

                // Reserve its stock again
                $stockService = app(\App\Services\InventoryStockService::class);
                $stockService->reserveApartado($apartado);
            }
        }
    }

    /**
     * Handle the Venta "updating" event.
     */
    public function updating(Venta $venta): void
    {
        // Detectar si el estado_pago está cambiando en una venta a plazos
        if ($venta->isDirty('estado_pago') && $venta->es_a_plazos) {
            $oldEstado = $venta->getOriginal('estado_pago');
            $newEstado = $venta->estado_pago;

            // Transición 1: Pasa a completado (El libro se entrega físicamente al cliente, sale de apartado)
            if ($oldEstado !== 'completado' && $newEstado === 'completado') {
                $this->consumeReserva($venta);
            }

            // Transición 2: Revierte de completado a parcial o pendiente (Vuelve a reservarse en stock_apartado)
            if ($oldEstado === 'completado' && $newEstado !== 'completado') {
                $this->restaurarReserva($venta);
            }
        }
    }

    private function consumeReserva(Venta $venta): void
    {
        foreach ($venta->movimientos()->where('tipo_movimiento', 'salida')->get() as $movimiento) {
            $libro = Libro::whereKey($movimiento->libro_id)->lockForUpdate()->firstOrFail();
            $cantidad = $movimiento->cantidad;

            if ($libro->stock_apartado >= $cantidad) {
                $libro->decrement('stock_apartado', $cantidad);
            } else {
                $libro->stock_apartado = 0;
                $libro->save();
            }
        }
    }

    private function restaurarReserva(Venta $venta): void
    {
        foreach ($venta->movimientos()->where('tipo_movimiento', 'salida')->get() as $movimiento) {
            $libro = Libro::whereKey($movimiento->libro_id)->lockForUpdate()->firstOrFail();
            $libro->increment('stock_apartado', $movimiento->cantidad);
        }
    }
}
