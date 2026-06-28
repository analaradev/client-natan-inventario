/**
 * Libro Search Dynamic
 * Manages the dynamic libro search functionality for multiple instances
 */

class LibroSearchDynamic {
    constructor(containerId, librosData) {
        this.containerId = containerId;
        this.libros = librosData;
        this.selectedLibro = null;
        
        // Get all DOM elements
        this.container = document.getElementById(containerId);
        if (!this.container) {
            console.error('[Libro Search Dynamic] Container not found:', containerId);
            return;
        }
        
        this.searchBox = this.container.querySelector('.libro-search-box');
        this.searchInput = this.container.querySelector('.libro-search-input');
        this.dropdown = this.container.querySelector('.libro-dropdown');
        this.resultsContainer = this.container.querySelector('.libro-results');
        this.noResults = this.container.querySelector('.libro-no-results');
        this.allOption = this.container.querySelector('.libro-all-option');
        this.hiddenInput = this.container.querySelector('.libro-id-input');
        this.selectedDiv = this.container.querySelector('.libro-selected');
        this.selectedNombre = this.container.querySelector('.libro-selected-nombre');
        this.selectedCodigo = this.container.querySelector('.libro-selected-codigo');
        this.selectedPrecio = this.container.querySelector('.libro-selected-precio');
        this.selectedStock = this.container.querySelector('.libro-selected-stock');
        this.removeBtn = this.container.querySelector('.libro-remove-btn');
        this.clearBtn = this.container.querySelector('.libro-clear-btn');
        
        // Initialize
        this.init();
    }
    
