<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Cortes de Caja</title>
    <style>
        {!! $styles !!}
    </style>
</head>
<body>
    <div class="header">
        <h1>REPORTE DE CORTES DE CAJA</h1>
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

    <div class="summary">
        <table style="width: 100%; border: none;">
            <tr>
                <td style="border: none; padding: 5px;">
                    <span class="summary-label">Cortes:</span>
                    <span class="summary-value">{{ $estadisticas['cantidad'] }}</span>
                </td>
                <td style="border: none; padding: 5px;">
                    <span class="summary-label">Sistema:</span>
                    <span class="summary-value">${{ number_format($estadisticas['total_sistema'], 2) }}</span>
                </td>
                <td style="border: none; padding: 5px;">
                    <span class="summary-label">Reportado:</span>
                    <span class="summary-value">${{ number_format($estadisticas['total_reportado'], 2) }}</span>
                </td>
                <td style="border: none; padding: 5px;">
                    <span class="summary-label">Diferencia:</span>
                    <span class="summary-value">${{ number_format($estadisticas['diferencia'], 2) }}</span>
                </td>
            </tr>
        </table>
    </div>

    @if($cortes->count() > 0)
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Fecha</th>
                    <th>Caja de</th>
                    <th class="text-right">Efectivo</th>
                    <th class="text-right">Tarjeta</th>
                    <th class="text-right">Transferencia</th>
                    <th class="text-right">No especificado</th>
                    <th class="text-right">Sistema</th>
                    <th class="text-right">Reportado</th>
                    <th class="text-right">Diferencia</th>
                    <th>Usuario</th>
                </tr>
            </thead>
            <tbody>
                @foreach($cortes as $corte)
                    <tr>
                        <td>#{{ $corte->id }}</td>
                        <td>{{ $corte->fecha_corte->format('d/m/Y') }}</td>
                        <td>
                            @if($corte->tipo_inventario === 'subinventario')
                                {{ $corte->subinventario->descripcion ?? 'Subinventario' }}
                            @elseif($corte->tipo_inventario === 'general')
                                Inventario general
                            @else
                                Todos
                            @endif
                        </td>
                        <td class="text-right">${{ number_format($corte->total_efectivo, 2) }}</td>
                        <td class="text-right">${{ number_format($corte->total_tarjeta, 2) }}</td>
                        <td class="text-right">${{ number_format($corte->total_transferencia, 2) }}</td>
                        <td class="text-right">${{ number_format($corte->total_no_especificado, 2) }}</td>
                        <td class="text-right">${{ number_format($corte->total_sistema, 2) }}</td>
                        <td class="text-right">${{ number_format($corte->total_reportado, 2) }}</td>
                        <td class="text-right">${{ number_format($corte->diferencia, 2) }}</td>
                        <td>{{ $corte->usuario_cierre }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="empty">
            No hay cortes para mostrar con los filtros aplicados
        </div>
    @endif

    <div class="footer">
        <p>Total de cortes: {{ $cortes->count() }}</p>
        <p>Pan de Vida - Sistema de Control Interno</p>
    </div>
</body>
</html>
