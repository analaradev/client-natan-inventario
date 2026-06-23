@extends('layouts.app')

@section('title', 'Editar Sub-Inventario')

@section('content')
<x-page-layout 
    title="Editar Sub-Inventario #{{ $subinventario->id }}"
    description="Modifica los libros y cantidades del sub-inventario"
    button-text="Volver a Sub-Inventarios"
    button-icon="fas fa-arrow-left"
    :button-route="route('subinventarios.show', $subinventario)"
>
    <x-card>
        <form action="{{ route('subinventarios.update', $subinventario) }}" method="POST" id="subinventarioForm">
            @csrf
            @method('PUT')

            <!-- Información del sub-inventario -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Fecha de Sub-Inventario <span class="text-red-500">*</span>
                    </label>
                    <input type="date" 
                           name="fecha_subinventario" 
                           value="{{ old('fecha_subinventario', $subinventario->fecha_subinventario->format('Y-m-d')) }}"
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
                           value="{{ old('descripcion', $subinventario->descripcion) }}"
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
                          class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('observaciones') border-red-500 @enderror">{{ old('observaciones', $subinventario->observaciones) }}</textarea>
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

                @if ($errors->has('libros.*') || $errors->has('error'))
                    <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                        @foreach ($errors->get('libros.*') as $mensajes)
                            @foreach ($mensajes as $mensaje)
                                <p class="text-sm text-red-600">{{ $mensaje }}</p>
                            @endforeach
                        @endforeach
                        @error('error')
                            <p class="text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                @endif

                <div id="librosClientError"
                     class="hidden mb-4 p-4 bg-red-50 border border-red-200 rounded-lg"
                     role="alert">
                    <p class="text-sm text-red-600"></p>
                </div>

                <div id="librosContainer" class="space-y-3">
                    <!-- Los libros se agregarán aquí dinámicamente -->
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
                <a href="{{ route('subinventarios.show', $subinventario) }}" 
                   class="bg-gray-500 text-white px-6 py-2 rounded-lg hover:bg-gray-600">
                    <i class="fas fa-times mr-2"></i>Cancelar
                </a>
                <button type="submit" 
                        class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                    <i class="fas fa-save mr-2"></i>Actualizar Sub-Inventario
                </button>
            </div>
        </form>
    </x-card>
</x-page-layout>

@push('scripts')
@php
    $librosFormulario = old('libros');

    if ($librosFormulario === null) {
        $librosFormulario = $subinventario->libros->map(function ($libro) {
            return [
                'libro_id' => $libro->id,
                'cantidad' => $libro->pivot->cantidad,
            ];
        })->values()->all();
    } else {
        // Al quitar una fila pueden quedar índices separados (0, 2, 3...).
        // Reindexarlos garantiza que JSON siga siendo un arreglo y admita forEach.
        $librosFormulario = collect($librosFormulario)->values()->all();
    }
