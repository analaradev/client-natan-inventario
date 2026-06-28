<x-card title="Ingresos incluidos" icon="fas fa-receipt">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Hora</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Concepto</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Referencia</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Metodo</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Origen</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Monto</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($ingresos as $ingreso)
                    <tr>
                        <td class="px-4 py-3 text-sm text-gray-700">{{ $ingreso->fecha?->format('H:i') ?? '--:--' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-900">{{ \App\Models\IngresoCaja::conceptos()[$ingreso->concepto] ?? ucfirst($ingreso->concepto) }}</td>
                        <td class="px-4 py-3 text-sm text-gray-700">
                            @if($ingreso->venta_id)
                                Venta #{{ $ingreso->venta_id }}
                            @elseif($ingreso->apartado_id)
                                Apartado {{ $ingreso->apartado->folio ?? ('#' . $ingreso->apartado_id) }}
                            @else
                                -
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-700">{{ \App\Models\IngresoCaja::metodosPago()[$ingreso->metodo_pago] ?? $ingreso->metodo_pago }}</td>
                        <td class="px-4 py-3 text-sm text-gray-700">
                            @if($ingreso->tipo_inventario === 'subinventario')
                                {{ $ingreso->subinventario->descripcion ?? 'Subinventario' }}
                            @else
                                Inventario general
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-right font-semibold">${{ number_format($ingreso->monto, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-gray-500">No hay ingresos para mostrar.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-card>
