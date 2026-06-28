<x-card title="Ingresos incluidos">
    <div class="space-y-4">
        @forelse($ingresos as $ingreso)
            <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition-colors">
                <div class="flex items-start gap-4">
                    <div class="flex-shrink-0">
                        <div class="w-14 h-14 bg-green-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-receipt text-green-600 text-lg"></i>
                        </div>
                    </div>

                    <div class="flex-1 min-w-0">
                        <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-3 mb-3">
                            <div class="min-w-0">
                                <h3 class="font-semibold text-gray-900">
                                    {{ \App\Models\IngresoCaja::conceptos()[$ingreso->concepto] ?? ucfirst($ingreso->concepto) }}
                                </h3>
                                <p class="text-sm text-gray-600">
                                    @if($ingreso->venta_id)
                                        Venta #{{ $ingreso->venta_id }}
                                    @elseif($ingreso->apartado_id)
                                        Apartado {{ $ingreso->apartado->folio ?? ('#' . $ingreso->apartado_id) }}
                                    @else
                                        Sin referencia
                                    @endif
                                </p>
                            </div>
                            <div class="text-left md:text-right">
                                <p class="text-xl font-bold text-green-600">${{ number_format($ingreso->monto, 2) }}</p>
                                <p class="text-xs text-gray-500">{{ $ingreso->fecha?->format('H:i') ?? '--:--' }}</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <p class="text-xs text-gray-500 mb-1">Metodo</p>
                                <p class="text-sm font-semibold text-gray-900">
                                    {{ \App\Models\IngresoCaja::metodosPago()[$ingreso->metodo_pago] ?? $ingreso->metodo_pago }}
                                </p>
                            </div>

                            <div>
                                <p class="text-xs text-gray-500 mb-1">Caja de</p>
                                <p class="text-sm font-semibold text-gray-900">
                                    @if($ingreso->tipo_inventario === 'subinventario')
                                        {{ $ingreso->subinventario->descripcion ?? 'Subinventario' }}
                                    @else
                                        Inventario general
                                    @endif
                                </p>
                            </div>

                            <div>
                                <p class="text-xs text-gray-500 mb-1">Usuario</p>
                                <p class="text-sm font-semibold text-gray-900">{{ $ingreso->usuario ?: 'Sin usuario' }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="text-center py-12">
                <i class="fas fa-receipt text-gray-300 text-5xl mb-3"></i>
                <p class="text-gray-500 font-medium">No hay ingresos para mostrar</p>
            </div>
        @endforelse
    </div>
</x-card>
