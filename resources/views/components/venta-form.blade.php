{{--
    Componente: Venta Form
    Props: venta, action, method, submitText, codigo, libros
--}}

@props([
    'venta' => null,
    'action',
    'method' => 'POST',
    'submitText' => 'Guardar',
    'libros' => [],
    'subinventarios' => []
])

@php
    $oldLibros = old('libros', []);
    $libroCount = !empty($oldLibros) ? count($oldLibros) : ($venta ? $venta->movimientos->count() : 0);
    
    // Obtener datos del cliente cuando hay old() (después de error de validación)
    $oldClienteId = old('cliente_id');
    $selectedClienteId = $oldClienteId ?? $venta?->cliente_id ?? null;
    $selectedCliente = null;
    
    if ($selectedClienteId) {
        if ($venta && $venta->cliente) {
            $selectedCliente = $venta->cliente;
        } elseif ($oldClienteId) {
            // Buscar el cliente en la base de datos cuando viene de old()
            $selectedCliente = \App\Models\Cliente::find($oldClienteId);
        }
    }

    $subinventariosCollection = collect($subinventarios);
    $soloSubinventarioId = $subinventariosCollection->count() === 1 ? $subinventariosCollection->first()?->id : null;
@endphp

<form action="{{ $action }}" method="POST" id="ventaForm" data-libro-index="{{ $libroCount }}" data-cliente-selected="{{ $selectedCliente ? json_encode(['nombre' => $selectedCliente->nombre, 'telefono' => $selectedCliente->telefono]) : '' }}">
    @csrf
    @if($method !== 'POST')
        @method($method)
    @endif

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
        <!-- Tipo de Inventario -->
        @if(!$venta)
        <div class="lg:col-span-2">
            <label for="tipo_inventario" class="block text-sm font-medium text-gray-700 mb-2">
                Tipo de Inventario <span class="text-red-500">*</span>
            </label>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Inventario General -->
                <label class="relative block cursor-pointer">
                    <input 
                        type="radio" 
                        name="tipo_inventario" 
                        value="general" 
                        id="tipo_inventario_general"
                        {{ old('tipo_inventario', 'general') == 'general' ? 'checked' : '' }}
                        class="radio-inventario sr-only"
                        onchange="toggleInventarioTipo()"
                    >
                    <div class="inventario-box p-4 bg-white border-2 border-gray-200 rounded-lg transition-all hover:border-blue-300 hover:shadow-md">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <div class="inventario-icon flex-shrink-0 w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center transition-colors">
                                    <i class="fas fa-warehouse text-xl text-blue-600"></i>
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-900">Inventario General</p>
                                    <p class="text-sm text-gray-500">Stock disponible en bodega</p>
                                </div>
                            </div>
                            <div class="flex-shrink-0">
                                <div class="inventario-check w-6 h-6 border-2 border-gray-300 rounded-full flex items-center justify-center transition-all">
                                    <i class="fas fa-check text-xs text-white opacity-0"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </label>

                <!-- Subinventario -->
                <label class="relative block cursor-pointer">
                    <input 
                        type="radio" 
                        name="tipo_inventario" 
                        value="subinventario"
                        id="tipo_inventario_subinventario" 
                        {{ old('tipo_inventario') == 'subinventario' ? 'checked' : '' }}
                        class="radio-inventario sr-only"
                        onchange="toggleInventarioTipo()"
                    >
                    <div class="inventario-box p-4 bg-white border-2 border-gray-200 rounded-lg transition-all hover:border-green-300 hover:shadow-md">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <div class="inventario-icon flex-shrink-0 w-12 h-12 bg-green-100 rounded-full flex items-center justify-center transition-colors">
                                    <i class="fas fa-box-open text-xl text-green-600"></i>
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-900">Subinventario</p>
                                    <p class="text-sm text-gray-500">Stock asignado a punto de venta</p>
                                </div>
                            </div>
                            <div class="flex-shrink-0">
                                <div class="inventario-check w-6 h-6 border-2 border-gray-300 rounded-full flex items-center justify-center transition-all">
                                    <i class="fas fa-check text-xs text-white opacity-0"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </label>
            </div>
            
            <style>
                /* Inventario General - Azul */
                input[type="radio"][value="general"]:checked ~ .inventario-box {
                    border-color: #3B82F6 !important;
                    background-color: #EFF6FF !important;
                }
                input[type="radio"][value="general"]:checked ~ .inventario-box .inventario-icon {
                    background-color: #3B82F6 !important;
                }
                input[type="radio"][value="general"]:checked ~ .inventario-box .inventario-icon i {
                    color: white !important;
                }
                input[type="radio"][value="general"]:checked ~ .inventario-box .inventario-check {
                    background-color: #3B82F6 !important;
                    border-color: #3B82F6 !important;
                }
                input[type="radio"][value="general"]:checked ~ .inventario-box .inventario-check i {
                    opacity: 1 !important;
                }
                
                /* Subinventario - Verde */
                input[type="radio"][value="subinventario"]:checked ~ .inventario-box {
                    border-color: #10B981 !important;
                    background-color: #ECFDF5 !important;
                }
                input[type="radio"][value="subinventario"]:checked ~ .inventario-box .inventario-icon {
                    background-color: #10B981 !important;
                }
                input[type="radio"][value="subinventario"]:checked ~ .inventario-box .inventario-icon i {
                    color: white !important;
                }
                input[type="radio"][value="subinventario"]:checked ~ .inventario-box .inventario-check {
                    background-color: #10B981 !important;
                    border-color: #10B981 !important;
                }
                input[type="radio"][value="subinventario"]:checked ~ .inventario-box .inventario-check i {
                    opacity: 1 !important;
                }
            </style>
            @error('tipo_inventario')
                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
            @enderror
        </div>
        @endif

        <!-- Selección de Subinventario (solo visible cuando se selecciona subinventario) -->
        @if(!$venta)
        <div id="subinventarioSelector" class="lg:col-span-2" style="display: none;">
            <label for="subinventario_id" class="block text-sm font-medium text-gray-700 mb-2">
                Seleccionar Subinventario <span class="text-red-500">*</span>
            </label>
            <div class="relative">
                <span class="absolute left-3 top-2.5 text-gray-400">
                    <i class="fas fa-box-open"></i>
                </span>
                <select 
                    name="subinventario_id" 
                    id="subinventario_id" 
                    class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 @error('subinventario_id') border-red-500 @enderror"
                    onchange="cargarLibrosSubinventario()"
                >
                    <option value="">Selecciona un subinventario</option>
                    @foreach($subinventarios as $sub)
                        <option value="{{ $sub->id }}" 
                            {{ old('subinventario_id', $soloSubinventarioId) == $sub->id ? 'selected' : '' }}
                            data-libros="{{ json_encode($sub->libros_data) }}">
                            SubInventario #{{ $sub->id }} - {{ $sub->descripcion ?? 'Sin descripción' }} 
                            ({{ $sub->fecha_subinventario->format('d/m/Y') }}) - {{ $sub->libros->count() }} libros
                        </option>
                    @endforeach
                </select>
            </div>
            @error('subinventario_id')
                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
            @enderror
        </div>
        @endif

        <!-- Cliente -->
        <div class="lg:col-span-2">
            <x-cliente-search-dynamic 
                name="cliente_id"
                :selected="$selectedClienteId"
                :clienteData="$selectedCliente"
                label="Cliente (Opcional)"
                :required="false"
            />
        </div>

        <!-- Nombre opcional del cliente -->
        <div class="lg:col-span-2">
            <label for="cliente_referencia" class="block text-sm font-medium text-gray-700 mb-2">
                Nombre del cliente (Opcional)
            </label>
            <div class="relative">
                <span class="absolute left-3 top-2.5 text-gray-400">
                    <i class="fas fa-user-tag"></i>
                </span>
                <input
                    type="text"
                    name="cliente_referencia"
                    id="cliente_referencia"
                    maxlength="255"
                    value="{{ old('cliente_referencia', $venta?->cliente_referencia) }}"
                    class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 @error('cliente_referencia') border-red-500 @enderror"
                    placeholder="Ej. Juan Perez">
            </div>
            <p class="mt-1 text-sm text-gray-500">
                <i class="fas fa-info-circle"></i> Texto libre para identificar a quien se le hizo la venta o descuento
            </p>
            @error('cliente_referencia')
                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <!-- Fecha de Venta -->
        <div>
            <label for="fecha_venta" class="block text-sm font-medium text-gray-700 mb-2">
                Fecha de Venta <span class="text-red-500">*</span>
            </label>
            <div class="relative">
                <span class="absolute left-3 top-2.5 text-gray-400">
                    <i class="fas fa-calendar"></i>
                </span>
                <input 
                    type="date" 
                    name="fecha_venta" 
                    id="fecha_venta" 
                    value="{{ old('fecha_venta', $venta?->fecha_venta?->format('Y-m-d') ?? date('Y-m-d')) }}"
                    required
                    class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 @error('fecha_venta') border-red-500 @enderror">
            </div>
            @error('fecha_venta')
                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <input type="hidden" name="tipo_pago" value="contado">

        <!-- Metodo de Pago -->
        <div>
            <label for="metodo_pago" class="block text-sm font-medium text-gray-700 mb-2">
                Metodo de Pago
            </label>
            <div class="relative">
                <span class="absolute left-3 top-2.5 text-gray-400">
                    <i class="fas fa-wallet"></i>
                </span>
                <select 
                    name="metodo_pago" 
                    id="metodo_pago" 
                    class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 @error('metodo_pago') border-red-500 @enderror">
                    <option value="no_especificado" {{ old('metodo_pago', $venta?->metodo_pago ?? 'no_especificado') == 'no_especificado' ? 'selected' : '' }}>No especificado</option>
                    <option value="efectivo" {{ old('metodo_pago', $venta?->metodo_pago) == 'efectivo' ? 'selected' : '' }}>Efectivo</option>
                    <option value="transferencia" {{ old('metodo_pago', $venta?->metodo_pago) == 'transferencia' ? 'selected' : '' }}>Transferencia</option>
                    <option value="tarjeta" {{ old('metodo_pago', $venta?->metodo_pago) == 'tarjeta' ? 'selected' : '' }}>Tarjeta</option>
                </select>
            </div>
            <p class="mt-1 text-sm text-gray-500">
                <i class="fas fa-info-circle"></i> Se usa para el corte de caja
            </p>
            @error('metodo_pago')
                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <!-- Descuento Global -->
        <div>
            <label for="descuento_global" class="block text-sm font-medium text-gray-700 mb-2">
                Descuento Global (%)
            </label>
            <div class="relative">
                <span class="absolute left-3 top-2.5 text-gray-400">
                    <i class="fas fa-percent"></i>
                </span>
                <input 
                    type="number" 
                    name="descuento_global" 
                    id="descuento_global" 
                    step="0.01"
                    min="0"
                    max="100"
                    value="{{ old('descuento_global', $venta?->descuento_global ?? 0) }}"
                    class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 @error('descuento_global') border-red-500 @enderror"
                    placeholder="0">
            </div>
            <p class="mt-1 text-sm text-gray-500">
                <i class="fas fa-info-circle"></i> Se aplicará sobre el total de la venta
            </p>
            @error('descuento_global')
                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <!-- Tiene Envío -->
        <div class="lg:col-span-2">
            <div class="flex items-start space-x-3 p-4 bg-gray-50 border border-gray-200 rounded-lg">
                <input 
                    type="checkbox" 
                    name="tiene_envio" 
                    id="tiene_envio" 
                    value="1"
                    {{ old('tiene_envio', $venta?->tiene_envio) ? 'checked' : '' }}
                    class="mt-1 h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded">
                <div class="flex-1">
                    <label for="tiene_envio" class="block text-sm font-medium text-gray-900 cursor-pointer">
                        <i class="fas fa-shipping-fast text-gray-600"></i> Requiere Envío
                    </label>
                    <p class="text-sm text-gray-600 mt-1">
                        <i class="fas fa-info-circle"></i> 
                        Esta venta será incluida en un envío a FedEx. Podrás asignarla posteriormente en el módulo de Envíos.
                    </p>
                </div>
            </div>
        </div>

        <!-- Costo de Envío (visible solo si tiene_envio está marcado) -->
        <div id="costoEnvioContainer" class="lg:col-span-2 hidden">
            <label for="costo_envio" class="block text-sm font-medium text-gray-700 mb-2">
                Costo del Envío
            </label>
            <div class="relative">
                <span class="absolute left-3 top-2.5 text-gray-400">
                    <i class="fas fa-dollar-sign"></i>
                </span>
                <input 
                    type="number" 
                    name="costo_envio" 
                    id="costo_envio" 
                    value="{{ old('costo_envio', $venta?->costo_envio ?? 0) }}"
                    step="0.01"
                    min="0"
                    placeholder="0.00"
                    class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 @error('costo_envio') border-red-500 @enderror">
            </div>
            <p class="mt-1 text-sm text-gray-500">
                <i class="fas fa-info-circle"></i> Ingresa el costo del envío si aplica
            </p>
            @error('costo_envio')
                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <!-- Observaciones -->
        <div class="lg:col-span-2">
            <label for="observaciones" class="block text-sm font-medium text-gray-700 mb-2">
                Observaciones
            </label>
            <textarea 
                name="observaciones" 
                id="observaciones" 
                rows="3"
                maxlength="500"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 @error('observaciones') border-red-500 @enderror"
                placeholder="Notas adicionales sobre esta venta...">{{ old('observaciones', $venta?->observaciones) }}</textarea>
            <p class="mt-1 text-sm text-gray-500">
                <i class="fas fa-info-circle"></i> Máximo 500 caracteres
            </p>
            @error('observaciones')
                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <!-- Sección de Libros -->
        <div class="lg:col-span-2 border-t border-gray-200 pt-6 mt-2">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                    <i class="fas fa-books text-primary-600 mr-2"></i>
                    Libros de la Venta
                </h3>
                <x-button 
                    type="button" 
                    id="addLibroBtn"
                    variant="success" 
                    size="sm"
                    icon="fas fa-plus">
                    Agregar Libro
                </x-button>
            </div>

            <!-- Contenedor de Libros -->
            <div id="librosContainer" class="space-y-4">
                @php
                    $oldLibros = old('libros', []);
                    $hasOldLibros = !empty($oldLibros);
                    $hasVentaMovimientos = $venta && $venta->movimientos->count() > 0;
                @endphp

                @if($hasOldLibros)
                    {{-- Renderizar libros desde old() cuando hay error de validación --}}
                    @foreach($oldLibros as $index => $oldLibro)
                        <x-libro-item 
                            :libros="$libros" 
                            :index="$index"
                            :oldData="$oldLibro" />
                    @endforeach
                @elseif($hasVentaMovimientos)
                    {{-- Renderizar libros de la venta existente --}}
                    @foreach($venta->movimientos as $index => $movimiento)
                        <x-libro-item 
                            :libros="$libros" 
                            :index="$index" 
                            :movimiento="$movimiento" />
                    @endforeach
                @endif
            </div>

            <!-- Mensaje cuando no hay libros -->
            @if(!$hasOldLibros && (!$venta || $venta->movimientos->count() === 0))
                <div id="emptyMessage" class="text-center py-12 text-gray-500 bg-gray-50 rounded-lg border-2 border-dashed border-gray-300">
                    <i class="fas fa-book text-4xl mb-3"></i>
                    <p>No hay libros agregados. Haz clic en "Agregar Libro" para empezar.</p>
                </div>
            @endif
        </div>

        <!-- Totales -->
        <div class="lg:col-span-2 bg-gray-50 rounded-lg p-4 border border-gray-200">
            <div class="space-y-2">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">Subtotal:</span>
                    <span class="font-semibold text-gray-900" id="subtotalDisplay">$0.00</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">Descuento:</span>
                    <span class="font-semibold text-red-600" id="descuentoDisplay">-$0.00</span>
                </div>
                <div class="flex justify-between text-sm" id="costoEnvioDisplayContainer" style="display: none;">
                    <span class="text-blue-600">
                        <i class="fas fa-shipping-fast mr-1"></i> Costo de Envío:
                    </span>
                    <span class="font-semibold text-blue-600" id="costoEnvioDisplay">+$0.00</span>
                </div>
                <div class="flex justify-between text-lg font-bold border-t border-gray-300 pt-2">
                    <span class="text-gray-900">Total:</span>
                    <span class="text-primary-600" id="totalDisplay">$0.00</span>
                </div>
            </div>
        </div>

        <!-- Botones de Acción -->
        <div class="lg:col-span-2 flex justify-end gap-3 pt-4 border-t border-gray-200">
            <x-button 
                type="button" 
                variant="secondary" 
                icon="fas fa-times" 
                onclick="window.location='{{ route('ventas.index') }}'">
                Cancelar
            </x-button>
            <x-button 
                type="submit" 
                variant="primary" 
                icon="fas fa-save">
                {{ $submitText }}
            </x-button>
        </div>
    </div>
