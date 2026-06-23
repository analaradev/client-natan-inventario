@extends('layouts.app')

@section('title', 'Nuevo Sub-Inventario')

@section('content')
<x-page-layout 
    title="Crear Nuevo Sub-Inventario"
    description="Selecciona los libros que reservarás para vender en un día específico"
    button-text="Volver a Sub-Inventarios"
    button-icon="fas fa-arrow-left"
    :button-route="route('subinventarios.index')"
>
    <x-card>
        <form action="{{ route('subinventarios.store') }}" method="POST" id="subinventarioForm">
            @csrf

            <!-- Información del sub-inventario -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Fecha de Sub-Inventario <span class="text-red-500">*</span>
                    </label>
                    <input type="date" 
                           name="fecha_subinventario" 
                           value="{{ old('fecha_subinventario', date('Y-m-d')) }}"
                           required
                           class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('fecha_subinventario') border-red-500 @enderror">
                    @error('fecha_subinventario')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Descripción
                    </label>
                    <input type="text" 
                           name="descripcion" 
                           value="{{ old('descripcion') }}"
                           placeholder="Ej: Venta en feria del libro"
                           class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('descripcion') border-red-500 @enderror">
                    @error('descripcion')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Observaciones
                </label>
                <textarea name="observaciones" 
                          rows="3"
                          placeholder="Notas adicionales..."
                          class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('observaciones') border-red-500 @enderror">{{ old('observaciones') }}</textarea>
                @error('observaciones')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <hr class="my-6">

            <!-- Selección de libros -->
            <div>
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">
                        <i class="fas fa-book mr-2 text-blue-600"></i>Libros a Apartar
                    </h3>
                    <button type="button" 
                            onclick="agregarLibro()" 
                            class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                        <i class="fas fa-plus mr-2"></i>Agregar Libro
                    </button>
                </div>

                @error('libros')
                    <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                        <p class="text-sm text-red-600">{{ $message }}</p>
                    </div>
                @enderror

                <div id="librosContainer" class="space-y-3">
                    <!-- Los libros se agregarán aquí dinámicamente -->
                </div>

                <div id="emptyMessage" class="text-center py-8 bg-gray-50 rounded-lg">
                    <i class="fas fa-info-circle text-gray-400 text-3xl mb-2"></i>
                    <p class="text-gray-500">No hay libros agregados. Haz clic en "Agregar Libro" para comenzar.</p>
                </div>
            </div>

            <hr class="my-6">

            <!-- Botones de acción -->
            <div class="flex justify-end gap-3">
                <a href="{{ route('subinventarios.index') }}" 
                   class="bg-gray-500 text-white px-6 py-2 rounded-lg hover:bg-gray-600">
                    <i class="fas fa-times mr-2"></i>Cancelar
                </a>
                <button type="submit" 
                        class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                    <i class="fas fa-save mr-2"></i>Guardar Sub-Inventario
                </button>
            </div>
        </form>
    </x-card>
</x-page-layout>

@push('scripts')
<script>
    let libroIndex = 0;
    const libros = @json($libros);

    function agregarLibro() {
        const container = document.getElementById('librosContainer');
        const emptyMessage = document.getElementById('emptyMessage');
        
        const div = document.createElement('div');
        div.className = 'flex gap-3 items-start bg-gray-50 p-4 rounded-lg libro-item';
        div.id = `libro-${libroIndex}`;
        
        div.innerHTML = `
            <div class="flex-1 grid grid-cols-1 md:grid-cols-3 gap-3">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Libro *</label>
                    <select name="libros[${libroIndex}][libro_id]" 
                            required
                            onchange="actualizarStockDisponible(${libroIndex})"
                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Selecciona un libro</option>
                        ${libros.map(libro => `
                            <option value="${libro.id}" 
                                    data-stock="${libro.stock}">
                                ${libro.nombre} - Stock: ${libro.stock} disponibles
                            </option>
                        `).join('')}
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Cantidad *</label>
                    <input type="number" 
                           name="libros[${libroIndex}][cantidad]" 
                           min="1" 
                           value="1"
                           required
                           class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <p id="stock-info-${libroIndex}" class="mt-1 text-xs text-gray-500"></p>
                </div>
            </div>
            
            <button type="button" 
                    onclick="eliminarLibro(${libroIndex})" 
                    class="mt-7 text-red-600 hover:text-red-900">
                <i class="fas fa-trash"></i>
            </button>
        `;
        
        container.appendChild(div);
        emptyMessage.style.display = 'none';
        libroIndex++;
        
        // Actualizar libros disponibles
        actualizarLibrosDisponibles();
    }

    function actualizarStockDisponible(index) {
        const select = document.querySelector(`select[name="libros[${index}][libro_id]"]`);
        const stockInfo = document.getElementById(`stock-info-${index}`);
        
        if (select.value) {
            const option = select.options[select.selectedIndex];
            const stock = parseInt(option.dataset.stock);
            
            stockInfo.textContent = `Stock disponible: ${stock}`;
            
            // Actualizar el max del input de cantidad
            const cantidadInput = document.querySelector(`input[name="libros[${index}][cantidad]"]`);
            cantidadInput.max = stock;
        } else {
            stockInfo.textContent = '';
        }
        
        // Actualizar libros disponibles en todos los selects
        actualizarLibrosDisponibles();
    }

    function actualizarLibrosDisponibles() {
        // Obtener todos los libros seleccionados
        const selects = document.querySelectorAll('[name^="libros["][name$="][libro_id]"]');
        const librosSeleccionados = Array.from(selects)
            .map(select => select.value)
            .filter(value => value !== '');
        
        // Actualizar cada select
        selects.forEach(select => {
            const valorActual = select.value;
            Array.from(select.options).forEach(option => {
                if (option.value && option.value !== valorActual) {
                    // Deshabilitar si ya está seleccionado en otro select
                    option.disabled = librosSeleccionados.includes(option.value);
                    
                    // Agregar indicador visual
                    if (option.disabled) {
                        if (!option.text.includes('(Ya agregado)')) {
                            option.text = option.text + ' (Ya agregado)';
                        }
                    } else {
                        option.text = option.text.replace(' (Ya agregado)', '');
                    }
                }
            });
        });
    }

    function eliminarLibro(index) {
        const elemento = document.getElementById(`libro-${index}`);
        elemento.remove();
        
        const container = document.getElementById('librosContainer');
        const emptyMessage = document.getElementById('emptyMessage');
        
        if (container.children.length === 0) {
            emptyMessage.style.display = 'block';
        }
        
        // Actualizar libros disponibles
        actualizarLibrosDisponibles();
    }

    // Agregar un libro por defecto al cargar
    document.addEventListener('DOMContentLoaded', function() {
        agregarLibro();
    });
</script>
@endpush
@endsection