@endphp
<script>
    let libroIndex = 0;
    const libros = @json($libros);
    const subinventarioLibros = @json($librosFormulario);

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

    function agregarLibro(libroId = '', cantidad = 1) {
        const container = document.getElementById('librosContainer');
        const emptyMessage = document.getElementById('emptyMessage');
        const currentIndex = libroIndex;
        
        const div = document.createElement('div');
        div.className = 'flex gap-3 items-start bg-gray-50 p-4 rounded-lg libro-item';
        div.id = `libro-${currentIndex}`;
        
        // Encontrar el libro seleccionado si existe
        const libroSeleccionado = libroId ? libros.find(l => l.id == libroId) : null;
        const stockDisponible = libroSeleccionado ? (libroSeleccionado.stock_disponible_edicion ?? libroSeleccionado.stock) : 0;
        
        div.innerHTML = `
            <div class="flex-1 grid grid-cols-1 md:grid-cols-3 gap-3">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Libro *</label>
                    <div class="relative">
                        <input type="hidden" 
                               name="libros[${currentIndex}][libro_id]" 
                               id="libro_id_${currentIndex}"
                               value="${libroId}">
                        <input type="text" 
                               id="search_${currentIndex}"
                               placeholder="Busca un libro..." 
                               autocomplete="off"
                               required
                               class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 px-3 py-2"
                               value="${libroSeleccionado ? escaparHtml(libroSeleccionado.nombre) : ''}"
                               data-index="${currentIndex}">
                        <div id="dropdown_${currentIndex}" class="absolute top-full left-0 right-0 mt-1 bg-white border border-gray-300 rounded-lg shadow-lg max-h-48 overflow-y-auto z-50" style="display: none;"></div>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Cantidad *</label>
                    <input type="number" 
                           name="libros[${currentIndex}][cantidad]" 
                           min="0" 
                           value="${cantidad}"
                           max="${stockDisponible}"
                           required
                           class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <p id="stock-info-${currentIndex}" class="mt-1 text-xs text-gray-500">${libroSeleccionado ? 'Stock disponible: ' + stockDisponible : ''}</p>
                </div>
            </div>
            <button type="button"
                    onclick="eliminarLibro(${currentIndex})"
                    class="mt-6 text-red-600 hover:text-red-800 p-2"
                    title="Quitar libro"
                    aria-label="Quitar libro">
                <i class="fas fa-trash"></i>
            </button>
        `;
        
        container.appendChild(div);
        emptyMessage.style.display = 'none';
        
        // Agregar event listeners para búsqueda
        const searchInput = div.querySelector(`#search_${currentIndex}`);
        const dropdown = div.querySelector(`#dropdown_${currentIndex}`);
        
        searchInput.dataset.selectedName = libroSeleccionado ? libroSeleccionado.nombre : '';
        searchInput.addEventListener('input', () => manejarBusqueda(currentIndex));
        searchInput.addEventListener('focus', () => filtrarLibros(currentIndex));
        
        document.addEventListener('click', (e) => {
            if (!div.contains(e.target)) {
                dropdown.style.display = 'none';
            }
        });
        
        libroIndex++;
        actualizarLibrosDisponibles();
    }

    function manejarBusqueda(index) {
        const searchInput = document.getElementById(`search_${index}`);
        const libroIdInput = document.getElementById(`libro_id_${index}`);

        if (searchInput.value !== (searchInput.dataset.selectedName || '')) {
            libroIdInput.value = '';
            document.getElementById(`stock-info-${index}`).textContent = 'Selecciona un libro de la lista';
            document.querySelector(`input[name="libros[${index}][cantidad]"]`).removeAttribute('max');
        }

        searchInput.removeAttribute('aria-invalid');
        ocultarErrorLibros();
        filtrarLibros(index);
    }

    function filtrarLibros(index) {
        const searchInput = document.getElementById(`search_${index}`);
        const dropdown = document.getElementById(`dropdown_${index}`);
        const valor = normalizarTexto(searchInput.value);
        const libroIdInput = document.getElementById(`libro_id_${index}`);
        
        // Obtener libros ya seleccionados
        const librosSeleccionados = Array.from(document.querySelectorAll('input[id^="libro_id_"]'))
            .map(el => el.value)
            .filter(v => v && v !== '');
        
        // Filtrar libros
        const librosFiltrados = libros
            .filter(l => !librosSeleccionados.includes(l.id.toString()) || libroIdInput.value == l.id)
            .filter(l => {
                const nombre = normalizarTexto(l.nombre);
                const codigo = normalizarTexto(l.codigo_barras);
                return nombre.includes(valor) || codigo.includes(valor);
            })
            .slice(0, 15); // Limitar a 15 resultados
        
        // Mostrar dropdown
        if (librosFiltrados.length > 0 && (valor.length > 0 || searchInput === document.activeElement)) {
            dropdown.innerHTML = librosFiltrados.map(libro => {
                const stock = libro.stock_disponible_edicion ?? libro.stock;
                return `
                <div class="px-3 py-2 hover:bg-blue-50 cursor-pointer border-b" 
                     data-libro-id="${libro.id}" 
                     data-libro-nombre="${escaparHtml(libro.nombre)}"
                     data-stock="${stock}"
                     onclick="seleccionarLibro(${index}, ${libro.id}, '${escaparParametro(libro.nombre)}', ${stock})">
                    <div class="font-medium text-gray-900">${escaparHtml(libro.nombre)}</div>
                    <div class="text-xs text-gray-500">
                        ${libro.codigo_barras ? 'Código: ' + escaparHtml(libro.codigo_barras) + ' · ' : ''}Stock: ${stock}
                    </div>
                </div>
            `}).join('');
            dropdown.style.display = 'block';
        } else if (valor.length > 0 && searchInput === document.activeElement) {
            dropdown.innerHTML = `
                <div class="px-3 py-3 text-sm text-gray-500 text-center">
                    No se encontraron coincidencias
                </div>
            `;
            dropdown.style.display = 'block';
        } else {
            dropdown.style.display = 'none';
        }
    }

    function seleccionarLibro(index, libroId, libroNombre, stock) {
        document.getElementById(`libro_id_${index}`).value = libroId;
        const searchInput = document.getElementById(`search_${index}`);
        searchInput.value = libroNombre;
        searchInput.dataset.selectedName = libroNombre;
        searchInput.removeAttribute('aria-invalid');
        document.getElementById(`dropdown_${index}`).style.display = 'none';
        document.getElementById(`stock-info-${index}`).textContent = `Stock disponible: ${stock}`;
        
        // Actualizar max del input cantidad
        const cantidadInput = document.querySelector(`input[name="libros[${index}][cantidad]"]`);
        cantidadInput.max = stock;

        ocultarErrorLibros();
        actualizarLibrosDisponibles();
    }

    function eliminarLibro(index) {
        document.getElementById(`libro-${index}`)?.remove();
        document.getElementById('emptyMessage').style.display =
            document.querySelectorAll('.libro-item').length === 0 ? 'block' : 'none';
        ocultarErrorLibros();
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

    function actualizarLibrosDisponibles() {
        // No es necesario deshabilitar opciones con este sistema
    }

    function agregarLibroAlInicio(libroId = '', cantidad = 1) {
        const container = document.getElementById('librosContainer');
        const emptyMessage = document.getElementById('emptyMessage');
        const currentIndex = libroIndex;
        
        const div = document.createElement('div');
        div.className = 'flex gap-3 items-start bg-gray-50 p-4 rounded-lg libro-item';
        div.id = `libro-${currentIndex}`;
        
        // Encontrar el libro seleccionado si existe
        const libroSeleccionado = libroId ? libros.find(l => l.id == libroId) : null;
        const stockDisponible = libroSeleccionado ? (libroSeleccionado.stock_disponible_edicion ?? libroSeleccionado.stock) : 0;
        
        div.innerHTML = `
            <div class="flex-1 grid grid-cols-1 md:grid-cols-3 gap-3">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Libro *</label>
                    <div class="relative">
                        <input type="hidden" 
                               name="libros[${currentIndex}][libro_id]" 
                               id="libro_id_${currentIndex}"
                               value="${libroId}">
                        <input type="text" 
                               id="search_${currentIndex}"
                               placeholder="Busca un libro..." 
                               autocomplete="off"
                               required
                               class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 px-3 py-2"
                               value="${libroSeleccionado ? escaparHtml(libroSeleccionado.nombre) : ''}"
                               data-index="${currentIndex}">
                        <div id="dropdown_${currentIndex}" class="absolute top-full left-0 right-0 mt-1 bg-white border border-gray-300 rounded-lg shadow-lg max-h-48 overflow-y-auto z-50" style="display: none;"></div>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Cantidad *</label>
                    <input type="number" 
                           name="libros[${currentIndex}][cantidad]" 
                           min="0" 
                           value="${cantidad}"
                           max="${stockDisponible}"
                           required
                           class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <p id="stock-info-${currentIndex}" class="mt-1 text-xs text-gray-500">${libroSeleccionado ? 'Stock disponible: ' + stockDisponible : ''}</p>
                </div>
            </div>
            <button type="button"
                    onclick="eliminarLibro(${currentIndex})"
                    class="mt-6 text-red-600 hover:text-red-800 p-2"
                    title="Quitar libro"
                    aria-label="Quitar libro">
                <i class="fas fa-trash"></i>
            </button>
        `;
        
        // Agregar al INICIO del contenedor
        if (container.children.length > 0) {
            container.insertBefore(div, container.firstChild);
        } else {
            container.appendChild(div);
        }
        
        emptyMessage.style.display = 'none';
        
        // Agregar event listeners para búsqueda
        const searchInput = div.querySelector(`#search_${currentIndex}`);
        const dropdown = div.querySelector(`#dropdown_${currentIndex}`);
        
        searchInput.dataset.selectedName = libroSeleccionado ? libroSeleccionado.nombre : '';
        searchInput.addEventListener('input', () => manejarBusqueda(currentIndex));
        searchInput.addEventListener('focus', () => filtrarLibros(currentIndex));
        
        document.addEventListener('click', (e) => {
            if (!div.contains(e.target)) {
                dropdown.style.display = 'none';
            }
        });
        
        libroIndex++;
        actualizarLibrosDisponibles();
    }

    // Cargar los libros existentes al iniciar
    document.addEventListener('DOMContentLoaded', function() {
        subinventarioLibros.forEach(item => {
            agregarLibro(item.libro_id, item.cantidad);
        });

        document.getElementById('emptyMessage').style.display =
            subinventarioLibros.length === 0 ? 'block' : 'none';

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
                const idInput = fila.querySelector('input[type="hidden"][name$="[libro_id]"]');
                const searchInput = fila.querySelector('input[id^="search_"]');

                if (!idInput.value) {
                    event.preventDefault();
                    mostrarErrorLibros('Selecciona el libro desde la lista de resultados antes de guardar.', searchInput);
                    return;
                }

                if (ids.includes(idInput.value)) {
                    event.preventDefault();
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
