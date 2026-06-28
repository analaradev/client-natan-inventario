/**
 * Cliente Search Dynamic
 * Manages the dynamic cliente search functionality (similar to LibroSearchDynamic)
 */

class ClienteSearchDynamic {
    constructor(containerId) {
        this.containerId = containerId;
        this.selectedCliente = null;
        this.searchTimeout = null;
        
        // Get all DOM elements
        this.container = document.getElementById(containerId);
        if (!this.container) {
            console.error('[Cliente Search Dynamic] Container not found:', containerId);
            return;
        }
        
        this.searchInput = this.container.querySelector('.cliente-search-input');
        this.dropdown = this.container.querySelector('.cliente-dropdown');
        this.resultsContainer = this.container.querySelector('.cliente-results');
        this.noResults = this.container.querySelector('.cliente-no-results');
        this.allOption = this.container.querySelector('.cliente-all-option');
        this.hiddenInput = this.container.querySelector('.cliente-id-input');
        this.selectedDiv = this.container.querySelector('.cliente-selected');
        this.selectedNombre = this.container.querySelector('.cliente-selected-nombre');
        this.selectedCodigo = this.container.querySelector('.cliente-selected-codigo');
        this.selectedTelefono = this.container.querySelector('.cliente-selected-telefono');
        this.removeBtn = this.container.querySelector('.cliente-remove-btn');
        this.clearBtn = this.container.querySelector('.cliente-clear-btn');
        
        // Initialize
        this.init();
    }
    
