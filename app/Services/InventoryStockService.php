<?php

namespace App\Services;

use App\Models\Apartado;
use App\Models\Libro;
use App\Models\Venta;
use Illuminate\Support\Facades\DB;

class InventoryStockService
{
    /**
     * Descuenta una venta del inventario del que realmente salió.
     * Debe ejecutarse dentro de una transacción.
     */
    public function deductSale(Venta $venta): void
    {
        foreach ($this->saleQuantities($venta) as $libroId => $cantidad) {
            $libro = Libro::whereKey($libroId)->lockForUpdate()->firstOrFail();

            if ($this->isSubinventory($venta)) {
                $pivot = $this->lockedPivot($venta->subinventario_id, $libroId);
                if (!$pivot || (int) $pivot->cantidad < $cantidad) {
                    $disponible = $pivot ? (int) $pivot->cantidad : 0;
                    throw new \DomainException("Stock insuficiente para '{$libro->nombre}' en el subinventario. Disponible: {$disponible}");
                }
                if ((int) $libro->stock_subinventario < $cantidad) {
                    throw new \DomainException("El contador de subinventario de '{$libro->nombre}' es inconsistente");
                }

                DB::table('subinventario_libro')->where('id', $pivot->id)->decrement('cantidad', $cantidad);
                $libro->decrement('stock_subinventario', $cantidad);
                continue;
            }

            $reservado = $this->generalReservedQuantity($libroId);
            $disponible = (int) $libro->stock - $reservado;
            if ($disponible < $cantidad) {
                throw new \DomainException("Stock insuficiente para '{$libro->nombre}'. Disponible: {$disponible}");
            }

            $libro->decrement('stock', $cantidad);
        }
    }

    /** Devuelve una venta al mismo inventario del que salió. */
    public function restoreSale(Venta $venta): void
    {
        foreach ($this->saleQuantities($venta) as $libroId => $cantidad) {
            $libro = Libro::whereKey($libroId)->lockForUpdate()->firstOrFail();

            if ($this->isSubinventory($venta)) {
                $this->incrementPivot($venta->subinventario_id, $libroId, $cantidad);
                $libro->increment('stock_subinventario', $cantidad);
            } else {
                $libro->increment('stock', $cantidad);
            }
        }
    }

    /** Reserva los productos de un apartado recién creado. */
    public function reserveApartado(Apartado $apartado): void
    {
        foreach ($this->apartadoQuantities($apartado) as $libroId => $cantidad) {
            $libro = Libro::whereKey($libroId)->lockForUpdate()->firstOrFail();

            if ($this->isSubinventory($apartado)) {
                $pivot = $this->lockedPivot($apartado->subinventario_id, $libroId);
                if (!$pivot || (int) $pivot->cantidad < $cantidad) {
                    $disponible = $pivot ? (int) $pivot->cantidad : 0;
                    throw new \DomainException("Stock insuficiente para '{$libro->nombre}' en el subinventario. Disponible: {$disponible}");
                }
                if ((int) $libro->stock_subinventario < $cantidad) {
                    throw new \DomainException("El contador de subinventario de '{$libro->nombre}' es inconsistente");
                }

                DB::table('subinventario_libro')->where('id', $pivot->id)->decrement('cantidad', $cantidad);
                $libro->decrement('stock_subinventario', $cantidad);
            } else {
                // Excluir el apartado actual: sus detalles ya existen pero aún no se han reservado.
                $reservado = $this->generalReservedQuantity($libroId, $apartado->id);
                $disponible = (int) $libro->stock - $reservado;
                if ($disponible < $cantidad) {
                    throw new \DomainException("Stock insuficiente para '{$libro->nombre}'. Disponible: {$disponible}");
                }
            }

            $libro->increment('stock_apartado', $cantidad);
        }
    }

    /** Cancela la reserva y devuelve el producto a su inventario de origen. */
    public function releaseApartado(Apartado $apartado): void
    {
        foreach ($this->apartadoQuantities($apartado) as $libroId => $cantidad) {
            $libro = Libro::whereKey($libroId)->lockForUpdate()->firstOrFail();
            if ((int) $libro->stock_apartado < $cantidad) {
                throw new \DomainException("El stock apartado de '{$libro->nombre}' es inconsistente");
            }

            $libro->decrement('stock_apartado', $cantidad);
            if ($this->isSubinventory($apartado)) {
                $this->incrementPivot($apartado->subinventario_id, $libroId, $cantidad);
                $libro->increment('stock_subinventario', $cantidad);
            }
        }
    }

    /**
     * Convierte una reserva pagada en venta. En general se descuenta stock físico;
     * en subinventario ya se descontó físicamente al reservar.
     */
    public function consumeApartado(Apartado $apartado): void
    {
        foreach ($this->apartadoQuantities($apartado) as $libroId => $cantidad) {
            $libro = Libro::whereKey($libroId)->lockForUpdate()->firstOrFail();
            if ((int) $libro->stock_apartado < $cantidad) {
                throw new \DomainException("El stock apartado de '{$libro->nombre}' es inconsistente");
            }



            $libro->decrement('stock_apartado', $cantidad);
        }
    }

    private function saleQuantities(Venta $venta): array
    {
        return $venta->movimientos()->where('tipo_movimiento', 'salida')
            ->selectRaw('libro_id, SUM(cantidad) cantidad')
            ->groupBy('libro_id')->pluck('cantidad', 'libro_id')->map(fn ($q) => (int) $q)->all();
    }

    private function apartadoQuantities(Apartado $apartado): array
    {
        return $apartado->detalles()->selectRaw('libro_id, SUM(cantidad) cantidad')
            ->groupBy('libro_id')->pluck('cantidad', 'libro_id')->map(fn ($q) => (int) $q)->all();
    }

    private function isSubinventory($record): bool
    {
        return $record->tipo_inventario === 'subinventario' && !empty($record->subinventario_id);
    }

    private function lockedPivot(int $subinventarioId, int $libroId): ?object
    {
        return DB::table('subinventario_libro')->where('subinventario_id', $subinventarioId)
            ->where('libro_id', $libroId)->orderBy('id')->lockForUpdate()->first();
    }

    private function incrementPivot(int $subinventarioId, int $libroId, int $cantidad): void
    {
        $pivot = $this->lockedPivot($subinventarioId, $libroId);
        if ($pivot) {
            DB::table('subinventario_libro')->where('id', $pivot->id)->increment('cantidad', $cantidad);
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

    private function generalReservedQuantity(int $libroId, ?int $exceptApartadoId = null): int
    {
        $query = DB::table('apartado_detalles as ad')
            ->join('apartados as a', 'a.id', '=', 'ad.apartado_id')
            ->where('ad.libro_id', $libroId)
            ->where('a.tipo_inventario', 'general')
            ->where('a.estado', 'activo');

        if ($exceptApartadoId !== null) {
            $query->where('a.id', '!=', $exceptApartadoId);
        }

        return (int) $query->sum('ad.cantidad');
    }
}
