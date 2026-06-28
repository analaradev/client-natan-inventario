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
                        <i class="fas fa-book mr-2 text-blue-600"></i>Libros a Sub-Inventario
                    </h3>
                    <button type="button" 
                            onclick="agregarLibroAlInicio()" 
                            class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                        <i class="fas fa-plus mr-2"></i>Agregar Libro
                    </button>
                </div>

                @error('libros')
                    <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                        <p class="text-sm text-red-600">{{ $message }}</p>
                    </div>
                @enderror

                <div id="librosClientError"
                     class="hidden mb-4 p-4 bg-red-50 border border-red-200 rounded-lg"
                     role="alert">
                    <p class="text-sm text-red-600"></p>
                </div>

                <div id="librosContainer" class="space-y-3">
                    <!-- Los libros se agregarán aquí dinámicamente -->
                </div>

                <!-- Controles de Paginación para Libros -->
                <div id="paginationControls" class="flex justify-between items-center mt-4 bg-gray-50 p-3 rounded-lg border border-gray-200" style="display: none;">
                    <button type="button" 
                            id="btnPrev" 
                            onclick="cambiarPagina(-1)"
                            class="px-4 py-2 bg-white hover:bg-gray-100 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                        <i class="fas fa-chevron-left mr-2"></i>Anterior
                    </button>
                    <span id="pageIndicator" class="text-sm text-gray-700 font-medium">Página 1 de 1</span>
                    <button type="button" 
                            id="btnNext" 
                            onclick="cambiarPagina(1)"
                            class="px-4 py-2 bg-white hover:bg-gray-100 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                        Siguiente<i class="fas fa-chevron-right ml-2"></i>
                    </button>
                </div>

                <div id="emptyMessage" class="text-center py-8 bg-gray-50 rounded-lg" style="display: none;">
                    <i class="fas fa-info-circle text-gray-400 text-3xl mb-2"></i>
                    <p class="text-gray-500">No hay libros agregados. Haz clic en "Agregar Libro" para comenzar.</p>
                </div>

                <div class="flex justify-center mt-4">
                    <button type="button" 
                            onclick="agregarLibro()" 
                            class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                        <i class="fas fa-plus mr-2"></i>Agregar Libro
                    </button>
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

<!-- Template para nuevos libros -->
<template id="libroTemplate">
    <div class="flex gap-3 items-start bg-gray-50 p-4 rounded-lg libro-item" id="libro-INDEX_PLACEHOLDER">
        <div class="flex-1 grid grid-cols-1 md:grid-cols-3 gap-3">
            <div class="md:col-span-2">
                <x-libro-search-dynamic 
                    name="libros[INDEX_PLACEHOLDER][libro_id]" 
                    index="INDEX_PLACEHOLDER"
                    :libros="$libros"
                    label="Libro"
                    :required="true"
                />
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Cantidad *</label>
                <input type="number" 
                       name="libros[INDEX_PLACEHOLDER][cantidad]" 
                       min="1" 
                       value="1"
                       required
                       class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 cantidad-input">
                <p class="stock-message mt-1 text-xs text-gray-500 font-medium"></p>
            </div>
        </div>
        
        <button type="button" 
                class="remove-libro mt-7 text-red-600 hover:text-red-900"
                title="Quitar libro">
            <i class="fas fa-trash"></i>
        </button>
    </div>
</template>

@push('scripts')
<script>
    // Global libros data for libro search components
    window.subinventoryLibrosData = @json($libros);
