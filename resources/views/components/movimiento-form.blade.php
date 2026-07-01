@props([
    'action',
    'libros' => [],
    'subinventarios' => [],
    'submitText' => 'Registrar Movimiento'
])

<form action="{{ $action }}" method="POST" id="movimientoForm">
    @csrf

    <div id="stockValidationBanner" class="hidden mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-red-800">
        <div class="flex items-start gap-3">
            <i class="fas fa-exclamation-triangle mt-0.5 text-red-500"></i>
            <div>
                <p class="font-semibold">Stock insuficiente</p>
                <p class="text-sm" id="stockValidationMessage"></p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Tipo de inventario / ubicación afectada -->
        <div id="origenStockContainer" class="lg:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Tipo de Inventario <span class="text-red-500">*</span>
            </label>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Inventario General -->
                <label class="relative block cursor-pointer">
                    <input
                        type="radio"
                        name="origen_stock"
                        value="general"
                        id="origen_stock_general"
                        {{ old('origen_stock', 'general') == 'general' ? 'checked' : '' }}
                        class="radio-inventario sr-only"
                    >
                    <div class="inventario-box p-4 bg-white border-2 border-gray-200 rounded-lg transition-all hover:border-blue-300 hover:shadow-md">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <div class="inventario-icon flex-shrink-0 w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center transition-colors">
                                    <i class="fas fa-warehouse text-xl text-blue-600"></i>
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-900">Inventario General</p>
                                    <p class="text-sm text-gray-500">Stock disponible en bodega</p>
                                </div>
                            </div>
                            <div class="flex-shrink-0">
                                <div class="inventario-check w-6 h-6 border-2 border-gray-300 rounded-full flex items-center justify-center transition-all">
                                    <i class="fas fa-check text-xs text-white opacity-0"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </label>

                <!-- Subinventario -->
                <label class="relative block cursor-pointer">
                    <input
                        type="radio"
                        name="origen_stock"
                        value="subinventario"
                        id="origen_stock_subinventario"
                        {{ old('origen_stock') == 'subinventario' ? 'checked' : '' }}
                        class="radio-inventario sr-only"
                    >
                    <div class="inventario-box p-4 bg-white border-2 border-gray-200 rounded-lg transition-all hover:border-green-300 hover:shadow-md">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <div class="inventario-icon flex-shrink-0 w-12 h-12 bg-green-100 rounded-full flex items-center justify-center transition-colors">
                                    <i class="fas fa-box-open text-xl text-green-600"></i>
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-900">Subinventario</p>
                                    <p class="text-sm text-gray-500">Stock asignado a punto de venta</p>
                                </div>
                            </div>
                            <div class="flex-shrink-0">
                                <div class="inventario-check w-6 h-6 border-2 border-gray-300 rounded-full flex items-center justify-center transition-all">
                                    <i class="fas fa-check text-xs text-white opacity-0"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </label>
            </div>

            <style>
                #origenStockContainer input[type="radio"][value="general"]:checked ~ .inventario-box {
                    border-color: #3B82F6 !important;
                    background-color: #EFF6FF !important;
                }
                #origenStockContainer input[type="radio"][value="general"]:checked ~ .inventario-box .inventario-icon {
                    background-color: #3B82F6 !important;
                }
                #origenStockContainer input[type="radio"][value="general"]:checked ~ .inventario-box .inventario-icon i {
                    color: white !important;
                }
                #origenStockContainer input[type="radio"][value="general"]:checked ~ .inventario-box .inventario-check {
                    background-color: #3B82F6 !important;
                    border-color: #3B82F6 !important;
                }
                #origenStockContainer input[type="radio"][value="general"]:checked ~ .inventario-box .inventario-check i {
                    opacity: 1 !important;
                }

                #origenStockContainer input[type="radio"][value="subinventario"]:checked ~ .inventario-box {
                    border-color: #10B981 !important;
                    background-color: #ECFDF5 !important;
                }
                #origenStockContainer input[type="radio"][value="subinventario"]:checked ~ .inventario-box .inventario-icon {
                    background-color: #10B981 !important;
                }
                #origenStockContainer input[type="radio"][value="subinventario"]:checked ~ .inventario-box .inventario-icon i {
                    color: white !important;
                }
                #origenStockContainer input[type="radio"][value="subinventario"]:checked ~ .inventario-box .inventario-check {
                    background-color: #10B981 !important;
                    border-color: #10B981 !important;
                }
                #origenStockContainer input[type="radio"][value="subinventario"]:checked ~ .inventario-box .inventario-check i {
                    opacity: 1 !important;
                }
            </style>
            @error('origen_stock')
                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <div id="subinventarioContainer" class="hidden lg:col-span-2">
            <label for="subinventario_id" class="block text-sm font-medium text-gray-700 mb-2">
                Subinventario <span class="text-red-500">*</span>
            </label>
            <div class="relative">
                <span class="absolute left-3 top-2.5 text-gray-400">
                    <i class="fas fa-store"></i>
                </span>
                <select name="subinventario_id" id="subinventario_id"
                        class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 @error('subinventario_id') border-red-500 @enderror">
                    <option value="">Selecciona el subinventario</option>
                    @foreach($subinventarios as $subinventario)
                        <option value="{{ $subinventario->id }}" {{ old('subinventario_id', $subinventarios->count() === 1 ? $subinventario->id : null) == $subinventario->id ? 'selected' : '' }}>
                            #{{ $subinventario->id }} - {{ $subinventario->descripcion ?? 'Sin descripción' }}
                            @if($subinventario->fecha_subinventario)
                                ({{ $subinventario->fecha_subinventario->format('d/m/Y') }})
                            @endif
                        </option>
                    @endforeach
                </select>
            </div>
            <p class="mt-1 text-sm text-gray-500">
                <i class="fas fa-info-circle"></i> El movimiento afectará directamente el stock disponible en este subinventario
            </p>
            @error('subinventario_id')
                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <!-- Selección de Libro con Buscador -->
        <div class="lg:col-span-2">
            <x-libro-search-filter 
                name="libro_id"
                :libros="$libros"
                :selected="old('libro_id')"
                label="Libro"
                :required="true"
            />
            @error('libro_id')
                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <!-- Tipo de Movimiento -->
        <div class="lg:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Tipo de Movimiento <span class="text-red-500">*</span>
            </label>
            <div class="grid grid-cols-2 gap-4">
                <label class="cursor-pointer">
                    <input type="radio" name="tipo_movimiento" value="entrada" 
                           class="hidden peer" 
                           {{ old('tipo_movimiento') == 'entrada' ? 'checked' : '' }}
                           required>
                    <div class="p-4 border-2 border-gray-300 rounded-lg text-center peer-checked:border-green-500 peer-checked:bg-green-50 transition-all hover:border-green-300">
                        <i class="fas fa-arrow-down text-3xl text-green-600 mb-2"></i>
                        <p class="font-medium text-gray-900">Entrada</p>
                        <p class="text-xs text-gray-500">Agregar al inventario</p>
                    </div>
                </label>
                <label class="cursor-pointer">
                    <input type="radio" name="tipo_movimiento" value="salida" 
                           class="hidden peer"
                           {{ old('tipo_movimiento') == 'salida' ? 'checked' : '' }}
                           required>
                    <div class="p-4 border-2 border-gray-300 rounded-lg text-center peer-checked:border-red-500 peer-checked:bg-red-50 transition-all hover:border-red-300">
                        <i class="fas fa-arrow-up text-3xl text-red-600 mb-2"></i>
                        <p class="font-medium text-gray-900">Salida</p>
                        <p class="text-xs text-gray-500">Retirar del inventario</p>
                    </div>
                </label>
            </div>
            @error('tipo_movimiento')
                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <!-- Tipo de Entrada (se muestra cuando tipo_movimiento = entrada) -->
        <div id="tipoEntradaContainer" class="hidden lg:col-span-2">
            <label for="tipo_entrada" class="block text-sm font-medium text-gray-700 mb-2">
                Motivo de Entrada <span class="text-red-500">*</span>
            </label>
            <div class="relative">
                <span class="absolute left-3 top-2.5 text-gray-400">
                    <i class="fas fa-arrow-down"></i>
                </span>
                <select name="tipo_entrada" id="tipo_entrada" 
                        class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 @error('tipo_entrada') border-red-500 @enderror">
                    <option value="">Selecciona el motivo</option>
                    @foreach(\App\Models\Movimiento::tiposEntrada() as $key => $label)
                        <option value="{{ $key }}" {{ old('tipo_entrada') == $key ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>
            <p class="mt-1 text-sm text-gray-500">
                <i class="fas fa-info-circle"></i> Selecciona el motivo por el cual ingresa el libro al inventario
            </p>
            @error('tipo_entrada')
                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <!-- Tipo de Salida (se muestra cuando tipo_movimiento = salida) -->
        <div id="tipoSalidaContainer" class="hidden lg:col-span-2">
            <label for="tipo_salida" class="block text-sm font-medium text-gray-700 mb-2">
                Motivo de Salida <span class="text-red-500">*</span>
            </label>
            <div class="relative">
                <span class="absolute left-3 top-2.5 text-gray-400">
                    <i class="fas fa-arrow-up"></i>
                </span>
                <select name="tipo_salida" id="tipo_salida" 
                        class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 @error('tipo_salida') border-red-500 @enderror">
                    <option value="">Selecciona el motivo</option>
                    @foreach(\App\Models\Movimiento::tiposSalida() as $key => $label)
                        <option value="{{ $key }}" {{ old('tipo_salida') == $key ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>
            <p class="mt-1 text-sm text-gray-500">
                <i class="fas fa-info-circle"></i> Selecciona el motivo por el cual sale el libro del inventario
            </p>
            @error('tipo_salida')
                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <!-- Cantidad -->
        <div>
            <x-form-number
                name="cantidad"
                label="Cantidad"
                :value="old('cantidad', 1)"
                :required="true"
                :min="1"
                icon="fas fa-hashtag"
                placeholder="Ej: 10"
            />
            <p class="mt-1 text-sm text-gray-500">
                <i class="fas fa-info-circle"></i> Número de unidades del movimiento
            </p>
            
            <!-- Información del Stock Actual -->
            <div id="stockInfo" class="hidden mt-2 p-2 bg-gray-50 border border-gray-200 rounded-lg">
                <p class="text-xs text-gray-700">
                    <i class="fas fa-boxes text-gray-400 mr-1"></i>
                    Stock actual: <span class="font-semibold text-gray-900" id="stockActual">-</span>
                    <span class="mx-2 text-gray-400">•</span>
                    <span id="stockResultanteTexto">-</span>
                </p>
            </div>
        </div>

        <!-- Descuento -->
        <div>
            <label for="descuento" class="block text-sm font-medium text-gray-700 mb-2">
                Descuento (%)
            </label>
            <div class="relative">
                <span class="absolute left-3 top-2.5 text-gray-400">
                    <i class="fas fa-percent"></i>
                </span>
                <input 
                    type="number" 
                    name="descuento" 
                    id="descuento" 
                    step="0.01"
                    min="0"
                    max="100"
                    value="{{ old('descuento', 0) }}"
                    class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 @error('descuento') border-red-500 @enderror"
                    placeholder="0">
            </div>
            <p class="mt-1 text-sm text-gray-500">
                <i class="fas fa-info-circle"></i> Porcentaje de descuento (0-100%)
            </p>
            @error('descuento')
                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
            @enderror
            
            <!-- Información del Precio con Descuento -->
            <div id="precioInfo" class="hidden mt-2 p-2 bg-gray-50 border border-gray-200 rounded-lg">
                <p class="text-xs text-gray-700">
                    <i class="fas fa-dollar-sign text-gray-400 mr-1"></i>
                    Precio original: <span class="font-semibold text-gray-900" id="precioOriginal">-</span>
                    <span class="mx-2 text-gray-400">•</span>
                    <span id="precioFinalTexto">-</span>
                </p>
            </div>
        </div>

        <!-- Fecha -->
        <div>
            <label for="fecha" class="block text-sm font-medium text-gray-700 mb-2">
                Fecha del Movimiento
            </label>
            <div class="relative">
                <span class="absolute left-3 top-2.5 text-gray-400">
                    <i class="fas fa-calendar"></i>
                </span>
                <input 
                    type="date" 
                    name="fecha" 
                    id="fecha" 
                    value="{{ old('fecha', date('Y-m-d')) }}"
                    class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 @error('fecha') border-red-500 @enderror">
            </div>
            <p class="mt-1 text-sm text-gray-500">
                <i class="fas fa-info-circle"></i> Fecha en que ocurrió el movimiento
            </p>
            @error('fecha')
                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <!-- Observaciones -->
        <div class="lg:col-span-2">
            <label for="observaciones" class="block text-sm font-medium text-gray-700 mb-2">
                Observaciones
            </label>
            <textarea 
                name="observaciones" 
                id="observaciones" 
                rows="4"
                maxlength="500"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 @error('observaciones') border-red-500 @enderror"
                placeholder="Notas adicionales sobre este movimiento...">{{ old('observaciones') }}</textarea>
            <p class="mt-1 text-sm text-gray-500">
                <i class="fas fa-info-circle"></i> Máximo 500 caracteres
            </p>
            @error('observaciones')
                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <!-- Botones de Acción -->
        <div class="lg:col-span-2 flex justify-end gap-3 pt-4 border-t border-gray-200">
            <x-button type="button" variant="secondary" icon="fas fa-times" onclick="window.location='{{ route('movimientos.index') }}'">
                Cancelar
            </x-button>
            <x-button type="submit" variant="primary" icon="fas fa-save">
                {{ $submitText }}
            </x-button>
        </div>
    </div>
</form>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const tipoMovimiento = document.querySelectorAll('input[name="tipo_movimiento"]');
    const tipoEntradaContainer = document.getElementById('tipoEntradaContainer');
    const tipoSalidaContainer = document.getElementById('tipoSalidaContainer');
    const stockInfo = document.getElementById('stockInfo');
    const stockActualSpan = document.getElementById('stockActual');
    const stockResultanteTexto = document.getElementById('stockResultanteTexto');
    const cantidadInput = document.getElementById('cantidad');
    const libroIdInput = document.getElementById('libro_id');
    const descuentoInput = document.getElementById('descuento');
    const precioInfo = document.getElementById('precioInfo');
    const precioOriginalSpan = document.getElementById('precioOriginal');
    const precioFinalTexto = document.getElementById('precioFinalTexto');
    const stockValidationBanner = document.getElementById('stockValidationBanner');
    const stockValidationMessage = document.getElementById('stockValidationMessage');
    const origenStockInputs = document.querySelectorAll('input[name="origen_stock"]');
    const subinventarioContainer = document.getElementById('subinventarioContainer');
    const subinventarioSelect = document.getElementById('subinventario_id');
    
    // Verificar que los elementos existan
    if (!cantidadInput || !libroIdInput || !stockInfo || !descuentoInput || !precioInfo) {
        console.error('Elementos del formulario no encontrados');
        return;
    }
    
    let currentStock = null;
    let currentTipo = null;
    let currentPrecio = null;
    const librosData = @json($libros);
    const subinventariosData = @json($subinventarios);

    function currentOrigenStock() {
        const selected = document.querySelector('input[name="origen_stock"]:checked');
        return selected ? selected.value : 'general';
    }

    function subinventarioSeleccionado() {
        if (!subinventarioSelect || !subinventarioSelect.value) {
            return null;
        }

        return subinventariosData.find(item => item.id == subinventarioSelect.value) || null;
    }

    function librosDisponiblesParaUbicacion() {
        if (currentOrigenStock() !== 'subinventario') {
            return librosData;
        }

        const subinventario = subinventarioSeleccionado();
        if (!subinventario) {
            return [];
        }

        return (subinventario.libros || []).map(libro => {
            const base = librosData.find(item => item.id == libro.id) || {};
            return {
                ...base,
                ...libro,
                stock: Number(libro.pivot?.cantidad ?? 0),
            };
        });
    }

    function actualizarBuscadorLibros(preservarSeleccion = false) {
        const esSubinventario = currentOrigenStock() === 'subinventario';
        const librosFiltrados = librosDisponiblesParaUbicacion();
        const selected = preservarSeleccion ? libroIdInput.value : null;
        const placeholder = esSubinventario
            ? (subinventarioSeleccionado() ? 'Buscar libro en este subinventario...' : 'Primero selecciona un subinventario...')
            : 'Buscar libro en inventario general...';

        window.dispatchEvent(new CustomEvent('libro-search-filter:update', {
            detail: {
                name: 'libro_id',
                libros: librosFiltrados,
                placeholder,
                selected,
            },
        }));
    }

    function stockEnSubinventario(libroId) {
        if (!libroId) {
            return null;
        }

        const subinventario = subinventarioSeleccionado();
        const libro = subinventario?.libros?.find(item => item.id == libroId);
        return libro?.pivot?.cantidad ?? 0;
    }

    function refreshSelectedBookStock() {
        if (!libroIdInput.value) {
            stockInfo.classList.add('hidden');
            precioInfo.classList.add('hidden');
            currentStock = null;
            currentPrecio = null;
            return;
        }

        const libro = librosDisponiblesParaUbicacion().find(l => l.id == libroIdInput.value)
            || librosData.find(l => l.id == libroIdInput.value);
        if (!libro) {
            return;
        }

        const origen = currentOrigenStock();
        const subStock = origen === 'subinventario' ? stockEnSubinventario(libro.id) : null;
        currentStock = subStock !== null ? subStock : libro.stock;
        currentPrecio = parseFloat(libro.precio);
        stockActualSpan.textContent = `${currentStock} unidades${origen === 'subinventario' ? ' en subinventario' : ''}`;
        precioOriginalSpan.textContent = `$${currentPrecio.toFixed(2)}`;
        stockInfo.classList.remove('hidden');
        precioInfo.classList.remove('hidden');
        updateStockResultante();
        updatePrecioConDescuento();
    }

    function toggleSubinventarioSelector() {
        const showSubinventario = currentOrigenStock() === 'subinventario';
        subinventarioContainer.classList.toggle('hidden', !showSubinventario);
        if (subinventarioSelect) {
            subinventarioSelect.required = showSubinventario;
            if (showSubinventario && !subinventarioSelect.value && subinventariosData.length === 1) {
                subinventarioSelect.value = subinventariosData[0].id;
            }
            if (!showSubinventario) {
                subinventarioSelect.value = '';
            }
        }
    }

    function hideStockValidationBanner() {
        if (stockValidationBanner) {
            stockValidationBanner.classList.add('hidden');
        }
    }

    function showStockValidationBanner(cantidad) {
        if (!stockValidationBanner || !stockValidationMessage) {
            return;
        }

        stockValidationMessage.textContent = `Stock actual: ${currentStock} unidades. Cantidad solicitada: ${cantidad} unidades. Faltante: ${cantidad - currentStock} unidades.`;
        stockValidationBanner.classList.remove('hidden');
        stockValidationBanner.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    
    // Función para actualizar el precio con descuento
    function updatePrecioConDescuento() {
        if (currentPrecio === null) {
            precioFinalTexto.textContent = '-';
            return;
        }
        
        const descuentoPorcentaje = parseFloat(descuentoInput.value) || 0;
        
        if (descuentoPorcentaje > 0) {
            if (descuentoPorcentaje > 100) {
                precioFinalTexto.innerHTML = `<span class="font-semibold text-red-600">⚠️ El descuento no puede ser mayor a 100%</span>`;
            } else {
                const montoDescuento = (currentPrecio * descuentoPorcentaje) / 100;
                const precioFinal = currentPrecio - montoDescuento;
                precioFinalTexto.innerHTML = `Precio final: <span class="font-semibold text-green-700">$${precioFinal.toFixed(2)}</span> <span class="text-gray-500">(${descuentoPorcentaje}% off = -$${montoDescuento.toFixed(2)})</span>`;
            }
        } else {
            precioFinalTexto.innerHTML = `Precio final: <span class="font-semibold text-gray-700">$${currentPrecio.toFixed(2)}</span>`;
        }
    }
    
    // Función para actualizar el stock resultante
    function updateStockResultante() {
        console.log('updateStockResultante', {currentStock, currentTipo, cantidad: cantidadInput.value});
        
        if (currentStock === null || !cantidadInput.value || !currentTipo) {
            stockResultanteTexto.textContent = '-';
            return;
        }
        
        const cantidad = parseInt(cantidadInput.value) || 0;
        let stockResultante = currentStock;
        let texto = '';
        
        if (currentTipo === 'entrada') {
            stockResultante = currentStock + cantidad;
            texto = `Después de guardar tendrá <span class="font-semibold text-green-700">${stockResultante} unidades</span>`;
        } else if (currentTipo === 'salida') {
            stockResultante = currentStock - cantidad;
            
            if (stockResultante < 0) {
                texto = `Después de guardar tendrá <span class="font-semibold text-red-600">${stockResultante} unidades</span> <span class="text-red-600">(insuficiente)</span>`;
            } else if (stockResultante < 10) {
                texto = `Después de guardar tendrá <span class="font-semibold text-amber-600">${stockResultante} unidades</span> <span class="text-amber-600">(stock bajo)</span>`;
            } else {
                texto = `Después de guardar tendrá <span class="font-semibold text-blue-700">${stockResultante} unidades</span>`;
            }
        }
        
        stockResultanteTexto.innerHTML = texto;
    }
    
    // Escuchar cambios en el libro seleccionado
    libroIdInput.addEventListener('change', function() {
        console.log('Libro cambiado:', this.value);
        refreshSelectedBookStock();
    });
    
    // Observar cambios en el input hidden para detectar selección de libro
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.attributeName === 'value') {
                console.log('MutationObserver detectó cambio');
                const event = new Event('change');
                libroIdInput.dispatchEvent(event);
            }
        });
    });
    
    observer.observe(libroIdInput, { attributes: true });

    // Mostrar/ocultar tipos según el movimiento
    tipoMovimiento.forEach(radio => {
        radio.addEventListener('change', function() {
            currentTipo = this.value;
            console.log('Tipo de movimiento cambiado:', currentTipo);
            
            if (this.value === 'entrada') {
                tipoEntradaContainer.classList.remove('hidden');
                tipoSalidaContainer.classList.add('hidden');
                document.getElementById('tipo_entrada').required = true;
                document.getElementById('tipo_salida').required = false;
                toggleSubinventarioSelector();
            } else {
                tipoSalidaContainer.classList.remove('hidden');
                tipoEntradaContainer.classList.add('hidden');
                document.getElementById('tipo_salida').required = true;
                document.getElementById('tipo_entrada').required = false;
                toggleSubinventarioSelector();
            }
            
            refreshSelectedBookStock();
        });
    });

    origenStockInputs.forEach(radio => {
        radio.addEventListener('change', function() {
            toggleSubinventarioSelector();
            actualizarBuscadorLibros();
            hideStockValidationBanner();
            refreshSelectedBookStock();
        });
    });

    if (subinventarioSelect) {
        subinventarioSelect.addEventListener('change', function() {
            actualizarBuscadorLibros();
            hideStockValidationBanner();
            refreshSelectedBookStock();
        });
    }
    
    // Actualizar stock resultante cuando cambie la cantidad
    cantidadInput.addEventListener('input', function() {
        console.log('Cantidad input:', this.value);
        hideStockValidationBanner();
        updateStockResultante();
    });
    
    // También actualizar al cambiar con las flechas o scroll
    cantidadInput.addEventListener('change', function() {
        console.log('Cantidad change:', this.value);
        hideStockValidationBanner();
        updateStockResultante();
    });

    // Actualizar precio con descuento cuando cambie el descuento
    descuentoInput.addEventListener('input', function() {
        console.log('Descuento input:', this.value);
        updatePrecioConDescuento();
    });
    
    descuentoInput.addEventListener('change', function() {
        console.log('Descuento change:', this.value);
        updatePrecioConDescuento();
    });

    toggleSubinventarioSelector();
    actualizarBuscadorLibros(true);

    // Inicializar el estado según old values
    const checkedRadio = document.querySelector('input[name="tipo_movimiento"]:checked');
    if (checkedRadio) {
        currentTipo = checkedRadio.value;
        console.log('Radio inicial:', currentTipo);
        checkedRadio.dispatchEvent(new Event('change'));
    }
    
    // Si hay un libro seleccionado al cargar, disparar el evento
    if (libroIdInput.value) {
        console.log('Libro inicial:', libroIdInput.value);
        libroIdInput.dispatchEvent(new Event('change'));
    }

    // Validación de stock al enviar
    document.getElementById('movimientoForm').addEventListener('submit', function(e) {
        const tipo = document.querySelector('input[name="tipo_movimiento"]:checked');
        const libroInput = document.getElementById('libro_id');
        
        if (tipo && tipo.value === 'salida' && libroInput.value && currentStock !== null) {
            const cantidad = parseInt(cantidadInput.value);
            
            if (cantidad > currentStock) {
                e.preventDefault();
                showStockValidationBanner(cantidad);
            }
        }
    });
});
</script>
@endpush
