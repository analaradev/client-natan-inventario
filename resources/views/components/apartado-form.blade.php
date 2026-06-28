{{--
    Componente: Apartado Form
    Props: apartado, action, method, submitText, libros, clientes
--}}

@props([
    'apartado' => null,
    'action',
    'method' => 'POST',
    'submitText' => 'Guardar',
    'libros' => [],
    'clientes' => [],
    'subinventarios' => []
])

@php
    $oldLibros = old('libros', []);
    $libroCount = !empty($oldLibros) ? count($oldLibros) : ($apartado ? $apartado->detalles->count() : 0);
@endphp

<form action="{{ $action }}" method="POST" id="apartadoForm" data-libro-index="{{ $libroCount }}">
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
        @if(!$apartado)
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
        @if(!$apartado)
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
                    @foreach($subinventarios as $subinventario)
                        <option value="{{ $subinventario->id }}" {{ old('subinventario_id') == $subinventario->id ? 'selected' : '' }}>
                            Subinventario #{{ $subinventario->id }} - {{ $subinventario->descripcion ?? 'Sin descripción' }} ({{ $subinventario->fecha_subinventario->format('d/m/Y') }}) - {{ $subinventario->libros->count() }} libros
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
            <label for="cliente_id" class="block text-sm font-medium text-gray-700 mb-2">
                Cliente <span class="text-red-500">*</span>
            </label>
            <div class="relative">
                <span class="absolute left-3 top-2.5 text-gray-400">
                    <i class="fas fa-user"></i>
                </span>
                <select 
                    name="cliente_id" 
                    id="cliente_id" 
                    required
                    class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 @error('cliente_id') border-red-500 @enderror">
                    <option value="">Selecciona un cliente</option>
                    @foreach($clientes as $cliente)
                        <option value="{{ $cliente->id }}" {{ old('cliente_id', $apartado?->cliente_id) == $cliente->id ? 'selected' : '' }}>
                            {{ $cliente->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>
            @error('cliente_id')
                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <!-- Fecha de Apartado -->
        <div>
            <label for="fecha_apartado" class="block text-sm font-medium text-gray-700 mb-2">
                Fecha de Apartado <span class="text-red-500">*</span>
            </label>
            <div class="relative">
                <span class="absolute left-3 top-2.5 text-gray-400">
                    <i class="fas fa-calendar"></i>
                </span>
                <input 
                    type="date" 
                    name="fecha_apartado" 
                    id="fecha_apartado" 
                    value="{{ old('fecha_apartado', $apartado?->fecha_apartado?->format('Y-m-d') ?? date('Y-m-d')) }}"
                    required
                    class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 @error('fecha_apartado') border-red-500 @enderror">
            </div>
            @error('fecha_apartado')
                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <!-- Fecha Límite -->
        <div>
            <label for="fecha_limite" class="block text-sm font-medium text-gray-700 mb-2">
                Fecha Límite
            </label>
            <div class="relative">
                <span class="absolute left-3 top-2.5 text-gray-400">
                    <i class="fas fa-calendar-check"></i>
                </span>
                <input 
                    type="date" 
                    name="fecha_limite" 
                    id="fecha_limite" 
                    value="{{ old('fecha_limite', $apartado?->fecha_limite?->format('Y-m-d')) }}"
                    class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 @error('fecha_limite') border-red-500 @enderror">
            </div>
            <p class="mt-1 text-sm text-gray-500">
                <i class="fas fa-info-circle"></i> Fecha sugerida para liquidar el apartado (opcional)
            </p>
            @error('fecha_limite')
                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <!-- Enganche -->
        <div>
            <label for="enganche" class="block text-sm font-medium text-gray-700 mb-2">
                Enganche / Anticipo <span class="text-red-500">*</span>
            </label>
            <div class="relative">
                <span class="absolute left-3 top-2.5 text-gray-400">
                    <i class="fas fa-dollar-sign"></i>
                </span>
                <input 
                    type="number" 
                    name="enganche" 
                    id="enganche" 
                    step="0.01"
                    min="0"
                    value="{{ old('enganche', $apartado?->enganche ?? 0) }}"
                    required
                    class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 @error('enganche') border-red-500 @enderror"
                    placeholder="0.00">
            </div>
            <p class="mt-1 text-sm text-gray-500">
                <i class="fas fa-info-circle"></i> Monto inicial que el cliente pagará
            </p>
            @error('enganche')
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
                    min="0" 
                    max="100" 
                    step="0.01"
                    value="{{ old('descuento_global', 0) }}"
                    class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 @error('descuento_global') border-red-500 @enderror"
                    placeholder="0.00">
            </div>
            <p class="mt-1 text-sm text-gray-500">
                <i class="fas fa-info-circle"></i> Se aplicará a todos los libros
            </p>
            @error('descuento_global')
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
                placeholder="Notas adicionales sobre este apartado...">{{ old('observaciones', $apartado?->observaciones) }}</textarea>
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
                    Libros a Apartar
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
                    $hasApartadoDetalles = $apartado && $apartado->detalles->count() > 0;
                @endphp

                @if($hasOldLibros)
                    {{-- Renderizar libros desde old() cuando hay error de validación --}}
                    @foreach($oldLibros as $index => $oldLibro)
                        <x-libro-item 
                            :libros="$libros" 
                            :index="$index"
                            :oldData="$oldLibro" />
                    @endforeach
                @elseif($hasApartadoDetalles)
                    {{-- Renderizar libros del apartado existente --}}
                    @foreach($apartado->detalles as $index => $detalle)
                        <x-libro-item 
                            :libros="$libros" 
                            :index="$index" 
                            :oldData="[
                                'libro_id' => $detalle->libro_id,
                                'cantidad' => $detalle->cantidad,
                                'descuento' => $detalle->descuento ?? 0
                            ]" />
                    @endforeach
                @endif
            </div>

            <!-- Mensaje cuando no hay libros -->
            @if(!$hasOldLibros && (!$apartado || $apartado->detalles->count() === 0))
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
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">Total:</span>
                    <span class="font-semibold text-gray-900" id="totalDisplay">$0.00</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">Enganche:</span>
                    <span class="font-semibold text-green-600" id="engancheDisplay">$0.00</span>
                </div>
                <div class="flex justify-between text-lg font-bold border-t border-gray-300 pt-2">
                    <span class="text-gray-900">Saldo Pendiente:</span>
                    <span class="text-orange-600" id="saldoDisplay">$0.00</span>
                </div>
            </div>
        </div>

        <!-- Botones de Acción -->
        <div class="lg:col-span-2 flex justify-end gap-3 pt-4 border-t border-gray-200">
            <x-button 
                type="button" 
                variant="secondary" 
                icon="fas fa-times" 
                onclick="window.location='{{ route('apartados.index') }}'">
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
    window.apartadoLibrosData = @json($libros);
    
    // Toggle Tipo de Inventario
    function toggleInventarioTipo() {
        const tipoGeneral = document.getElementById('tipo_inventario_general');
        const tipoSubinventario = document.getElementById('tipo_inventario_subinventario');
        const subinventarioSelector = document.getElementById('subinventarioSelector');
        const subinventarioSelect = document.getElementById('subinventario_id');
        
        if (tipoSubinventario && tipoSubinventario.checked) {
            if (subinventarioSelector) {
                subinventarioSelector.style.display = 'block';
                if (subinventarioSelect) {
                    subinventarioSelect.required = true;
                }
            }
        } else {
            if (subinventarioSelector) {
                subinventarioSelector.style.display = 'none';
                if (subinventarioSelect) {
                    subinventarioSelect.required = false;
                    subinventarioSelect.value = '';
                }
            }
        }
        
        // Actualizar lista de libros
        actualizarListaLibros();
    }
    
    // Cargar libros según subinventario seleccionado
    function cargarLibrosSubinventario() {
        actualizarListaLibros();
    }
    
    // Actualizar lista de libros disponibles según tipo de inventario
    function actualizarListaLibros() {
        const tipoGeneral = document.getElementById('tipo_inventario_general');
        const subinventarioSelect = document.getElementById('subinventario_id');
        
        if (!tipoGeneral) return;
        
        if (tipoGeneral.checked) {
            // Cargar libros del inventario general
            window.apartadoLibrosData = @json($libros);
            // Actualizar las instancias de LibroSearchDynamic
            actualizarInstanciasLibroSearch();
        } else if (subinventarioSelect && subinventarioSelect.value) {
            // Cargar libros del subinventario seleccionado vía AJAX
            fetch(`/api/subinventarios/${subinventarioSelect.value}/libros`)

                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data.libros) {
                        // Mapear libros del subinventario al formato esperado
                        window.apartadoLibrosData = data.data.libros.map(libro => ({
                            id: libro.id,
                            nombre: libro.nombre,
                            codigo_barras: libro.codigo_barras,
                            precio: libro.precio,
                            stock: libro.cantidad_disponible // Usar cantidad_disponible como stock
                        }));
                        // Actualizar todos los selects de libros existentes
                        actualizarSelectsLibros();
                        // Actualizar las instancias de LibroSearchDynamic
                        actualizarInstanciasLibroSearch();
                    }
                })
                .catch(error => console.error('Error cargando libros:', error));
        }
    }
    
    // Actualizar instancias de LibroSearchDynamic con nueva data
    function actualizarInstanciasLibroSearch() {
        if (window.libroSearchInstances) {
            Object.values(window.libroSearchInstances).forEach(instance => {
                if (instance && typeof instance.updateLibrosData === 'function') {
                    instance.updateLibrosData(window.apartadoLibrosData);
                }
            });
        }
    }
    
    // Actualizar todos los selects de libros con nueva data
    function actualizarSelectsLibros() {
        // Esta función se ejecutará después de cargar libros del subinventario
        const libroSelects = document.querySelectorAll('[id^="libro_id_"]');
        libroSelects.forEach(select => {
            const valorActual = select.value;
            select.innerHTML = '<option value="">Selecciona un libro</option>';
            
            window.apartadoLibrosData.forEach(libro => {
                const option = document.createElement('option');
                option.value = libro.id;
                option.textContent = `${libro.nombre} ($${libro.precio})`;
                option.dataset.precio = libro.precio;
                option.dataset.stock = libro.stock || libro.cantidad || 0;
                if (libro.id == valorActual) {
                    option.selected = true;
                }
                select.appendChild(option);
            });
        });
    }
    
    // Inicializar al cargar la página
    document.addEventListener('DOMContentLoaded', function() {
        // Configurar estado inicial
        toggleInventarioTipo();
        
        const apartadoForm = document.getElementById('apartadoForm');
        if (apartadoForm) {
            apartadoForm.addEventListener('submit', function(e) {
                const submitButton = apartadoForm.querySelector('button[type="submit"]');
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
<script src="{{ asset('js/libro-search-dynamic.js') }}"></script>
<script src="{{ asset('js/apartado-form.js') }}"></script>
@endpush
