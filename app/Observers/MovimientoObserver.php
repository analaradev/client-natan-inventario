<?php

namespace App\Observers;

use App\Models\Movimiento;
use App\Models\Libro;
use Illuminate\Support\Facades\DB;

class MovimientoObserver
{
    /**
     * Handle the Movimiento "created" event.
     */
    public function created(Movimiento $movimiento): void
    {
        $libro = Libro::whereKey($movimiento->libro_id)->lockForUpdate()->firstOrFail();
        $cantidad = $movimiento->cantidad;

        if ($movimiento->tipo_movimiento === 'entrada') {
            $this->handleEntrada($movimiento, $libro, $cantidad, true);
        } else {
            $this->handleSalida($movimiento, $libro, $cantidad, true);
        }
    }

    /**
     * Handle the Movimiento "deleted" event.
     */
    public function deleted(Movimiento $movimiento): void
    {
        $libro = Libro::whereKey($movimiento->libro_id)->lockForUpdate()->firstOrFail();
        $cantidad = $movimiento->cantidad;

        // Reversar la operación de forma precisa según el tipo y subtipo de movimiento
        if ($movimiento->tipo_movimiento === 'entrada') {
            // Reversar una Entrada: restar el stock que ingresó
            if ($movimiento->tipo_entrada === 'devolucion_subinventario') {
                $libro->decrement('stock', $cantidad);
                $libro->increment('stock_subinventario', $cantidad);
            } elseif ($movimiento->tipo_entrada === 'devolucion' && $movimiento->venta_id) {
                $venta = $movimiento->venta;
                if ($venta && $venta->tipo_inventario === 'subinventario') {
                    $libro->decrement('stock_subinventario', $cantidad);
                    $this->decrementSubinventoryPivot($venta->subinventario_id, $libro->id, $cantidad);
                } else {
                    $libro->decrement('stock', $cantidad);
                }

                if ($venta && $venta->es_a_plazos && $venta->estado_pago !== 'completado') {
                    $libro->increment('stock_apartado', $cantidad);
                }
            } else {
                $libro->decrement('stock', $cantidad);
            }
        } else {
            // Reversar una Salida: sumar el stock que salió
            if ($movimiento->tipo_salida === 'transferencia_subinventario') {
                $libro->increment('stock', $cantidad);
                $libro->decrement('stock_subinventario', $cantidad);
            } elseif ($movimiento->tipo_salida === 'venta' && $movimiento->venta_id) {
                $venta = $movimiento->venta;
                
                if ($venta && $venta->es_a_plazos && $venta->estado_pago !== 'completado') {
                    if ($libro->stock_apartado >= $cantidad) {
                        $libro->decrement('stock_apartado', $cantidad);
                    } else {
                        $libro->stock_apartado = 0;
                        $libro->save();
                    }
                }
                
                if ($venta && $venta->tipo_inventario === 'subinventario') {
                    // Si era la venta de liquidación de un apartado, nunca se decrementó
                    // el pivot ni stock_subinventario, por lo que tampoco los restauramos.
                    if ($venta->apartado_id === null) {
                        $libro->increment('stock_subinventario', $cantidad);
                        $this->incrementSubinventoryPivot($venta->subinventario_id, $libro->id, $cantidad);
                    }
                } else {
                    $libro->increment('stock', $cantidad);
                }
            } else {
                $libro->increment('stock', $cantidad);
            }
        }
    }

    private function handleEntrada(Movimiento $movimiento, Libro $libro, int $cantidad, bool $validate): void
    {
        // Traspaso devuelto desde subinventario a bodega principal
        if ($movimiento->tipo_entrada === 'devolucion_subinventario') {
            $libro->increment('stock', $cantidad);
            $libro->decrement('stock_subinventario', $cantidad);
            return;
        }

        // Devolución por venta cancelada o pago eliminado
        if ($movimiento->tipo_entrada === 'devolucion' && $movimiento->venta_id) {
            $venta = $movimiento->venta;
            if ($venta && $venta->tipo_inventario === 'subinventario') {
                $libro->increment('stock_subinventario', $cantidad);
                $this->incrementSubinventoryPivot($venta->subinventario_id, $libro->id, $cantidad);
            } else {
                $libro->increment('stock', $cantidad);
            }

            // Si la venta era a plazos y no estaba completada, los libros estaban en stock_apartado.
            // Al cancelarse/devolverse la venta, debemos liberar el apartado (restar de stock_apartado).
            if ($venta && $venta->es_a_plazos && $venta->estado_pago !== 'completado') {
                if ($libro->stock_apartado >= $cantidad) {
                    $libro->decrement('stock_apartado', $cantidad);
                } else {
                    $libro->stock_apartado = 0;
                    $libro->save();
                }
            }
            return;
        }

        // Cualquier otra entrada (compra, ajuste_positivo, donacion_recibida)
        $libro->increment('stock', $cantidad);
    }