</script>
<script src="{{ asset('js/libro-search-dynamic.js') }}"></script>
<script>
    let libroIndex = 0;
    const libros = window.subinventoryLibrosData;

    // Variables de paginación client-side
    let paginaActual = 1;
    const itemsPorPagina = 10;

    function normalizarTexto(texto) {
        return String(texto || '')
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '');
    }

    function escaparHtml(texto) {
        const div = document.createElement('div');
        div.textContent = texto || '';
        return div.innerHTML;
    }

    function escaparParametro(texto) {
        return String(texto || '').replace(/\\/g, '\\\\').replace(/'/g, "\\'");
    }

    function actualizarPaginacion() {
        const items = Array.from(document.querySelectorAll('.libro-item'));
        const totalItems = items.length;
        const totalPaginas = Math.ceil(totalItems / itemsPorPagina) || 1;

        if (paginaActual > totalPaginas) {
            paginaActual = totalPaginas;
        }
        if (paginaActual < 1) {
            paginaActual = 1;
        }

        const paginationControls = document.getElementById('paginationControls');
        if (totalItems > itemsPorPagina) {
            paginationControls.style.display = 'flex';
        } else {
            paginationControls.style.display = 'none';
        }

        document.getElementById('pageIndicator').textContent = `Página ${paginaActual} de ${totalPaginas}`;
        document.getElementById('btnPrev').disabled = paginaActual === 1;
        document.getElementById('btnNext').disabled = paginaActual === totalPaginas;

        const inicio = (paginaActual - 1) * itemsPorPagina;
        const fin = inicio + itemsPorPagina;

        items.forEach((item, index) => {
            if (index >= inicio && index < fin) {
                item.style.setProperty('display', 'flex', 'important');
            } else {
                item.style.setProperty('display', 'none', 'important');
            }
        });
    }

    function cambiarPagina(direccion) {
        paginaActual += direccion;
        actualizarPaginacion();
        document.getElementById('librosContainer').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function agregarLibro(libroId = '', cantidad = 1, autoPage = true) {
        const container = document.getElementById('librosContainer');
        const emptyMessage = document.getElementById('emptyMessage');
        const currentIndex = libroIndex;
        
        // Clonar el template
        const template = document.getElementById('libroTemplate');
        const clone = template.content.cloneNode(true);
        const div = clone.querySelector('.libro-item');
        
        // Reemplazar marcadores de posición INDEX_PLACEHOLDER
        div.id = `libro-${currentIndex}`;
        div.dataset.index = currentIndex;
        div.innerHTML = div.innerHTML.replace(/INDEX_PLACEHOLDER/g, currentIndex);
        
        container.appendChild(div);
        
        // Configurar los valores de los inputs
        const idInput = div.querySelector('.libro-id-input');
        if (libroId) {
            idInput.value = libroId;
        }
        
        const cantidadInput = div.querySelector('.cantidad-input');
        if (cantidad) {
            cantidadInput.value = cantidad;
        }
        
        emptyMessage.style.display = 'none';
        
        // Inicializar el buscador de libros
        const containerId = `libro_search_libros_${currentIndex}_libro_id_container`;
        if (typeof window.initLibroSearch === 'function') {
            window.libroSearchInstances[containerId] = window.initLibroSearch(containerId, libros);
        }
        
        // Configurar el botón de eliminar
        const deleteBtn = div.querySelector('.remove-libro');
        if (deleteBtn) {
            deleteBtn.onclick = function() {
                eliminarLibro(currentIndex);
            };
        }
        
        libroIndex++;
        
        if (autoPage) {
            const totalItems = document.querySelectorAll('.libro-item').length;
            paginaActual = Math.ceil(totalItems / itemsPorPagina) || 1;
            actualizarPaginacion();
        }
    }

    function eliminarLibro(index) {
        document.getElementById(`libro-${index}`)?.remove();
        document.getElementById('emptyMessage').style.display =
            document.querySelectorAll('.libro-item').length === 0 ? 'block' : 'none';
        ocultarErrorLibros();
        actualizarPaginacion();
    }

    function mostrarErrorLibros(mensaje, input = null) {
        const error = document.getElementById('librosClientError');
        error.querySelector('p').textContent = mensaje;
        error.classList.remove('hidden');
        input?.setAttribute('aria-invalid', 'true');
        input?.focus();
        error.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function ocultarErrorLibros() {
        document.getElementById('librosClientError').classList.add('hidden');
    }

    function agregarLibroAlInicio(libroId = '', cantidad = 1) {
        const container = document.getElementById('librosContainer');
        const emptyMessage = document.getElementById('emptyMessage');
        const currentIndex = libroIndex;
        
        // Clonar el template
        const template = document.getElementById('libroTemplate');
        const clone = template.content.cloneNode(true);
        const div = clone.querySelector('.libro-item');
        
        // Reemplazar marcadores de posición INDEX_PLACEHOLDER
        div.id = `libro-${currentIndex}`;
        div.dataset.index = currentIndex;
        div.innerHTML = div.innerHTML.replace(/INDEX_PLACEHOLDER/g, currentIndex);
        
        // Agregar al INICIO del contenedor
        if (container.children.length > 0) {
            container.insertBefore(div, container.firstChild);
        } else {
            container.appendChild(div);
        }
        
        // Configurar los valores de los inputs
        const idInput = div.querySelector('.libro-id-input');
        if (libroId) {
            idInput.value = libroId;
        }
        
        const cantidadInput = div.querySelector('.cantidad-input');
        if (cantidad) {
            cantidadInput.value = cantidad;
        }
        
        emptyMessage.style.display = 'none';
        
        // Inicializar el buscador de libros
        const containerId = `libro_search_libros_${currentIndex}_libro_id_container`;
        if (typeof window.initLibroSearch === 'function') {
            window.libroSearchInstances[containerId] = window.initLibroSearch(containerId, libros);
        }
        
        // Configurar el botón de eliminar
        const deleteBtn = div.querySelector('.remove-libro');
        if (deleteBtn) {
            deleteBtn.onclick = function() {
                eliminarLibro(currentIndex);
            };
        }
        
        libroIndex++;
        
        // Ir a la primera página al agregar al inicio
        paginaActual = 1;
        actualizarPaginacion();
    }

    // Escuchar el evento de cambio para actualizar max de stock y mensajes
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('librosContainer').addEventListener('change', (e) => {
            if (e.target.classList.contains('libro-id-input')) {
                const libroItem = e.target.closest('.libro-item');
                const stock = parseInt(e.target.dataset.stock) || 0;
                const cantidadInput = libroItem.querySelector('.cantidad-input');
                const stockMessage = libroItem.querySelector('.stock-message');
                if (cantidadInput) {
                    cantidadInput.max = stock;
                }
                if (stockMessage) {
                    stockMessage.textContent = e.target.value ? `Stock disponible: ${stock}` : '';
                }
            }
        });

        // Cargar los libros guardados previamente o uno vacío
        const oldLibros = @json(old('libros', []));
        if (oldLibros && Object.keys(oldLibros).length > 0) {
            Object.values(oldLibros).forEach(oldLibro => {
                if (oldLibro.libro_id) {
                    agregarLibro(oldLibro.libro_id, oldLibro.cantidad, false);
                }
            });
        } else {
            agregarLibro('', 1, false);
        }

        document.getElementById('emptyMessage').style.display =
            document.querySelectorAll('.libro-item').length === 0 ? 'block' : 'none';

        // Inicializar paginación
        paginaActual = 1;
        actualizarPaginacion();

        document.getElementById('subinventarioForm').addEventListener('submit', function(event) {
            ocultarErrorLibros();
            const filas = Array.from(document.querySelectorAll('.libro-item'));

            if (filas.length === 0) {
                event.preventDefault();
                mostrarErrorLibros('Debes agregar por lo menos un libro al sub-inventario.');
                return;
            }

            const ids = [];
            for (const fila of filas) {
                const idInput = fila.querySelector('.libro-id-input');
                const searchInput = fila.querySelector('.libro-search-input');

                if (!idInput.value) {
                    event.preventDefault();
                    // Ir a la página del error
                    const items = Array.from(document.querySelectorAll('.libro-item'));
                    const itemIndex = items.indexOf(fila);
                    if (itemIndex !== -1) {
                        paginaActual = Math.floor(itemIndex / itemsPorPagina) + 1;
                        actualizarPaginacion();
                    }
                    mostrarErrorLibros('Selecciona el libro desde la lista de resultados antes de guardar.', searchInput);
                    return;
                }

                if (ids.includes(idInput.value)) {
                    event.preventDefault();
                    // Ir a la página del duplicado
                    const items = Array.from(document.querySelectorAll('.libro-item'));
                    const itemIndex = items.indexOf(fila);
                    if (itemIndex !== -1) {
                        paginaActual = Math.floor(itemIndex / itemsPorPagina) + 1;
                        actualizarPaginacion();
                    }
                    mostrarErrorLibros('El mismo libro no puede agregarse más de una vez.', searchInput);
                    return;
                }
                ids.push(idInput.value);
            }
        });
    });
</script>
@endpush
@endsection
