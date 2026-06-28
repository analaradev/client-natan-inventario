{{--
    Componente: Cliente Search Dynamic
    Descripción: Búsqueda dinámica de clientes (IDÉNTICO al componente de libros)
    Props:
        - name: Nombre del campo (default: "cliente_id")
        - selected: ID del cliente seleccionado (opcional)
        - label: Etiqueta del campo
        - required: Si el campo es requerido
--}}

@props([
    'name' => 'cliente_id',
    'selected' => null,
    'clienteData' => null,
    'label' => 'Cliente',
    'required' => false,
    'placeholder' => 'Buscar cliente...',
    'showCreateOption' => true
])

@php
    $uniqueId = 'cliente_search_' . str_replace(['[', ']'], ['_', ''], $name);
    
    // Obtener datos del cliente para los atributos data-*
    $clienteNombre = $clienteData?->nombre ?? '';
    $clienteTelefono = $clienteData?->telefono ?? '';
@endphp

<div class="cliente-search-container" id="{{ $uniqueId }}_container">
    @if($label)
        <label for="{{ $uniqueId }}_search" class="block text-sm font-medium text-gray-700 mb-2">
            <i class="fas fa-user text-gray-400"></i> {{ $label }}
            @if($required)<span class="text-red-500">*</span>@endif
        </label>
    @endif
    
    <!-- Input de búsqueda visible -->
    <div class="relative">
        <div class="relative">
            <span class="absolute left-3 top-2.5 text-gray-400">
                <i class="fas fa-search"></i>
            </span>
            <input 
                type="text" 
                id="{{ $uniqueId }}_search"
                class="cliente-search-input w-full pl-10 pr-10 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                placeholder="{{ $placeholder }}"
                autocomplete="off"
            >
            <button 
                type="button"
                class="cliente-clear-btn absolute right-3 top-2.5 text-gray-400 hover:text-gray-600 hidden"
            >
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <!-- Dropdown de resultados -->
        <div class="cliente-dropdown absolute z-[9999] w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-xl max-h-64 overflow-y-auto hidden">
            <div class="cliente-results divide-y divide-gray-100">
                <!-- Los resultados se insertarán aquí -->
            </div>
            <div class="cliente-no-results hidden p-4 text-center text-gray-500">
                <i class="fas fa-search text-2xl mb-2 text-gray-300"></i>
                <p class="text-sm">No se encontraron clientes</p>
            </div>
            @if($showCreateOption)
                <div class="cliente-all-option p-3 border-t border-gray-200 bg-gray-50 hover:bg-gray-100 cursor-pointer transition-colors">
                    <p class="text-sm font-medium text-gray-700">
                        <i class="fas fa-plus-circle"></i> Crear nuevo cliente
                    </p>
                </div>
            @endif
        </div>
    </div>
    
    <!-- Input hidden con el valor real -->
    <input 
        type="hidden" 
        name="{{ $name }}" 
        class="cliente-id-input"
        value="{{ old($name, $selected) }}"
        data-nombre="{{ $clienteNombre }}"
        data-telefono="{{ $clienteTelefono }}"
        @if($required) required @endif
    >
    
    <!-- Cliente seleccionado - Ocupa espacio normal en el flujo del documento -->
    <div class="cliente-selected mt-3 hidden">
        <div class="p-3 bg-primary-50 border border-primary-200 rounded-lg shadow-sm">
            <div class="flex items-start justify-between">
                <div class="flex-1">
                    <div class="flex items-center gap-2 mb-1">
                        <i class="fas fa-user text-primary-600"></i>
                        <p class="font-semibold text-gray-900 cliente-selected-nombre"></p>
                    </div>
                    <div class="text-sm text-gray-600 space-y-1">
                        <p>
                            <i class="fas fa-id-badge text-gray-400 w-4"></i>
                            <span class="cliente-selected-codigo"></span>
                        </p>
                        <p>
                            <i class="fas fa-phone text-gray-400 w-4"></i>
                            Teléfono: <span class="cliente-selected-telefono font-medium"></span>
                        </p>
                    </div>
                </div>
                <button 
                    type="button" 
                    class="cliente-remove-btn ml-3 text-red-600 hover:text-red-800 transition-colors p-1"
                    title="Quitar cliente"
                >
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    </div>
</div>



