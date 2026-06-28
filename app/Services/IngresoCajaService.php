<?php

namespace App\Services;

use App\Models\Abono;
use App\Models\Apartado;
use App\Models\IngresoCaja;
use App\Models\Pago;
use App\Models\Venta;
use Illuminate\Support\Facades\DB;

class IngresoCajaService
{
    public function registrarVenta(Venta $venta, string $origen = 'automatico'): void
    {
        $venta->refresh();

        if ($venta->estado === 'cancelada' || $venta->es_a_plazos || (float) $venta->total <= 0) {
            $this->cancelarPorVenta($venta);
            return;
        }

        $montoVenta = max(0, (float) $venta->total - (float) $venta->costo_envio);
        if ($montoVenta > 0) {
            IngresoCaja::updateOrCreate(
                [
                    'venta_id' => $venta->id,
                    'concepto' => 'venta',
                    'pago_id' => null,
                    'abono_id' => null,
                ],
                $this->baseVentaData($venta, [
                    'fecha' => $venta->fecha_venta,
                    'monto' => $montoVenta,
                    'metodo_pago' => $this->normalizarMetodo($venta->metodo_pago ?? null),
                    'origen' => $origen,
                    'observaciones' => "Venta #{$venta->id}",
                ])
            );
        } else {
            IngresoCaja::where('venta_id', $venta->id)
                ->where('concepto', 'venta')
                ->update(['estado' => 'cancelado']);
        }

        if ($venta->tiene_envio && (float) $venta->costo_envio > 0) {
            IngresoCaja::updateOrCreate(
                [
                    'venta_id' => $venta->id,
                    'concepto' => 'envio',
                    'pago_id' => null,
                    'abono_id' => null,
                ],
                $this->baseVentaData($venta, [
                    'fecha' => $venta->fecha_venta,
                    'monto' => $venta->costo_envio,
                    'metodo_pago' => $this->normalizarMetodo($venta->metodo_pago ?? null),
                    'origen' => $origen,
                    'observaciones' => "Envio cobrado en venta #{$venta->id}",
                ])
            );
        } else {
            IngresoCaja::where('venta_id', $venta->id)
                ->where('concepto', 'envio')
                ->update(['estado' => 'cancelado']);
        }
    }

    public function registrarPago(Pago $pago, string $origen = 'automatico'): void
    {
        $pago->load('venta');
        if (!$pago->venta) {
            return;
        }

        IngresoCaja::updateOrCreate(
            [
                'pago_id' => $pago->id,
                'concepto' => 'pago_venta',
            ],
            $this->baseVentaData($pago->venta, [
                'fecha' => $pago->fecha_pago,
                'monto' => $pago->monto,
                'metodo_pago' => $this->normalizarMetodo($pago->metodo_pago),
                'pago_id' => $pago->id,
                'origen' => $origen,
                'observaciones' => "Pago de venta #{$pago->venta_id}",
            ])
        );
    }

    public function registrarAbono(Abono $abono, string $origen = 'automatico', ?string $concepto = null): void
    {
        $abono->load('apartado');
        if (!$abono->apartado) {
            return;
        }

        $concepto ??= $this->esEnganche($abono) ? 'enganche_apartado' : 'abono_apartado';

        IngresoCaja::updateOrCreate(
            [
                'abono_id' => $abono->id,
                'concepto' => $concepto,
            ],
            [
                'fecha' => $abono->fecha_abono,
                'monto' => $abono->monto,
                'metodo_pago' => $this->normalizarMetodo($abono->metodo_pago),
                'venta_id' => null,
                'apartado_id' => $abono->apartado_id,
                'pago_id' => null,
                'subinventario_id' => $abono->apartado->subinventario_id,
                'tipo_inventario' => $abono->apartado->tipo_inventario ?? 'general',
                'origen' => $origen,
                'estado' => 'activo',
                'usuario' => $abono->usuario,
                'observaciones' => $concepto === 'enganche_apartado'
                    ? "Enganche de apartado {$abono->apartado->folio}"
                    : "Abono de apartado {$abono->apartado->folio}",
            ]
        );
    }

    public function cancelarPorVenta(Venta $venta): void
    {
        IngresoCaja::where('venta_id', $venta->id)->update(['estado' => 'cancelado']);
    }

    public function cancelarPorPago(Pago $pago): void
    {
        IngresoCaja::where('pago_id', $pago->id)->update(['estado' => 'cancelado']);
    }

    public function cancelarPorAbono(Abono $abono): void
    {
        IngresoCaja::where('abono_id', $abono->id)->update(['estado' => 'cancelado']);
    }

    public function reconstruirHistoricos(): array
    {
        $creados = 0;

        DB::transaction(function () use (&$creados) {
            Venta::withTrashed()->with(['pagos', 'apartado'])->orderBy('id')->chunk(100, function ($ventas) use (&$creados) {
                foreach ($ventas as $venta) {
                    $antes = IngresoCaja::count();
                    $this->registrarVenta($venta, 'migrado');
                    $creados += max(0, IngresoCaja::count() - $antes);
                }
            });

            Pago::withTrashed()->with('venta')->orderBy('id')->chunk(100, function ($pagos) use (&$creados) {
                foreach ($pagos as $pago) {
                    $antes = IngresoCaja::count();
                    $this->registrarPago($pago, 'migrado');
                    if ($pago->trashed()) {
                        $this->cancelarPorPago($pago);
                    }
                    $creados += max(0, IngresoCaja::count() - $antes);
                }
            });

            Abono::withTrashed()->with('apartado')->orderBy('id')->chunk(100, function ($abonos) use (&$creados) {
                foreach ($abonos as $abono) {
                    $antes = IngresoCaja::count();
                    $this->registrarAbono($abono, 'migrado');
                    if ($abono->trashed()) {
                        $this->cancelarPorAbono($abono);
                    }
                    $creados += max(0, IngresoCaja::count() - $antes);
                }
            });
        });

        return [
            'creados' => $creados,
            'activos' => IngresoCaja::where('estado', 'activo')->count(),
            'cancelados' => IngresoCaja::where('estado', 'cancelado')->count(),
        ];
    }

    public function normalizarMetodo(?string $metodo): string
    {
        return match ($metodo) {
            'efectivo', 'tarjeta', 'transferencia' => $metodo,
            default => 'no_especificado',
        };
    }

    private function baseVentaData(Venta $venta, array $extra): array
    {
        return array_merge([
            'venta_id' => $venta->id,
            'apartado_id' => $venta->apartado_id,
            'pago_id' => null,
            'abono_id' => null,
            'subinventario_id' => $venta->subinventario_id,
            'tipo_inventario' => $venta->tipo_inventario ?? 'general',
            'estado' => 'activo',
            'usuario' => $venta->usuario,
        ], $extra);
    }

    private function esEnganche(Abono $abono): bool
    {
        return str_contains(strtolower((string) $abono->observaciones), 'enganche');
    }
}
