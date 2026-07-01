/**
 * Toggle entre inventario general y subinventario
 */
window.toggleInventarioTipo = function() {
    const tipoInventario = document.querySelector('input[name="tipo_inventario"]:checked')?.value;
    if (!tipoInventario) return;
    
    const subinventarioSelector = document.getElementById('subinventarioSelector');
    const subinventarioSelect = document.getElementById('subinventario_id');
    const addLibroBtn = document.getElementById('addLibroBtn');
    
    if (!subinventarioSelector || !subinventarioSelect) return;
    
    if (tipoInventario === 'subinventario') {
        subinventarioSelector.style.display = 'block';
        subinventarioSelect.required = true;
        seleccionarUnicoSubinventario(subinventarioSelect);
        
        // Deshabilitar botón de agregar libro hasta que se seleccione un subinventario
        if (addLibroBtn) {
            const hasSubinventarioSelected = subinventarioSelect.value !== '';
            addLibroBtn.disabled = !hasSubinventarioSelected;
            if (!hasSubinventarioSelected) {
                addLibroBtn.classList.add('opacity-50', 'cursor-not-allowed');
            } else {
                addLibroBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            }
        }
        
        cargarLibrosSubinventario();
        
        // Limpiar libros al cambiar a subinventario
        if (typeof limpiarLibrosFormulario === 'function') {
            limpiarLibrosFormulario();
        }
    } else {
        subinventarioSelector.style.display = 'none';
        subinventarioSelect.required = false;
        subinventarioSelect.value = '';
        
        // Habilitar botón de agregar libro para inventario general
        if (addLibroBtn) {
            addLibroBtn.disabled = false;
            addLibroBtn.classList.remove('opacity-50', 'cursor-not-allowed');
        }
        
        // Limpiar libros al cambiar a inventario general
        if (typeof limpiarLibrosFormulario === 'function') {
            limpiarLibrosFormulario();
        }
    }
};

function seleccionarUnicoSubinventario(subinventarioSelect) {
    if (!subinventarioSelect || subinventarioSelect.value) {
        return;
    }

    const opciones = Array.from(subinventarioSelect.options).filter(option => option.value);
    if (opciones.length === 1) {
        subinventarioSelect.value = opciones[0].value;
    }
}

/**
 * Cargar libros del subinventario seleccionado
 */
window.cargarLibrosSubinventario = function() {
    const subinventarioSelect = document.getElementById('subinventario_id');
    const selectedOption = subinventarioSelect?.options[subinventarioSelect.selectedIndex];
    const addLibroBtn = document.getElementById('addLibroBtn');
    
    if (!subinventarioSelect || !selectedOption) return;
    
    if (selectedOption.value) {
        try {
            const librosData = JSON.parse(selectedOption.getAttribute('data-libros') || '[]');
            window.ventaLibrosData = librosData;
            
            // Habilitar botón de agregar libro cuando se selecciona un subinventario
            if (addLibroBtn) {
                addLibroBtn.disabled = false;
                addLibroBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            }
        } catch (e) {
            console.error('Error parsing libros data:', e);
        }
    } else {
        // Deshabilitar botón si no hay subinventario seleccionado
        if (addLibroBtn) {
            addLibroBtn.disabled = true;
            addLibroBtn.classList.add('opacity-50', 'cursor-not-allowed');
        }
        window.ventaLibrosData = [];
    }
    
    // Limpiar libros al cambiar de subinventario
    if (typeof limpiarLibrosFormulario === 'function') {
        limpiarLibrosFormulario();
    }
};

/**
 * Función para limpiar libros del formulario
 */
window.limpiarLibrosFormulario = function() {
    const librosContainer = document.getElementById('librosContainer');
    if (!librosContainer) return;
    
    let emptyMessage = document.getElementById('emptyMessage');
    
    // Limpiar solo los items de libros (elementos con clase libro-item)
    const libroItems = librosContainer.querySelectorAll('.libro-item');
    libroItems.forEach(item => item.remove());
    
    // Crear mensaje vacío si no existe
    if (!emptyMessage) {
        emptyMessage = document.createElement('div');
        emptyMessage.id = 'emptyMessage';
        emptyMessage.className = 'text-center py-12 text-gray-500 bg-gray-50 rounded-lg border-2 border-dashed border-gray-300';
        emptyMessage.innerHTML = '<i class="fas fa-book text-4xl mb-3"></i><p>No hay libros agregados. Haz clic en "Agregar Libro" para empezar.</p>';
        if (librosContainer.nextSibling) {
            librosContainer.parentElement.insertBefore(emptyMessage, librosContainer.nextSibling);
        } else {
            librosContainer.parentElement.appendChild(emptyMessage);
        }
    }
    
    // Mostrar mensaje vacío
    emptyMessage.style.display = 'block';
    
    // Actualizar displays de totales a cero
    const subtotalDisplay = document.getElementById('subtotalDisplay');
    const descuentoDisplay = document.getElementById('descuentoDisplay');
    const totalDisplay = document.getElementById('totalDisplay');
    
    if (subtotalDisplay) subtotalDisplay.textContent = '$0.00';
    if (descuentoDisplay) descuentoDisplay.textContent = '-$0.00';
    if (totalDisplay) totalDisplay.textContent = '$0.00';
};

// Inicializar al cargar el DOM
document.addEventListener('DOMContentLoaded', function() {
    // Ejecutar toggle inicial
    if (typeof toggleInventarioTipo === 'function') {
        toggleInventarioTipo();
    }
    
    // Si hay old data de subinventario, cargar los libros
    const subinventarioSelect = document.getElementById('subinventario_id');
    if (subinventarioSelect && subinventarioSelect.value) {
        cargarLibrosSubinventario();
    }
});