    private function handleSalida(Movimiento $movimiento, Libro $libro, int $cantidad, bool $validate): void
    {
        // Traspaso de bodega principal a subinventario
        if ($movimiento->tipo_salida === 'transferencia_subinventario') {
            if ($validate) {
                $this->validateGeneralStock($libro, $cantidad);
            }
            $libro->decrement('stock', $cantidad);
            $libro->increment('stock_subinventario', $cantidad);
            return;
        }

        // Salida por venta
        if ($movimiento->tipo_salida === 'venta' && $movimiento->venta_id) {
            $venta = $movimiento->venta;
            
            // Si es venta a plazos (crédito) y aún no está completada
            if ($venta && $venta->es_a_plazos && $venta->estado_pago !== 'completado') {
                if ($venta->tipo_inventario === 'subinventario') {
                    if ($validate) {
                        $this->validateSubinventoryStock($venta->subinventario_id, $libro->id, $cantidad, $libro->nombre);
                    }
                    $libro->decrement('stock_subinventario', $cantidad);
                    $this->decrementSubinventoryPivot($venta->subinventario_id, $libro->id, $cantidad);
                } else {
                    if ($validate) {
                        $this->validateGeneralStock($libro, $cantidad);
                    }
                    $libro->decrement('stock', $cantidad);
                }
                // Se mueve el stock a apartado (Reserva)
                $libro->increment('stock_apartado', $cantidad);
            } else {
                // Venta de contado o ya completada
                if ($venta && $venta->tipo_inventario === 'subinventario') {
                    // Si es la venta generada al liquidar un apartado de subinventario,
                    // NO decrementar pivot ni stock_subinventario: ya fueron descontados
                    // cuando se reservó el apartado. consumeApartado() libera stock_apartado.
                    if ($venta->apartado_id !== null) {
                        return;
                    }
                    if ($validate) {
                        $this->validateSubinventoryStock($venta->subinventario_id, $libro->id, $cantidad, $libro->nombre);
                    }
                    $libro->decrement('stock_subinventario', $cantidad);
                    $this->decrementSubinventoryPivot($venta->subinventario_id, $libro->id, $cantidad);
                } else {
                    if ($validate) {
                        $this->validateGeneralStock($libro, $cantidad);
                    }
                    $libro->decrement('stock', $cantidad);
                }
            }
            return;
        }

        // Cualquier otra salida (perdida, ajuste_negativo, donacion_entregada, prestamo)
        if ($validate) {
            $this->validateGeneralStock($libro, $cantidad);
        }
        $libro->decrement('stock', $cantidad);
    }

    private function validateGeneralStock(Libro $libro, int $cantidad): void
    {
        // Obtener stock reservado por apartados activos
        $reservado = (int) DB::table('apartado_detalles as ad')
            ->join('apartados as a', 'a.id', '=', 'ad.apartado_id')
            ->where('ad.libro_id', $libro->id)
            ->where('a.tipo_inventario', 'general')
            ->where('a.estado', 'activo')
            ->sum('ad.cantidad');

        $disponible = $libro->stock - $reservado;
        if ($disponible < $cantidad) {
            throw new \DomainException("Stock insuficiente para '{$libro->nombre}'. Disponible: {$disponible}");
        }
    }

    private function validateSubinventoryStock(int $subinventarioId, int $libroId, int $cantidad, string $nombre): void
    {
        $pivot = DB::table('subinventario_libro')
            ->where('subinventario_id', $subinventarioId)
            ->where('libro_id', $libroId)
            ->first();

        $disponible = $pivot ? (int) $pivot->cantidad : 0;
        if ($disponible < $cantidad) {
            throw new \DomainException("Stock insuficiente para '{$nombre}' en el subinventario. Disponible: {$disponible}");
        }
    }

    private function incrementSubinventoryPivot(int $subinventarioId, int $libroId, int $cantidad): void
    {
        $pivot = DB::table('subinventario_libro')
            ->where('subinventario_id', $subinventarioId)
            ->where('libro_id', $libroId)
            ->first();

        if ($pivot) {
            DB::table('subinventario_libro')
                ->where('id', $pivot->id)
                ->increment('cantidad', $cantidad);
        } else {
            DB::table('subinventario_libro')->insert([
                'subinventario_id' => $subinventarioId,
                'libro_id' => $libroId,
                'cantidad' => $cantidad,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function decrementSubinventoryPivot(int $subinventarioId, int $libroId, int $cantidad): void
    {
        $pivot = DB::table('subinventario_libro')
            ->where('subinventario_id', $subinventarioId)
            ->where('libro_id', $libroId)
            ->first();

        if ($pivot) {
            $nuevaCantidad = max(0, $pivot->cantidad - $cantidad);
            DB::table('subinventario_libro')
                ->where('id', $pivot->id)
                ->update(['cantidad' => $nuevaCantidad]);
        }
    }
}
