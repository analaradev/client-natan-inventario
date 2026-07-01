@props([
    'name' => 'libro_id',
    'selected' => null,
    'libros' => [],
    'label' => 'Libro',
    'required' => false
])

<div class="relative" x-data="libroSearchFilter{{ $name }}">
    @if($label)
        <label for="{{ $name }}_search" class="block text-sm font-medium text-gray-700 mb-2">
            <i class="fas fa-book text-gray-400"></i> {{ $label }}
            @if($required)<span class="text-red-500">*</span>@endif
        </label>
    @endif
    
    <!-- Input de búsqueda visible -->
    <div class="relative">
        <div class="relative">
            <span class="absolute left-3 top-3 text-gray-400">
                <i class="fas fa-search"></i>
            </span>
            <input 
                type="text" 
                id="{{ $name }}_search"
                placeholder="Buscar libro..."
                autocomplete="off"
                class="w-full pl-10 pr-10 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            >
            <button 
                type="button"
                id="{{ $name }}_clear"
                class="absolute right-3 top-3 text-gray-400 hover:text-gray-600 hidden"
            >
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <!-- Dropdown de resultados -->
        <div 
            id="{{ $name }}_dropdown" 
            class="absolute z-[9999] w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-xl max-h-64 overflow-y-auto hidden"
        >
            <div id="{{ $name }}_results" class="divide-y divide-gray-100">
                <!-- Los resultados se insertarán aquí -->
            </div>
            <div id="{{ $name }}_no_results" class="hidden p-4 text-center text-gray-500">
                <i class="fas fa-search text-2xl mb-2 text-gray-300"></i>
                <p class="text-sm">No se encontraron libros</p>
            </div>
            <div id="{{ $name }}_all_option" class="p-3 border-t border-gray-200 bg-gray-50 hover:bg-gray-100 cursor-pointer transition-colors">
                <p class="text-sm font-medium text-gray-700">
                    <i class="fas fa-list"></i> Ver todos los libros
                </p>
            </div>
        </div>
    </div>
    
    <!-- Input hidden con el valor real -->
    <input 
        type="hidden" 
        name="{{ $name }}" 
        id="{{ $name }}"
        value="{{ old($name, $selected) }}"
        @if($required) required @endif
    >
    
    <!-- Libro seleccionado -->
    <div id="{{ $name }}_selected" class="mt-2 hidden">
        <div class="p-2 bg-primary-50 border border-primary-200 rounded-lg flex items-center justify-between text-sm">
            <div class="flex-1">
                <p class="font-medium text-gray-900" id="{{ $name }}_selected_nombre"></p>
                <p class="text-xs text-gray-600" id="{{ $name }}_selected_codigo"></p>
            </div>
            <button 
                type="button" 
                id="{{ $name }}_remove"
                class="ml-2 text-red-600 hover:text-red-800 transition-colors"
                title="Quitar libro"
            >
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
</div>