    init() {
        // Check if already initialized
        if (this.searchInput.dataset.initialized === 'true') {
            console.log('[Cliente Search Dynamic] Already initialized:', this.containerId);
            return;
        }
        
        console.log('[Cliente Search Dynamic] Initializing:', this.containerId);
        
        // Event listeners
        this.searchInput.addEventListener('input', (e) => this.handleInput(e));
        this.searchInput.addEventListener('focus', (e) => this.handleFocus(e));
        this.clearBtn.addEventListener('click', () => this.handleClear());
        this.removeBtn.addEventListener('click', () => this.clearSelection());
        
        // Click en opción "Crear nuevo cliente"
        if (this.allOption) {
            this.allOption.addEventListener('click', () => this.handleNewCliente());
        }
        
        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!this.container.contains(e.target)) {
                this.dropdown.classList.add('hidden');
            }
        });
        
        // Mark as initialized
        this.searchInput.dataset.initialized = 'true';
        
        // Load preselected cliente if exists (igual que libros)
        if (this.hiddenInput.value) {
            const nombre = this.hiddenInput.getAttribute('data-nombre');
            const telefono = this.hiddenInput.getAttribute('data-telefono');
            
            if (nombre) {
                const preselectedId = parseInt(this.hiddenInput.value);
                const preselected = {
                    id: preselectedId,
                    nombre: nombre,
                    telefono: telefono || ''
                };
                this.selectCliente(preselected);
            }
        }
        
        // Cargar cliente recién creado si existe
        this.loadNewlyCreatedCliente();
    }
    
    handleInput(e) {
        const query = e.target.value.trim();
        
        // Cancelar búsqueda previa
        if (this.searchTimeout) {
            clearTimeout(this.searchTimeout);
        }
        
        if (query.length === 0) {
            this.dropdown.classList.add('hidden');
            this.clearBtn.classList.add('hidden');
            return;
        }
        
        this.clearBtn.classList.remove('hidden');
        
        if (query.length < 2) {
            return;
        }
        
        // Esperar 300ms antes de buscar (AJAX)
        this.searchTimeout = setTimeout(() => {
            this.searchClientes(query);
        }, 300);
    }
    
    handleFocus(e) {
        if (this.selectedCliente) return;
        
        const query = e.target.value.trim();
        
        if (query.length > 0) {
            // Si hay texto, buscar
            this.searchClientes(query);
        } else {
            // Si no hay texto, cargar primeros clientes (igual que libros)
            this.loadInitialClientes();
        }
    }
    
    handleClear() {
        this.searchInput.value = '';
        this.dropdown.classList.add('hidden');
        this.clearBtn.classList.add('hidden');
        this.searchInput.focus();
    }
    
    handleNewCliente() {
        // Guardar estado del formulario
        if (window.ventaFormManagerInstance) {
            window.ventaFormManagerInstance.saveFormState();
        } else if (window.ventaFormManager) {
            window.ventaFormManager.saveFormState();
        }

        
        // Redirigir a crear cliente
        const returnUrl = encodeURIComponent(window.location.href);
        window.location.href = `/clientes/create?return_url=${returnUrl}`;
    }
    
    async loadInitialClientes() {
        try {
            // Cargar primeros 20 clientes (igual que libros)
            const response = await fetch('/clientes/search?q=', {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });
            
            if (!response.ok) {
                console.error('[Cliente Search] Response status:', response.status);
                throw new Error('Error al cargar clientes (status: ' + response.status + ')');
            }
            
            const clientes = await response.json();
            console.log('[Cliente Search] Clientes cargados:', clientes.length);
            
            if (!Array.isArray(clientes)) {
                console.error('[Cliente Search] Response is not an array:', clientes);
                this.renderError();
                return;
            }
            
            this.renderResults(clientes.slice(0, 20)); // Limitar a 20 como libros
            this.dropdown.classList.remove('hidden');
        } catch (error) {
            console.error('[Cliente Search Dynamic] Error al cargar clientes iniciales:', error);
            this.renderError();
        }
    }
    
    async searchClientes(query) {
        try {
            const response = await fetch(`/clientes/search?q=${encodeURIComponent(query)}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });
            
            if (!response.ok) {
                console.error('[Cliente Search] Response status:', response.status);
                throw new Error('Error en la búsqueda (status: ' + response.status + ')');
            }
            
            const clientes = await response.json();
            console.log('[Cliente Search] Búsqueda completada, resultados:', clientes.length);
            
            if (!Array.isArray(clientes)) {
                console.error('[Cliente Search] Response is not an array:', clientes);
                this.renderError();
                return;
            }
            
            this.renderResults(clientes);
            this.dropdown.classList.remove('hidden');
        } catch (error) {
            console.error('[Cliente Search Dynamic] Error al buscar clientes:', error);
            this.renderError();
        }
    }
    
    renderResults(clientes) {
        this.resultsContainer.innerHTML = '';
        
        if (clientes.length === 0) {
            this.noResults.classList.remove('hidden');
            this.resultsContainer.classList.add('hidden');
            return;
        }
        
        this.noResults.classList.add('hidden');
        this.resultsContainer.classList.remove('hidden');
        
        clientes.forEach(cliente => {
            const item = document.createElement('div');
            item.className = 'p-3 hover:bg-gray-50 cursor-pointer transition-colors';
            // Formato idéntico al de libros: nombre en una línea, detalles en otra con bullets
            item.innerHTML = `
                <div>
                    <p class="font-medium text-gray-900 text-sm">${this.escapeHtml(cliente.nombre)}</p>
                    <p class="text-xs text-gray-600">
                        Teléfono: ${this.escapeHtml(cliente.telefono || 'Sin teléfono')}
                    </p>
                </div>
            `;
            
            item.addEventListener('click', () => this.selectCliente(cliente));
            this.resultsContainer.appendChild(item);
        });
    }
    
    renderError() {
        this.resultsContainer.innerHTML = `
            <div class="p-4 text-center text-red-500">
                <i class="fas fa-exclamation-triangle text-2xl mb-2"></i>
                <p class="text-sm">Error al buscar clientes</p>
            </div>
        `;
        this.resultsContainer.classList.remove('hidden');
        this.noResults.classList.add('hidden');
        this.dropdown.classList.remove('hidden');
    }
    
    selectCliente(cliente) {
        if (!cliente) {
            this.clearSelection();
            return;
        }
        
        this.selectedCliente = cliente;
        this.hiddenInput.value = cliente.id;
        this.hiddenInput.setAttribute('data-nombre', cliente.nombre);
        this.hiddenInput.setAttribute('data-telefono', cliente.telefono || '');
        
        // Actualizar vista del cliente seleccionado (estructura idéntica a libros)
        this.selectedNombre.textContent = cliente.nombre;
        this.selectedCodigo.textContent = 'ID: ' + cliente.id;
        this.selectedTelefono.textContent = cliente.telefono || 'Sin teléfono';
        
        this.selectedDiv.classList.remove('hidden');
        this.searchInput.value = '';
        this.dropdown.classList.add('hidden');
        this.clearBtn.classList.add('hidden');
        
        // Dispatch change event (igual que libros)
        this.hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
        
        // Dispatch evento personalizado para notificar que se seleccionó un cliente
        document.dispatchEvent(new CustomEvent('clienteSeleccionado', {
            detail: { cliente: cliente }
        }));
    }
    
    clearSelection() {
        this.selectedCliente = null;
        this.hiddenInput.value = '';
        this.hiddenInput.removeAttribute('data-nombre');
        this.hiddenInput.removeAttribute('data-telefono');
        this.selectedDiv.classList.add('hidden');
        this.searchInput.value = '';
        this.clearBtn.classList.add('hidden');
        this.searchInput.placeholder = 'Buscar cliente...';
        
        // Dispatch change event
        this.hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
        
        // Dispatch evento personalizado para notificar que se removió el cliente
        document.dispatchEvent(new CustomEvent('clienteRemovido'));
    }
    
    /**
     * Cargar cliente recién creado desde sessionStorage
     */
    loadNewlyCreatedCliente() {
        const clienteId = sessionStorage.getItem('nuevo_cliente_id');
        const clienteNombre = sessionStorage.getItem('nuevo_cliente_nombre');
        const clienteTelefono = sessionStorage.getItem('nuevo_cliente_telefono');
        
        if (clienteId && clienteNombre) {
            // Seleccionar el cliente recién creado
            this.selectCliente({
                id: parseInt(clienteId),
                nombre: clienteNombre,
                telefono: clienteTelefono || ''
            });
            
            // Limpiar sessionStorage
            sessionStorage.removeItem('nuevo_cliente_id');
            sessionStorage.removeItem('nuevo_cliente_nombre');
            sessionStorage.removeItem('nuevo_cliente_telefono');
            
            console.log('[Cliente Search Dynamic] Cliente recién creado cargado:', clienteNombre);
        }
    }
    
    /**
     * Escape HTML to prevent XSS
     */
    escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text ? text.replace(/[&<>"']/g, m => map[m]) : '';
    }
}

// Global initialization function
window.initClienteSearch = function(containerId) {
    return new ClienteSearchDynamic(containerId);
};

// Global instances storage
window.clienteSearchInstances = window.clienteSearchInstances || {};

// Auto-initialize on DOM ready (igual que libro-search-dynamic.js)
document.addEventListener('DOMContentLoaded', function() {
    console.log('[Cliente Search] Auto-initializing containers');
    const containers = document.querySelectorAll('.cliente-search-container');
    containers.forEach(container => {
        const id = container.id;
        if (id && !window.clienteSearchInstances[id]) {
            console.log('[Cliente Search] Initializing:', id);
            window.clienteSearchInstances[id] = window.initClienteSearch(id);
        }
    });
});
