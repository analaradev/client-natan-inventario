<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Corte de Caja #{{ $corte->id }}</title>
    <style>
        {!! $styles !!}

        .section-title {
            font-size: 14px;
            font-weight: bold;
            margin: 20px 0 10px 0;
            padding-bottom: 5px;
            border-bottom: 2px solid #1F2937;
        }

        .page-break {
            page-break-before: always;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>CORTE DE CAJA #{{ $corte->id }}</h1>
        <p>Fecha del corte: {{ $corte->fecha_corte->format('d/m/Y') }} | Generado el {{ date('d/m/Y H:i:s') }}</p>
    </div>

    <div class="summary">
        <table style="width: 100%; border: none;">
            <tr>
                <td style="border: none; padding: 5px;">
                    <span class="summary-label">Sistema:</span>
                    <span class="summary-value">${{ number_format($corte->total_sistema, 2) }}</span>
                </td>
                <td style="border: none; padding: 5px;">
                    <span class="summary-label">Reportado:</span>
                    <span class="summary-value">${{ number_format($corte->total_reportado, 2) }}</span>
                </td>
                <td style="border: none; padding: 5px;">
                    <span class="summary-label">Diferencia:</span>
                    <span class="summary-value">${{ number_format($corte->diferencia, 2) }}</span>
                </td>
                <td style="border: none; padding: 5px;">
                    <span class="summary-label">Ingresos:</span>
                    <span class="summary-value">{{ $corte->ingresos->count() }}</span>
                </td>
            </tr>
        </table>
    </div>

    <div class="section-title">DATOS DEL CIERRE</div>
    <table>
        <tbody>
            <tr>
                <td><strong>Caja de</strong></td>
                <td>
                    @if($corte->tipo_inventario === 'subinventario')
                        {{ $corte->subinventario->descripcion ?? 'Subinventario' }}
                    @elseif($corte->tipo_inventario === 'general')
                        Inventario general
                    @else
                        Todos
                    @endif
                </td>
                <td><strong>Cerrado por</strong></td>
                <td>{{ $corte->usuario_cierre }}</td>
            </tr>
            <tr>
                <td><strong>Observaciones</strong></td>
                <td colspan="3">{{ $corte->observaciones ?: 'Sin observaciones' }}</td>
            </tr>
        </tbody>
    </table>

    <div class="section-title">METODO DE PAGO</div>
    <table>
        <thead>
            <tr>
                <th>Metodo</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach(\App\Models\IngresoCaja::metodosPago() as $key => $label)
                <tr>
                    <td>{{ $label }}</td>
                    <td class="text-right">${{ number_format($resumen['metodos'][$key] ?? 0, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="page-break"></div>
    <div class="section-title">INGRESOS INCLUIDOS</div>
    <table>
        <thead>
            <tr>
                <th>Hora</th>
                <th>Concepto</th>
                <th>Referencia</th>
                <th>Metodo</th>
                <th>Caja de</th>
                <th class="text-right">Monto</th>
                <th>Usuario</th>
            </tr>
        </thead>
        <tbody>
            @foreach($corte->ingresos as $ingreso)
                <tr>
                    <td>{{ $ingreso->fecha?->format('H:i') ?? '--:--' }}</td>
                    <td>{{ \App\Models\IngresoCaja::conceptos()[$ingreso->concepto] ?? ucfirst($ingreso->concepto) }}</td>
                    <td>
                        @if($ingreso->venta_id)
                            Venta #{{ $ingreso->venta_id }}
                        @elseif($ingreso->apartado_id)
                            Apartado {{ $ingreso->apartado->folio ?? ('#' . $ingreso->apartado_id) }}
                        @else
                            -
                        @endif
                    </td>
                    <td>{{ \App\Models\IngresoCaja::metodosPago()[$ingreso->metodo_pago] ?? $ingreso->metodo_pago }}</td>
                    <td>
                        @if($ingreso->tipo_inventario === 'subinventario')
                            {{ $ingreso->subinventario->descripcion ?? 'Subinventario' }}
                        @else
                            Inventario general
                        @endif
                    </td>
                    <td class="text-right">${{ number_format($ingreso->monto, 2) }}</td>
                    <td>{{ $ingreso->usuario }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="page-break"></div>
    <div class="section-title">PRODUCTOS VENDIDOS Y APARTADOS RELACIONADOS</div>
    @if($productos->count() > 0)
        <table>
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Codigo</th>
                    <th>Operacion</th>
                    <th>Referencias</th>
                    <th>Caja de</th>
                    <th class="text-right">Cantidad</th>
                    <th class="text-right">Precio</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($productos as $producto)
                    <tr>
                        <td>{{ $producto['nombre'] }}</td>
                        <td>{{ $producto['codigo'] }}</td>
                        <td>{{ $producto['operacion'] }}</td>
                        <td>{{ $producto['referencias'] }}</td>
                        <td>{{ $producto['caja'] }}</td>
                        <td class="text-right">{{ number_format($producto['cantidad']) }}</td>
                        <td class="text-right">${{ number_format($producto['precio_unitario'], 2) }}</td>
                        <td class="text-right">${{ number_format($producto['total'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="empty">
            No hay productos relacionados con este corte
        </div>
    @endif

    <div class="footer">
        <p>Pan de Vida - Sistema de Control Interno</p>
    </div>
</body>
</html>
