<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Ventas</title>
    <style>
        {!! $styles !!}
        
        .section-break {
            page-break-after: always;
            margin-top: 40px;
            margin-bottom: 40px;
            border-top: 2px solid #ddd;
            padding-top: 20px;
        }
        
        .section-title {
            font-size: 14px;
            font-weight: bold;
            color: #1F2937;
            margin: 20px 0 15px 0;
            padding-bottom: 5px;
            border-bottom: 2px solid #3B82F6;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>REPORTE DE VENTAS DETALLADO</h1>
        <p>Generado el {{ date('d/m/Y H:i:s') }}</p>
    </div>

    @if(count($filtros) > 0)
        <div class="filters">
            <h3>Filtros Aplicados:</h3>
            <ul>
                @foreach($filtros as $filtro)
                    <li>{{ $filtro }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- ===== SECCIÓN 1: RESUMEN DE VENTAS ===== --}}
    <div class="section-title">RESUMEN DE VENTAS</div>

    {{-- Resumen de estadísticas --}}
    @if(isset($estadisticas))
        <div class="summary">
            <table style="width: 100%; border: none;">
                <tr>
                    <td style="border: none; padding: 5px;">
                        <span class="summary-label">Total Ventas:</span>
                        <span class="summary-value">{{ $estadisticas['total'] ?? 0 }}</span>
                    </td>
                    <td style="border: none; padding: 5px;">
                        <span class="summary-label">Monto Total:</span>
                        <span class="summary-value badge-success">${{ number_format($estadisticas['monto_total'] ?? 0, 2) }}</span>
                    </td>
                    <td style="border: none; padding: 5px;">
                        <span class="summary-label">Unidades Vendidas:</span>
                        <span class="summary-value badge-info">{{ $estadisticas['unidades_vendidas'] ?? 0 }}</span>
                    </td>
                    <td style="border: none; padding: 5px;">
                        <span class="summary-label">Con Envío:</span>
                        <span class="summary-value badge-info">{{ $estadisticas['ventas_con_envio'] ?? 0 }}</span>
                    </td>
                </tr>
            </table>
        </div>
    @endif

    @if($ventas->count() > 0)
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Fecha</th>
                    <th>Cliente</th>
                    <th class="text-center">Origen</th>
                    <th class="text-center">Apartado</th>
                    <th class="text-center">Libros</th>
                    <th class="text-center">Unidades</th>
                    <th>Tipo Pago</th>
                    <th class="text-right">Total</th>
                    <th class="text-center">Envío</th>
                    <th class="text-center">Estado</th>
                </tr>
            </thead>
            <tbody>
                @foreach($ventas as $venta)
                    <tr>
                        <td>#{{ $venta->id }}</td>
                        <td>{{ $venta->fecha_venta->format('d/m/Y') }}</td>
                        <td>{{ $venta->cliente?->nombre ?: 'Sin cliente' }}</td>
                        <td class="text-center">
                            @if($venta->tipo_inventario === 'subinventario' && $venta->subinventario)
                                <span class="badge badge-info" style="font-size: 9px;">SubInv #{{ $venta->subinventario->id }}</span>
                            @else
                                <span style="font-size: 9px;">General</span>
                            @endif
                        </td>
                        <td class="text-center">
                            @if($venta->esApartado() && $venta->apartado)
                                <span class="badge badge-warning" style="font-size: 8px;">Apt #{{ $venta->apartado->id }}</span>
                            @else
                                <span style="color: #999; font-size: 9px;">-</span>
                            @endif
                        </td>
                        <td class="text-center">{{ $venta->movimientos->count() }}</td>
                        <td class="text-center">{{ $venta->movimientos->sum('cantidad') }}</td>
                        <td>
                            <span class="badge badge-info">
                                {{ $venta->getTipoPagoLabel() }}
                            </span>
                        </td>
                        <td class="text-right">${{ number_format($venta->total, 2) }}</td>
                        <td class="text-center">
                            @if($venta->tiene_envio)
                                <span class="badge badge-info">Sí</span>
                            @else
                                <span style="color: #999;">No</span>
                            @endif
                        </td>
                        <td class="text-center">
                            <span class="badge {{ $venta->estado === 'completada' ? 'badge-success' : ($venta->estado === 'cancelada' ? 'badge-danger' : 'badge-warning') }}">
                                {{ ucfirst($venta->estado) }}
                            </span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="empty">
            No hay ventas para mostrar con los filtros aplicados
        </div>
    @endif

    {{-- ===== SECCIÓN 2: DETALLE DE PRODUCTOS ===== --}}
    @if($ventas->count() > 0)
        <div class="section-break"></div>
        <div class="section-title">DETALLE DE PRODUCTOS POR VENTA</div>

        @foreach($ventas as $venta)
            @if($venta->movimientos->count() > 0)
                <div style="margin-bottom: 20px; padding: 10px; background-color: {{ $venta->estado === 'cancelada' ? '#fee2e2' : '#f9f9f9' }}; border-left: 3px solid {{ $venta->estado === 'cancelada' ? '#ef4444' : '#3B82F6' }};">
                    <strong style="color: #1F2937;">Venta #{{ $venta->id }} - {{ $venta->fecha_venta->format('d/m/Y H:i') }}</strong> 
                    | Cliente: {{ $venta->cliente?->nombre ?: 'Sin cliente' }}
                    | Total: <span style="color: {{ $venta->estado === 'cancelada' ? '#dc2626' : '#10B981' }}; font-weight: bold;">${{ number_format($venta->total, 2) }}</span>
                    @if($venta->estado === 'cancelada')
                        | <strong style="color: #DC2626;">(CANCELADA)</strong>
                    @endif
                </div>

                <table style="margin-bottom: 20px; width: 100%; font-size: 11px;">
                    <thead>
                        <tr style="background-color: #f0f0f0;">
                            <th style="text-align: left; padding: 5px;">Libro</th>
                            <th style="text-align: center; padding: 5px;">Cantidad</th>
                            <th style="text-align: right; padding: 5px;">Precio Unit.</th>
                            <th style="text-align: center; padding: 5px;">Desc. %</th>
                            <th style="text-align: right; padding: 5px;">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($venta->movimientos as $movimiento)
                            @php
                                $subtotal = ($movimiento->precio_unitario - ($movimiento->precio_unitario * $movimiento->descuento / 100)) * $movimiento->cantidad;
                            @endphp
                            <tr style="border-bottom: 1px solid #e0e0e0;">
                                <td style="padding: 5px;">{{ $movimiento->libro?->nombre ?: 'Producto eliminado' }}</td>
                                <td style="text-align: center; padding: 5px;">{{ $movimiento->cantidad }}</td>
                                <td style="text-align: right; padding: 5px;">${{ number_format($movimiento->precio_unitario, 2) }}</td>
                                <td style="text-align: center; padding: 5px;">{{ $movimiento->descuento ? $movimiento->descuento . '%' : '-' }}</td>
                                <td style="text-align: right; padding: 5px; font-weight: bold;">${{ number_format($subtotal, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        @endforeach
    @endif

    <div class="footer">
        <p>Total de ventas: {{ $ventas->count() }}</p>
        <p>Pan de Vida - Sistema de Control Interno</p>
    </div>
</body>
</html>
