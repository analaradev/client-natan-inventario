{{--
    Componente: Cliente Selector
    Descripción: Búsqueda y selección de cliente con autocompletado
    Props:
        - venta: Objeto venta existente (opcional)
--}}

@props(['venta' => null])

<div class="mb-4">
    <label for="cliente_search" class="block text-sm font-medium text-gray-700 mb-2">
        Cliente <span class="text-gray-400 text-xs">(Opcional)</span>
    </label>
    
    <!-- Input de búsqueda -->
    <div class="relative" id="clienteSearchContainer">
        <span class="absolute left-3 top-2.5 text-gray-400">
            <i class="fas fa-user"></i>
        </span>
        <input 
            type="text" 
            id="cliente_search" 
            placeholder="Buscar cliente..."
            autocomplete="off"
            value="{{ old('cliente_nombre', $venta?->cliente?->nombre) }}"
            class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
        
        <!-- Dropdown de resultados -->
        <div id="clienteResults" class="hidden absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg max-h-60 overflow-auto"></div>
    </div>
    
    <!-- Input hidden con ID del cliente -->
    <input type="hidden" name="cliente_id" id="cliente_id" value="{{ old('cliente_id', $venta?->cliente_id) }}">
    
    <!-- Cliente seleccionado -->
    <div id="clienteSelected" class="hidden mt-2 p-3 bg-primary-50 border border-primary-200 rounded-lg">
        <div class="flex justify-between items-center">
            <div>
                <p class="font-semibold text-primary-800" id="clienteNombre"></p>
                <p class="text-sm text-primary-600" id="clienteTelefono"></p>
            </div>
            <button type="button" id="clearCliente" class="text-red-600 hover:text-red-700 transition-colors">
                <i class="fas fa-times-circle"></i>
            </button>
        </div>
    </div>
    
    <!-- Botón crear cliente -->
    <a href="{{ route('clientes.create', ['return_url' => url()->current()]) }}" id="btnNuevoCliente" class="mt-2 inline-block text-sm text-primary-600 hover:text-primary-700 font-medium transition-colors">
        <i class="fas fa-plus-circle"></i> Crear nuevo cliente
    </a>
</div>
