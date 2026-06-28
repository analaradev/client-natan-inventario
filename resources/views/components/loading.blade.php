<div id="loading-spinner" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white p-5 rounded-lg shadow-lg">
        <div class="flex items-center space-x-3">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-500"></div>
            <span class="text-gray-700 font-medium" id="loading-message">Cargando...</span>
        </div>
    </div>
</div>

<script>
const loadingSpinner = {
    show(message = 'Cargando...') {
        const spinner = document.getElementById('loading-spinner');
        const messageEl = document.getElementById('loading-message');
        
        messageEl.textContent = message;
        spinner.classList.remove('hidden');
    },
    
    hide() {
        const spinner = document.getElementById('loading-spinner');
        spinner.classList.add('hidden');
    }
};

// Hacer global
window.loadingSpinner = loadingSpinner;

// Sistema de loading para botones de submit
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function(e) {
            setTimeout(() => {
                if (e.defaultPrevented || !this.checkValidity()) {
                    return;
                }

                // Buscar el botón de submit dentro del formulario
                const submitButton = this.querySelector('button[type="submit"]');
                
                if (submitButton && !submitButton.disabled) {
                    // Guardar el contenido original en el botón para restaurarlo después
                    submitButton.setAttribute('data-original-content', submitButton.innerHTML);
                    
                    // Deshabilitar el botón
                    submitButton.disabled = true;
                    
                    // Agregar spinner al botón
                    submitButton.innerHTML = `
                        <span class="inline-flex items-center">
                            <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Procesando...
                        </span>
                    `;
                }
            }, 0);
        });
    });
    
    // Si hay errores de validación, restaurar los botones
    const hasErrors = document.querySelectorAll('.text-red-600, .text-red-500, .border-red-500').length > 0;
    if (hasErrors) {
        document.querySelectorAll('button[type="submit"][data-original-content]').forEach(button => {
            button.innerHTML = button.getAttribute('data-original-content');
            button.disabled = false;
            button.removeAttribute('data-original-content');
        });
    }
});
</script>

<style>
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.animate-spin {
    animation: spin 1s linear infinite;
}
</style>