<script>
(function() {
    let libros = @json($libros);
    const nameId = '{{ $name }}';
    const searchInput = document.getElementById(nameId + '_search');
    const dropdown = document.getElementById(nameId + '_dropdown');
    const resultsContainer = document.getElementById(nameId + '_results');
    const noResults = document.getElementById(nameId + '_no_results');
    const allOption = document.getElementById(nameId + '_all_option');
    const hiddenInput = document.getElementById(nameId);
    const selectedDiv = document.getElementById(nameId + '_selected');
    const selectedNombre = document.getElementById(nameId + '_selected_nombre');
    const selectedCodigo = document.getElementById(nameId + '_selected_codigo');
    const removeBtn = document.getElementById(nameId + '_remove');
    const clearBtn = document.getElementById(nameId + '_clear');

    let selectedLibro = null;

    // Función para normalizar texto (sin acentos, minúsculas)
    function normalizeText(text) {
        return text.toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '');
    }

    // Función para buscar libros
    function searchLibros(query) {
        if (!query || query.length < 1) {
            return libros.slice(0, 20); // Primeros 20 por defecto
        }

        const normalizedQuery = normalizeText(query);
        
        return libros.filter(libro => {
            const nombre = normalizeText(libro.nombre);
            const codigo = normalizeText(libro.codigo_barras || '');
            
            return nombre.includes(normalizedQuery) || codigo.includes(normalizedQuery);
        }).slice(0, 20); // Limitar a 20 resultados
    }

    // Función para renderizar resultados
    function renderResults(results) {
        resultsContainer.innerHTML = '';
        
        if (results.length === 0) {
            noResults.classList.remove('hidden');
            resultsContainer.classList.add('hidden');
            return;
        }

        noResults.classList.add('hidden');
        resultsContainer.classList.remove('hidden');

        results.forEach(libro => {
            const item = document.createElement('div');
            item.className = 'p-3 hover:bg-gray-50 cursor-pointer transition-colors';
            item.innerHTML = `
                <div>
                    <p class="font-medium text-gray-900 text-sm">${libro.nombre}</p>
                    <p class="text-xs text-gray-600">Código: ${libro.codigo_barras || 'Sin código'}</p>
                </div>
            `;
            
            item.addEventListener('click', () => selectLibro(libro));
            resultsContainer.appendChild(item);
        });
    }

    // Función para seleccionar un libro
    function selectLibro(libro) {
        if (!libro) {
            // "Todos los libros" seleccionado
            clearSelection();
            return;
        }

        selectedLibro = libro;
        hiddenInput.value = libro.id;
        hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
        
        selectedNombre.textContent = libro.nombre;
        selectedCodigo.textContent = 'Código: ' + (libro.codigo_barras || 'Sin código');
        
        selectedDiv.classList.remove('hidden');
        searchInput.value = '';
        dropdown.classList.add('hidden');
        clearBtn.classList.add('hidden');
    }

    // Función para limpiar selección
    function clearSelection() {
        selectedLibro = null;
        hiddenInput.value = '';
        hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
        selectedDiv.classList.add('hidden');
        searchInput.value = '';
        clearBtn.classList.add('hidden');
        searchInput.placeholder = 'Buscar libro...';
    }

    // Event listeners
    searchInput.addEventListener('input', (e) => {
        const query = e.target.value;
        
        if (query.length === 0) {
            dropdown.classList.add('hidden');
            clearBtn.classList.add('hidden');
            return;
        }

        clearBtn.classList.remove('hidden');
        const results = searchLibros(query);
        renderResults(results);
        dropdown.classList.remove('hidden');
    });

    searchInput.addEventListener('focus', (e) => {
        if (selectedLibro) return; // No mostrar dropdown si ya hay selección
        
        if (e.target.value.length > 0) {
            const results = searchLibros(e.target.value);
            renderResults(results);
            dropdown.classList.remove('hidden');
        } else {
            // Mostrar primeros 20 libros
            renderResults(libros.slice(0, 20));
            dropdown.classList.remove('hidden');
        }
    });

    clearBtn.addEventListener('click', () => {
        searchInput.value = '';
        dropdown.classList.add('hidden');
        clearBtn.classList.add('hidden');
        searchInput.focus();
    });

    removeBtn.addEventListener('click', clearSelection);

    allOption.addEventListener('click', () => {
        clearSelection();
        dropdown.classList.add('hidden');
        searchInput.focus();
    });

    window.addEventListener('libro-search-filter:update', (event) => {
        if (!event.detail || event.detail.name !== nameId) {
            return;
        }

        libros = Array.isArray(event.detail.libros) ? event.detail.libros : [];
        clearSelection();
        searchInput.placeholder = event.detail.placeholder || 'Buscar libro...';
        dropdown.classList.add('hidden');

        if (event.detail.selected) {
            const selected = libros.find(libro => libro.id == event.detail.selected);
            if (selected) {
                selectLibro(selected);
            }
        }
    });

    // Cerrar dropdown al hacer click fuera
    document.addEventListener('click', (e) => {
        if (!e.target.closest(`#${nameId}_search`) && 
            !e.target.closest(`#${nameId}_dropdown`)) {
            dropdown.classList.add('hidden');
        }
    });

    // Si hay un libro pre-seleccionado (old value o selected)
    @if(old($name, $selected))
        const preselectedId = {{ old($name, $selected) }};
        const preselected = libros.find(l => l.id == preselectedId);
        if (preselected) {
            selectLibro(preselected);
        }
    @endif
})();
</script>
