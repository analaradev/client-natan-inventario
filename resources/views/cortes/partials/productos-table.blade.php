<x-card title="Productos del corte">
    <div class="space-y-4">
        @forelse($productos as $producto)
            <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition-colors">
                <div class="flex items-start gap-4">
                    <div class="flex-shrink-0">
                        <div class="w-16 h-20 bg-gradient-to-br from-primary-100 to-primary-200 rounded-lg flex items-center justify-center">
                            <i class="fas fa-book text-primary-600 text-2xl"></i>
                        </div>
                    </div>

                    <div class="flex-1 min-w-0">
                        <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-3 mb-3">
                            <div class="min-w-0">
                                <h3 class="font-semibold text-gray-900 text-base break-words">{{ $producto['nombre'] }}</h3>
                                <p class="text-sm text-gray-600">
                                    Codigo: <span class="font-mono">{{ $producto['codigo'] }}</span>
                                </p>
                                <p class="text-sm text-gray-600">{{ $producto['referencias'] }}</p>
                            </div>
                            <span class="inline-flex items-center self-start rounded-full px-2.5 py-1 text-xs font-semibold {{ $producto['operacion'] === 'Venta' ? 'bg-green-100 text-green-800' : ($producto['operacion'] === 'Apartado liquidado' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800') }}">
                                {{ $producto['operacion'] }}
                            </span>
                        </div>

                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div>
                                <p class="text-xs text-gray-500 mb-1">Caja de</p>
                                <p class="text-sm font-semibold text-gray-900">{{ $producto['caja'] }}</p>
                            </div>

                            <div>
                                <p class="text-xs text-gray-500 mb-1">Cantidad</p>
                                <p class="text-sm font-semibold text-gray-900">{{ number_format($producto['cantidad']) }} unidad(es)</p>
                            </div>

                            <div>
                                <p class="text-xs text-gray-500 mb-1">Precio</p>
                                <p class="text-sm font-semibold text-gray-900">${{ number_format($producto['precio_unitario'], 2) }}</p>
                            </div>

                            <div>
                                <p class="text-xs text-gray-500 mb-1">Total</p>
                                <p class="text-sm font-bold text-primary-600">${{ number_format($producto['total'], 2) }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="text-center py-12">
                <i class="fas fa-book text-gray-300 text-5xl mb-3"></i>
                <p class="text-gray-500 font-medium">No hay productos relacionados con este corte</p>
            </div>
        @endforelse
    </div>
</x-card>