</form>

<!-- Template para nuevos libros -->
<template id="libroTemplate">
    <x-libro-item :libros="$libros" />
</template>

{{-- Cargar script externo --}}
@push('scripts')
<script>
    // Global libros data for libro search components
    window.ventaLibrosData = @json($libros);
    window.subinventariosData = @json($subinventarios);
</script>
@if(!$venta)
<script src="{{ asset('js/venta-inventario-toggle.js') }}"></script>
@endif
<script>
    // Gestión del campo Costo de Envío
    document.addEventListener('DOMContentLoaded', function() {
        const checkboxEnvio = document.getElementById('tiene_envio');
        const costoEnvioContainer = document.getElementById('costoEnvioContainer');
        const costoEnvioInput = document.getElementById('costo_envio');
        
        function toggleCostoEnvio() {
            if (checkboxEnvio.checked) {
                costoEnvioContainer.classList.remove('hidden');
            } else {
                costoEnvioContainer.classList.add('hidden');
                costoEnvioInput.value = '0'; // Resetear el valor si se desmarca
            }
        }
        
        // Evento al cambiar el checkbox de envío
        checkboxEnvio.addEventListener('change', toggleCostoEnvio);
        
        // Ejecutar al cargar para mantener el estado si hay old() values
        toggleCostoEnvio();

        // Prevenir que el botón quede deshabilitado al mostrar alert de validación
        const ventaForm = document.getElementById('ventaForm');
        if (ventaForm) {
            ventaForm.addEventListener('submit', function(e) {
                const submitButton = ventaForm.querySelector('button[type="submit"]');
                if (submitButton) {
                    // Si la validación falla (el alert se muestra), reactivar el botón después
                    setTimeout(() => {
                        submitButton.disabled = false;
                        submitButton.classList.remove('opacity-50', 'cursor-not-allowed');
                    }, 100);
                }
            });
        }
    });
</script>
<script src="{{ asset('js/cliente-search-dynamic.js') }}"></script>
<script src="{{ asset('js/libro-search-dynamic.js') }}"></script>
<script src="{{ asset('js/venta-form.js') }}"></script>
@endpush