    init() {
        // Check if already initialized
        if (this.searchInput.dataset.initialized === 'true') {
            console.log('[Libro Search Dynamic] Already initialized:', this.containerId);
            return;
        }
        
        console.log('[Libro Search Dynamic] Initializing:', this.containerId);
        
        // Event listeners
        this.searchInput.addEventListener('input', (e) => this.handleInput(e));
        this.searchInput.addEventListener('focus', (e) => this.handleFocus(e));
        this.clearBtn.addEventListener('click', () => this.handleClear());
        this.removeBtn.addEventListener('click', () => this.clearSelection());
        this.allOption.addEventListener('click', () => this.handleAllOption());
        
        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!this.container.contains(e.target)) {
                this.dropdown.classList.add('hidden');
            }
        });
        
        // Mark as initialized
        this.searchInput.dataset.initialized = 'true';
        
        // Load preselected libro if exists
        if (this.hiddenInput.value) {
            const preselectedId = parseInt(this.hiddenInput.value);
            const preselected = this.libros.find(l => l.id == preselectedId);
            if (preselected) {
                this.selectLibro(preselected);
            }
        }
    }
    
    handleInput(e) {
        const query = e.target.value;
        
        if (query.length === 0) {
            this.dropdown.classList.add('hidden');
            this.clearBtn.classList.add('hidden');
            return;
        }
        
        this.clearBtn.classList.remove('hidden');
        const results = this.searchLibros(query);
        this.renderResults(results);
        this.dropdown.classList.remove('hidden');
    }
    
    handleFocus(e) {
        if (this.selectedLibro) return;
        
        if (e.target.value.length > 0) {
            const results = this.searchLibros(e.target.value);
            this.renderResults(results);
            this.dropdown.classList.remove('hidden');
        } else {
            this.renderResults(this.libros.slice(0, 20));
            this.dropdown.classList.remove('hidden');
        }
    }
    
    handleClear() {
        this.searchInput.value = '';
        this.dropdown.classList.add('hidden');
        this.clearBtn.classList.add('hidden');
        this.searchInput.focus();
    }
    
    handleAllOption() {
        this.clearSelection();
        this.dropdown.classList.add('hidden');
        this.searchInput.focus();
    }
    
    normalizeText(text) {
        return text.toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '');
    }
    
    searchLibros(query) {
        if (!query || query.length < 1) {
            return this.libros.slice(0, 20);
        }
        
        const normalizedQuery = this.normalizeText(query);
        
        return this.libros.filter(libro => {
            const nombre = this.normalizeText(libro.nombre);
            const codigo = this.normalizeText(libro.codigo_barras || '');
            
            return nombre.includes(normalizedQuery) || codigo.includes(normalizedQuery);
        }).slice(0, 20);
    }
    
    renderResults(results) {
        this.resultsContainer.innerHTML = '';
        
        if (results.length === 0) {
            this.noResults.classList.remove('hidden');
            this.resultsContainer.classList.add('hidden');
            return;
        }
        
        this.noResults.classList.add('hidden');
        this.resultsContainer.classList.remove('hidden');
        
        results.forEach(libro => {
            const item = document.createElement('div');
            item.className = 'p-3 hover:bg-gray-50 cursor-pointer transition-colors';
            
            const isSub = libro.cantidad_disponible !== undefined;
            const stockDisp = isSub ? libro.cantidad_disponible : Math.max(0, (libro.stock || 0) - (libro.stock_apartado || 0));
            const stockFisico = isSub ? libro.cantidad_disponible : (libro.stock || 0);
            const stockApartado = isSub ? 0 : (libro.stock_apartado || 0);

            let stockDetailsText = `Stock: ${stockDisp}`;
            if (!isSub && libro.stock_apartado !== undefined) {
                stockDetailsText = `Disponible: ${stockDisp} (Apartados: ${stockApartado} | Físico: ${stockFisico})`;
            }
            item.innerHTML = `
                <div>
                    <p class="font-medium text-gray-900 text-sm">${libro.nombre}</p>
                    <p class="text-xs text-gray-600">
                        <span>Código: ${libro.codigo_barras || 'Sin código'}</span>
                        <span class="mx-2">•</span>
                        <span class="text-green-700 font-medium">${stockDetailsText}</span>
                        <span class="mx-2">•</span>
                        <span class="text-blue-700">$${parseFloat(libro.precio).toFixed(2)}</span>
                    </p>
                </div>
            `;
            
            item.addEventListener('click', () => this.selectLibro(libro));
            this.resultsContainer.appendChild(item);
        });
    }
    
    selectLibro(libro) {
        if (!libro) {
            this.clearSelection();
            return;
        }
        
        this.selectedLibro = libro;
        this.hiddenInput.value = libro.id;
        this.hiddenInput.setAttribute('data-precio', libro.precio);
        
        const isSub = libro.cantidad_disponible !== undefined;
        const stockDisp = isSub ? libro.cantidad_disponible : Math.max(0, (libro.stock || 0) - (libro.stock_apartado || 0));
        const stockFisico = isSub ? libro.cantidad_disponible : (libro.stock || 0);
        const stockApartado = isSub ? 0 : (libro.stock_apartado || 0);

        this.hiddenInput.setAttribute('data-stock', stockDisp);
        
        this.selectedNombre.textContent = libro.nombre;
        this.selectedCodigo.textContent = 'Código: ' + (libro.codigo_barras || 'Sin código');
        this.selectedPrecio.textContent = '$' + parseFloat(libro.precio).toFixed(2);
        
        let stockSelectText = stockDisp + ' unidades';
        if (!isSub && libro.stock_apartado !== undefined) {
            stockSelectText = `Disponible: ${stockDisp} | Apartados: ${stockApartado} | Físico: ${stockFisico}`;
        }
        this.selectedStock.textContent = stockSelectText;
        
        this.selectedDiv.classList.remove('hidden');
        this.searchBox.classList.add('hidden');
        this.searchInput.value = '';
        this.dropdown.classList.add('hidden');
        this.clearBtn.classList.add('hidden');
        
        // Update precio_unitario hidden input (for forms that need it)
        const libroItem = this.container.closest('.libro-item');
        if (libroItem) {
            const precioUnitarioInput = libroItem.querySelector('.precio-unitario-input');
            if (precioUnitarioInput) {
                precioUnitarioInput.value = libro.precio;
            }
        }
        
        // Dispatch change event for calculations
        this.hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
    }
    
    clearSelection() {
        this.selectedLibro = null;
        this.hiddenInput.value = '';
        this.hiddenInput.removeAttribute('data-precio');
        this.hiddenInput.removeAttribute('data-stock');
        this.selectedDiv.classList.add('hidden');
        this.searchBox.classList.remove('hidden');
        this.searchInput.value = '';
        this.clearBtn.classList.add('hidden');
        this.searchInput.placeholder = 'Buscar libro...';
        
        // Clear precio_unitario hidden input
        const libroItem = this.container.closest('.libro-item');
        if (libroItem) {
            const precioUnitarioInput = libroItem.querySelector('.precio-unitario-input');
            if (precioUnitarioInput) {
                precioUnitarioInput.value = 0;
            }
        }
        
        // Dispatch change event
        this.hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
    }
    
    // Update libros data dynamically
    updateLibrosData(newLibrosData) {
        console.log('[Libro Search Dynamic] Updating libros data:', this.containerId);
        this.libros = newLibrosData;
    }
}

// Global initialization function
window.initLibroSearch = function(containerId, librosData) {
    return new LibroSearchDynamic(containerId, librosData);
};

// Global instances storage
window.libroSearchInstances = window.libroSearchInstances || {};
