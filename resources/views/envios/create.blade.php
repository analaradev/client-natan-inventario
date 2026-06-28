@extends('layouts.app')

@section('title', 'Nuevo Envío')

@section('page-title', 'Nuevo Envío')
@section('page-description', 'Registra un nuevo envío a FedEx')

@section('content')
<x-page-layout 
    title="Registrar Nuevo Envío"
    description="Completa la información del envío y selecciona las ventas"
    button-text="Volver a Envíos"
    button-icon="fas fa-arrow-left"
    :button-route="route('envios.index')"
>
    <x-card>
        <form action="{{ route('envios.store') }}" method="POST" enctype="multipart/form-data" id="envioForm">
            @csrf

            {{-- Mensajes de error generales --}}
            @if($errors->has('error'))
                <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                    <div class="flex items-start">
                        <i class="fas fa-exclamation-circle text-red-500 mt-0.5 mr-3"></i>
                        <div>
                            <p class="text-sm font-medium text-red-800">{{ $errors->first('error') }}</p>
                        </div>
                    </div>
                </div>
            @endif

            @if($errors->any() && !$errors->has('error'))
                <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                    <div class="flex items-start">
                        <i class="fas fa-exclamation-circle text-red-500 mt-0.5 mr-3"></i>
                        <div>
                            <p class="text-sm font-medium text-red-800 mb-2">Por favor corrige los siguientes errores:</p>
                            <ul class="list-disc list-inside text-sm text-red-700 space-y-1">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Guía de FedEx -->
                <div>
                    <label for="guia" class="block text-sm font-medium text-gray-700 mb-2">
                        Número de Guía / Referencia
                    </label>
                    <div class="relative">
                        <span class="absolute left-3 top-2.5 text-gray-400">
                            <i class="fas fa-barcode"></i>
                        </span>
                        <input 
                            type="text" 
                            name="guia" 
                            id="guia" 
                            value="{{ old('guia') }}"
                            placeholder="Ej: 1234567890"
                            class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 @error('guia') border-red-500 @enderror"
                        >
                    </div>
                    <p class="mt-1 text-sm text-gray-500">
                        <i class="fas fa-info-circle"></i> Número de seguimiento de FedEx
                    </p>
                    @error('guia')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Fecha de Envío -->
                <div>
                    <label for="fecha_envio" class="block text-sm font-medium text-gray-700 mb-2">
                        Fecha de Envío <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <span class="absolute left-3 top-2.5 text-gray-400">
                            <i class="fas fa-calendar"></i>
                        </span>
                        <input 
                            type="date" 
                            name="fecha_envio" 
                            id="fecha_envio" 
                            value="{{ old('fecha_envio', date('Y-m-d')) }}"
                            required
                            class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 @error('fecha_envio') border-red-500 @enderror"
                        >
                    </div>
                    @error('fecha_envio')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Comprobante -->
                <div class="lg:col-span-2">
                    <label for="comprobante" class="block text-sm font-medium text-gray-700 mb-2">
                        Comprobante / Factura de FedEx
                    </label>
                    <div class="relative">
                        <input 
                            type="file" 
                            name="comprobante" 
                            id="comprobante" 
                            accept=".pdf,.jpg,.jpeg,.png"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 @error('comprobante') border-red-500 @enderror"
                        >
                    </div>
                    <p class="mt-1 text-sm text-gray-500">
                        <i class="fas fa-info-circle"></i> Formatos permitidos: PDF, JPG, PNG. Máximo 5MB
                    </p>
                    @error('comprobante')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Notas -->
                <div class="lg:col-span-2">
                    <label for="notas" class="block text-sm font-medium text-gray-700 mb-2">
                        Notas Adicionales
                    </label>
                    <textarea 
                        name="notas" 
                        id="notas" 
                        rows="3"
                        placeholder="Información adicional sobre el envío..."
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 @error('notas') border-red-500 @enderror"
                    >{{ old('notas') }}</textarea>
                    <p class="mt-1 text-sm text-gray-500">
                        <i class="fas fa-info-circle"></i> Observaciones o detalles importantes del envío
                    </p>
                    @error('notas')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Sección de Ventas -->
            <div class="mt-8 pt-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">
                        <i class="fas fa-shopping-cart text-primary-600 mr-2"></i>
                        Ventas a Incluir en el Envío
                        <span class="text-red-500">*</span>
                    </h3>
                    <div class="text-sm text-gray-600">
                        <span id="ventasSeleccionadas" class="font-semibold text-primary-600">0</span> ventas seleccionadas
                    </div>
                </div>

                @error('ventas')
                    <div class="mb-6 p-4 bg-red-50 border-l-4 border-red-500 rounded-r-lg">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                            <p class="text-sm text-red-700 font-medium">{{ $message }}</p>
                        </div>
                    </div>
                @enderror

                @if($ventas->count() > 0)
                    <div class="overflow-hidden border border-gray-200 rounded-lg">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left">
                                            <input 
                                                type="checkbox" 
                                                id="selectAll"
                                                class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                                onchange="toggleAllVentas(this)"
                                            >
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            ID
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Cliente
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Fecha
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Estado
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Costo Envío
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Total Venta
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($ventas as $venta)
                                        <tr class="hover:bg-gray-50 transition-colors">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <input 
                                                    type="checkbox" 
                                                    name="ventas[]" 
                                                    value="{{ $venta->id }}"
                                                    id="venta_{{ $venta->id }}"
                                                    class="venta-checkbox rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                                    data-costo-envio="{{ $venta->costo_envio ?? 0 }}"
                                                    onchange="updateVentasCount()"
                                                    {{ in_array($venta->id, old('ventas', [])) ? 'checked' : '' }}
                                                >
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="text-sm font-medium text-gray-900">#{{ $venta->id }}</span>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-sm">
                                                    <div class="font-medium text-gray-900">{{ $venta->cliente?->nombre ?: 'Sin cliente' }}</div>
                                                    @if($venta->cliente?->telefono)
                                                        <div class="text-gray-500">{{ $venta->cliente->telefono }}</div>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="text-sm text-gray-900">
                                                    {{ \Carbon\Carbon::parse($venta->fecha_venta)->format('d/m/Y') }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full {{ $venta->estado_badge }}">
                                                    {{ ucfirst($venta->estado) }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                                <span class="text-sm font-semibold text-blue-600">
                                                    ${{ number_format($venta->costo_envio ?? 0, 2) }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                                <span class="text-sm font-semibold text-gray-900">${{ number_format($venta->total, 2) }}</span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <p class="mt-3 text-sm text-gray-500">
                        <i class="fas fa-info-circle"></i>
                        Solo se muestran ventas <strong>marcadas con "Requiere Envío"</strong> que aún no han sido asignadas a ningún envío.
                    </p>

                    <!-- Resumen de Costos de Envío -->
                    <div class="mt-6 bg-gradient-to-r from-blue-50 to-indigo-50 border-2 border-blue-200 rounded-lg p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="text-sm font-medium text-gray-600 mb-1">
                                    <i class="fas fa-shipping-fast text-blue-600 mr-2"></i>
                                    Total de Costos de Envío
                                </h4>
                                <p class="text-xs text-gray-500">
                                    Suma de los costos de envío de las ventas seleccionadas
                                </p>
                            </div>
                            <div class="text-right">
                                <div class="text-4xl font-bold text-blue-600" id="totalCostoEnvioDisplay">
                                    $0.00
                                </div>
                                <div class="text-xs text-gray-500 mt-1">
                                    <span id="ventasEnTotalDisplay">0</span> ventas
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Monto editable para registrar el cargo real de FedEx -->
                    <div class="mt-6 max-w-sm">
                        <label for="monto_a_pagar" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-dollar-sign text-blue-600 mr-1"></i>
                            Monto a Pagar FedEx <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <span class="absolute left-3 top-2.5 text-gray-400">$</span>
                            <input
                                type="number"
                                name="monto_a_pagar"
                                id="monto_a_pagar"
                                value="{{ old('monto_a_pagar', '0.00') }}"
                                step="0.01"
                                min="0"
                                required
                                class="w-full pl-8 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 @error('monto_a_pagar') border-red-500 @enderror"
                            >
                        </div>
                        <p class="mt-1 text-sm text-gray-500">
                            Puedes ajustar este monto si el cargo real difiere del cálculo de las ventas seleccionadas.
                        </p>
                        @error('monto_a_pagar')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                @else
                    <div class="bg-gray-50 border-2 border-dashed border-gray-300 rounded-lg p-12 text-center ">
                        <div class="flex flex-col items-center">
                            <div class="h-8"> </div> 
                            <div class="w-16 h-16 bg-gray-200 rounded-full flex items-center justify-center mb-4">
                                <i class="fas fa-shopping-cart text-gray-400 text-2xl"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">No hay ventas disponibles</h3>
                            <p class="text-gray-600 mb-6 max-w-md">
                                No hay ventas marcadas con "Requiere Envío" disponibles. Al crear una venta, marca la opción de envío para que aparezca aquí.
                            </p>
                            <a 
                                href="{{ route('ventas.create') }}" 
                                class="inline-flex items-center px-4 py-2 bg-primary-600 text-white text-sm font-medium rounded-lg hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-colors"
                            >
                                <i class="fas fa-plus mr-2"></i>
                                Crear Nueva Venta
                            </a>
                            <div class="h-8"> </div> 
                        </div>
                    </div>
                @endif
            </div>

            <!-- Botones de acción -->
            <div class="flex justify-end gap-4 mt-8 pt-6 border-t border-gray-200">
                <a 
                    href="{{ route('envios.index') }}"
                    class="inline-flex items-center px-4 py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-colors"
                >
                    <i class="fas fa-times mr-2"></i>
                    Cancelar
                </a>
                
                @if($ventas->count() > 0)
                    <button 
                        type="submit" 
                        class="inline-flex items-center px-6 py-2.5 bg-green-600 text-white text-sm font-semibold rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 shadow-md hover:shadow-lg transition-all duration-200"
                    >
                        <i class="fas fa-save mr-2"></i>
                        Guardar Envío
                    </button>
                @else
                    <button 
                        type="button" 
                        disabled
                        style="background-color: #1f2937; color: #ffffff; padding: 0.625rem 1.5rem; font-weight: 600; border-radius: 0.5rem; cursor: not-allowed; display: inline-flex; align-items: center; font-size: 0.875rem; box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);"
                    >
                        <i class="fas fa-save mr-2"></i>
                        Guardar Envío
                    </button>
                @endif
            </div>
        </form>
    </x-card>
</x-page-layout>

<script>
    // Actualizar contador de ventas seleccionadas y calcular total de costos de envío
    function updateVentasCount() {
        const checkboxes = document.querySelectorAll('.venta-checkbox:checked');
        const count = checkboxes.length;
        
        // Actualizar contador en el header
        document.getElementById('ventasSeleccionadas').textContent = count;
        
        // Calcular total de costos de envío
        let totalCostoEnvio = 0;
        checkboxes.forEach(checkbox => {
            const costoEnvio = parseFloat(checkbox.getAttribute('data-costo-envio')) || 0;
            totalCostoEnvio += costoEnvio;
        });
        
        // Actualizar el display del total (grande)
        const totalDisplay = document.getElementById('totalCostoEnvioDisplay');
        if (totalDisplay) {
            totalDisplay.textContent = '$' + totalCostoEnvio.toFixed(2);
        }
        
        // Actualizar el contador de ventas en el resumen
        const ventasEnTotal = document.getElementById('ventasEnTotalDisplay');
        if (ventasEnTotal) {
            ventasEnTotal.textContent = count;
        }
        
        // Actualizar el campo hidden para enviar el valor
        const montoAPagarInput = document.getElementById('monto_a_pagar');
        if (montoAPagarInput) {
            montoAPagarInput.value = totalCostoEnvio.toFixed(2);
        }
    }

    // Toggle individual venta
    function toggleVenta(ventaId) {
        const checkbox = document.getElementById(`venta_${ventaId}`);
        checkbox.checked = !checkbox.checked;
        updateVentasCount();
    }

    // Seleccionar/deseleccionar todas
    function toggleAllVentas(checkbox) {
        const checkboxes = document.querySelectorAll('.venta-checkbox');
        checkboxes.forEach(cb => {
            cb.checked = checkbox.checked;
        });
        updateVentasCount();
    }

    // Inicializar contador al cargar
    document.addEventListener('DOMContentLoaded', function() {
        updateVentasCount();
    });
</script>
@endsection
