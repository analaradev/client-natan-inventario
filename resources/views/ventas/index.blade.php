@extends('layouts.app')

@section('title', 'Ventas')

@section('page-title', 'Ventas')
@section('page-description', 'Gestión de ventas realizadas')

@section('content')
@php
    $isAdminLibreria = \App\Helpers\AuthHelper::isAdminLibreria();
    $canManageSalesOperations = \App\Helpers\AuthHelper::canManageSalesOperations();
@endphp

<x-page-layout 
    title="Listado de Ventas"
    description="Total: {{ $ventas->total() }} ventas"
>
    <x-slot name="header">
        @if($canManageSalesOperations)
            <x-button 
                variant="primary" 
                icon="fas fa-plus"
                onclick="window.location='{{ route('ventas.create') }}'"
            >
                Nueva Venta
            </x-button>
        @else
            <button 
                disabled
                class="inline-flex items-center px-4 py-2 rounded-lg font-medium text-sm cursor-not-allowed bg-gray-200 text-gray-400 opacity-60"
            >
                <i class="fas fa-plus mr-2"></i>
                Nueva Venta
            </button>
        @endif
    </x-slot>

    <!-- Estadísticas de ventas filtradas -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem;">
        <x-stat-card 
            icon="fas fa-shopping-cart"
            label="Total Ventas"
            :value="$estadisticas['total_ventas']"
            bg-color="bg-blue-100"
            icon-color="text-blue-600"
        />

        <x-stat-card 
            icon="fas fa-dollar-sign"
            label="Monto Total"
            :value="'$' . number_format($estadisticas['total_monto'], 2)"
            bg-color="bg-green-100"
            icon-color="text-green-600"
        />

        <x-stat-card 
            icon="fas fa-check-circle"
            label="Completadas"
            :value="$estadisticas['ventas_completadas']"
            bg-color="bg-green-100"
            icon-color="text-green-600"
        />

        @if($estadisticas['ventas_vencidas'] > 0)
        <x-stat-card 
            icon="fas fa-clock"
            label="Vencidas"
            :value="$estadisticas['ventas_vencidas']"
            bg-color="bg-red-100"
            icon-color="text-red-600"
        />
        @endif

        @if($estadisticas['ventas_canceladas'] > 0)
        <x-stat-card 
            icon="fas fa-ban"
            label="Canceladas"
            :value="$estadisticas['ventas_canceladas']"
            bg-color="bg-gray-100"
            icon-color="text-gray-600"
        />
        @endif
    </div>

    <!-- Filtros -->
    <x-card class="overflow-visible">
        <form method="GET" action="{{ route('ventas.index') }}" class="overflow-visible">
            <!-- Filters Grid -->
            <div class="grid grid-cols-4 gap-4 mb-4 overflow-visible">
                <!-- Filtro por Cliente - Ocupa toda la primera fila -->
                <div class="col-span-4">
                    @php
                        $selectedClienteId = request('cliente_id');
                        $selectedCliente = $selectedClienteId ? \App\Models\Cliente::find($selectedClienteId) : null;
                    @endphp
                    <x-cliente-search-dynamic 
                        name="cliente_id"
                        :selected="$selectedClienteId"
                        :clienteData="$selectedCliente"
                        label="Cliente"
                        :required="false"
                        placeholder="Buscar o seleccionar cliente..."
                    />
                </div>

                <!-- Filtro por Libro - Ocupa toda la segunda fila -->
                <div class="col-span-4">
                    @php
                        $selectedLibroId = request('libro_id');
                    @endphp
                    <x-libro-search-dynamic 
                        name="libro_id"
                        :selected="$selectedLibroId"
                        :libros="$libros"
                        label="Libro"
                        :required="false"
                    />
                </div>

                <!-- Filtro por Tipo de Inventario / Sub-Inventario - PRIMERA FILA DE FILTROS -->
                <div class="col-span-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-warehouse text-gray-400"></i> Origen de Venta (Inventario / Sub-Inventario)
                    </label>
                    <select name="subinventario_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <option value="">Todos los orígenes</option>
                        <option value="general" {{ request('subinventario_id') === 'general' ? 'selected' : '' }}>
                            Inventario General
                        </option>
                        @if($subinventarios->count() > 0)
                            <optgroup label="Sub-Inventarios Disponibles">
                                @foreach($subinventarios as $sub)
                                    <option value="{{ $sub->id }}" {{ request('subinventario_id') == $sub->id ? 'selected' : '' }}>
                                        Sub-Inventario #{{ $sub->id }} - {{ $sub->descripcion ?: 'Sin descripción' }}
                                    </option>
                                @endforeach
                            </optgroup>
                        @endif
                    </select>
                </div>

                <!-- Filtro por Estado -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-info-circle text-gray-400"></i> Estado
                    </label>
                    <select name="estado" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <option value="">Todos los estados</option>
                        <option value="completada" {{ request('estado') === 'completada' ? 'selected' : '' }}>Completada</option>
                        <option value="pendiente" {{ request('estado') === 'pendiente' ? 'selected' : '' }}>Pendiente</option>
                        <option value="cancelada" {{ request('estado') === 'cancelada' ? 'selected' : '' }}>Cancelada</option>
                    </select>
                </div>

                <!-- Filtro por Estado de Pago -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-money-check-alt text-gray-400"></i> Estado de Pago
                    </label>
                    <select name="estado_pago" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <option value="">Todos</option>
                        <option value="pendiente" {{ request('estado_pago') === 'pendiente' ? 'selected' : '' }}>Pendiente</option>
                        <option value="parcial" {{ request('estado_pago') === 'parcial' ? 'selected' : '' }}>Pago Parcial</option>
                        <option value="completado" {{ request('estado_pago') === 'completado' ? 'selected' : '' }}>Completado</option>
                    </select>
                </div>

                <!-- Filtro por Apartados -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-box-open text-gray-400"></i> Tipo de Venta
                    </label>
                    <select name="es_apartado" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <option value="">Todas</option>
                        <option value="1" {{ request('es_apartado') === '1' ? 'selected' : '' }}>Solo Apartados</option>
                        <option value="0" {{ request('es_apartado') === '0' ? 'selected' : '' }}>Solo Ventas Directas</option>
                    </select>
                </div>

                <!-- Filtro Ventas Vencidas -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-exclamation-triangle text-gray-400"></i> Ventas Vencidas
                    </label>
                    <select name="vencidas" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <option value="">Todas</option>
                        <option value="1" {{ request('vencidas') === '1' ? 'selected' : '' }}>Solo vencidas</option>
                    </select>
                </div>

                <!-- Filtro Fecha Desde -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-calendar-alt text-gray-400"></i> Fecha Desde
                    </label>
                    <input type="date" 
                           name="fecha_desde" 
                           value="{{ request('fecha_desde') }}" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                </div>

                <!-- Filtro Fecha Hasta -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-calendar-check text-gray-400"></i> Fecha Hasta
                    </label>
                    <input type="date" 
                           name="fecha_hasta" 
                           value="{{ request('fecha_hasta') }}" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                </div>
            </div>

            <!-- Botones de acción -->
            <div class="flex flex-wrap justify-between items-center gap-3 pt-2">
                <div class="flex gap-3">
                    <x-button type="submit" variant="primary" icon="fas fa-filter">
                        Aplicar Filtros
                    </x-button>

                    @if(request()->hasAny(['cliente_id', 'estado', 'tipo_pago', 'estado_pago', 'es_apartado', 'libro_id', 'fecha_desde', 'fecha_hasta', 'vencidas', 'tipo_inventario', 'subinventario_id']))
                        <x-button type="button" variant="secondary" icon="fas fa-times" 
                                  onclick="window.location='{{ route('ventas.index') }}'">
                            Limpiar Filtros
                        </x-button>
                    @endif
                </div>

                <!-- Botones de exportación -->
                <div class="flex gap-3">
                    <x-button 
                        type="button" 
                        variant="success" 
                        icon="fas fa-file-excel"
                        onclick="exportarExcel()"
                    >
                        Exportar Excel
                    </x-button>
                    
                    <x-button 
                        type="button" 
                        variant="danger" 
                        icon="fas fa-file-pdf"
                        onclick="exportarPdf()"
                    >
                        Exportar PDF
                    </x-button>
                </div>
            </div>
        </form>
    </x-card>

    <!-- Tabla de ventas -->
    <x-card>
        <x-data-table 
            :headers="['ID', 'Fecha', 'Cliente', 'Total', 'Usuario', 'Origen', 'Estado', 'Acciones']"
            :rows="$ventas"
            emptyMessage="No se encontraron ventas"
            emptyIcon="fas fa-shopping-cart"
            :showActions="false"
        >
            @foreach($ventas as $venta)
                <x-data-table-row>
                    <!-- ID -->
                    <x-data-table-cell bold>
                        <div class="flex items-center gap-1">
                            #{{ $venta->id }}
                            @if($venta->tiene_envio)
                                <span class="text-xs px-1.5 py-0.5 bg-blue-100 text-blue-700 rounded" title="Tiene envío asignado">
                                    <i class="fas fa-shipping-fast"></i>
                                </span>
                            @endif
                            @if($venta->esApartado())
                                <span class="text-xs px-1.5 py-0.5 bg-purple-100 text-purple-700 rounded" title="Venta generada desde apartado">
                                    <i class="fas fa-box-open"></i>
                                </span>
                            @endif
                        </div>
                    </x-data-table-cell>

                    <!-- Fecha -->
                    <x-data-table-cell>
                        <div class="text-sm">
                            <div class="font-medium text-gray-900">
                                {{ $venta->fecha_venta->format('d/m/Y') }}
                            </div>
                        </div>
                    </x-data-table-cell>

                    <!-- Cliente -->
                    <x-data-table-cell>
                        <div class="text-sm">
                            <div class="font-medium text-gray-900">
                                {{ $venta->cliente_referencia ?: ($venta->cliente?->nombre ?: 'Sin cliente') }}
                            </div>
                            @if($venta->cliente_referencia && $venta->cliente)
                                <div class="text-gray-500 text-xs">
                                    <i class="fas fa-user mr-1"></i>{{ $venta->cliente->nombre }}
                                </div>
                            @endif
                            @if($venta->cliente?->telefono)
                                <div class="text-gray-500 text-xs">
                                    <i class="fas fa-phone mr-1"></i>{{ $venta->cliente->telefono }}
                                </div>
                            @endif
                            <div class="text-xs text-gray-500 mt-0.5">
                                <i class="fas fa-book mr-1"></i>{{ $venta->movimientos->count() }} libro(s) | 
                                <span class="inline-flex items-center text-xs
                                    {{ $venta->tipo_pago === 'contado' ? 'text-green-600' : '' }}
                                    {{ $venta->tipo_pago === 'credito' ? 'text-orange-600' : '' }}
                                    {{ $venta->tipo_pago === 'mixto' ? 'text-blue-600' : '' }}">
                                    <i class="fas fa-credit-card mr-1"></i>{{ $venta->getTipoPagoLabel() }}
                                </span>
                            </div>
                        </div>
                    </x-data-table-cell>

                    <!-- Total -->
                    <x-data-table-cell>
                        <div class="text-sm">
                            <div class="font-bold text-gray-900 text-base">
                                ${{ number_format($venta->total, 2) }}
                            </div>
                            @if($venta->descuento_global > 0)
                                <div class="text-xs text-yellow-600">
                                    <i class="fas fa-tag mr-1"></i>{{ $venta->descuento_global }}% desc.
                                </div>
                            @endif
                        </div>
                    </x-data-table-cell>

                    <!-- Usuario -->
                    <x-data-table-cell>
                        <div class="text-sm">
                            <div class="font-medium text-gray-700">
                                <i class="fas fa-user text-gray-400 mr-1"></i>{{ $venta->usuario ?: 'Sistema' }}
                            </div>
                        </div>
                    </x-data-table-cell>

                    <!-- Origen -->
                    <x-data-table-cell>
                        <div class="text-sm">
                            @if($venta->tipo_inventario === 'subinventario' && $venta->subinventario)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800">
                                    <i class="fas fa-boxes mr-1"></i>#{{ $venta->subinventario->id }}
                                </span>
                                <div class="text-xs text-gray-600 mt-1">
                                    {{ $venta->subinventario->descripcion ?: 'Sin descripción' }}
                                </div>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    <i class="fas fa-warehouse mr-1"></i>General
                                </span>
                            @endif
                        </div>
                    </x-data-table-cell>

                    <!-- Estado -->
                    <x-data-table-cell>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $venta->getEstadoUnificadoBadgeColor() }}">
                            <i class="{{ $venta->getEstadoUnificadoIcon() }} mr-1"></i>
                            {{ $venta->getEstadoUnificadoLabel() }}
                        </span>
                    </x-data-table-cell>

                    <!-- Acciones -->
                    <x-data-table-cell>
                        <div class="flex justify-end gap-1">
                            <x-button 
                                href="{{ route('ventas.show', $venta) }}" 
                                variant="primary" 
                                size="sm"
                                icon="fas fa-eye"
                                title="Ver detalles">
                            </x-button>
                            
                            
                            @if($isAdminLibreria)
                                <x-button 
                                    href="{{ route('ventas.edit', $venta) }}" 
                                    variant="warning" 
                                    size="sm"
                                    icon="fas fa-edit"
                                    title="Editar venta">
                                </x-button>
                            @else
                                <button 
                                    disabled
                                    class="inline-flex items-center justify-center px-2 py-1 text-xs font-medium rounded cursor-not-allowed bg-gray-200 text-gray-400 opacity-60"
                                    title="Solo Admin Librería">
                                    <i class="fas fa-edit"></i>
                                </button>
                            @endif
                            
                            @if($venta->estado === 'completada')
                                @if($isAdminLibreria)
                                    <form action="{{ route('ventas.cancelar', $venta) }}" method="POST" class="inline">
                                        @csrf
                                        <x-button 
                                            type="submit" 
                                            variant="warning" 
                                            size="sm"
                                            icon="fas fa-ban"
                                            title="Cancelar venta"
                                            onclick="return confirm('¿Estás seguro de cancelar esta venta? Se restaurará el stock.')">
                                        </x-button>
                                    </form>
                                @else
                                    <button 
                                        disabled
                                        class="inline-flex items-center justify-center px-2 py-1 text-xs font-medium rounded cursor-not-allowed bg-gray-200 text-gray-400 opacity-60"
                                        title="Solo Admin Librería">
                                        <i class="fas fa-ban"></i>
                                    </button>
                                @endif
                            @endif
                        </div>
                    </x-data-table-cell>
                </x-data-table-row>
            @endforeach
        </x-data-table>

        <!-- Paginación -->
        @if($ventas->hasPages())
            <div class="mt-4 px-6 py-4 border-t border-gray-200">
                {{ $ventas->appends(request()->query())->links() }}
            </div>
        @endif
    </x-card>
</x-page-layout>
@endsection

@push('scripts')
<script src="{{ asset('js/cliente-search-dynamic.js') }}"></script>
<script src="{{ asset('js/libro-search-dynamic.js') }}"></script>

<script>
/**
 * Exportar a Excel
 */
function exportarExcel() {
    const queryString = window.location.search;
    window.location.href = '{{ route("ventas.export.excel") }}' + queryString;
}

/**
 * Exportar a PDF con validación
 */
function exportarPdf() {
    const queryString = window.location.search;
    const url = '{{ route("ventas.export.pdf") }}' + queryString;
    
    // Mostrar loading
    const button = event.target.closest('button');
    const originalText = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generando PDF...';
    
    // Hacer petición AJAX para validar y generar
    fetch(url, {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(response => {
        // Verificar si es una respuesta JSON (error) o blob (PDF exitoso)
        const contentType = response.headers.get('content-type');
        
        if (response.status === 422 || response.status === 500) {
            // Error - parsear JSON
            return response.json().then(data => {
                alert('⚠️ ' + data.message);
                button.disabled = false;
                button.innerHTML = originalText;
            });
        }
        
        if (!response.ok) {
            alert('Error al generar el PDF. Intenta de nuevo.');
            button.disabled = false;
            button.innerHTML = originalText;
            return;
        }
        
        // Éxito - descargar PDF
        return response.blob().then(blob => {
            const downloadUrl = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = downloadUrl;
            a.download = 'reporte_ventas_detallado.pdf';
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(downloadUrl);
            document.body.removeChild(a);
            
            button.disabled = false;
            button.innerHTML = originalText;
        });
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al generar el PDF. Revisa la consola para más detalles.');
        button.disabled = false;
        button.innerHTML = originalText;
    });
}

// Auto-submit del formulario cuando se cambian los filtros
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form[action*="ventas"]');
    if (!form) return;
    
    // Agregar listeners a todos los select e inputs (EXCEPTO los de fecha)
    const filterElements = form.querySelectorAll('select, input[type="text"], input[type="number"]');
    filterElements.forEach(element => {
        // Ignorar el input de cliente_id ya que tiene su propio manejo
        if (element.classList.contains('cliente-search-input') || element.classList.contains('libro-search-input')) {
            return;
        }
        
        element.addEventListener('change', function() {
            console.log('[Filtros] Cambio detectado en:', element.name);
            // Auto-submit después de 500ms para que se registre el cambio
            setTimeout(() => {
                form.submit();
            }, 500);
        });
    });
    
    // También aplicar a los inputs del cliente search si existen
    const clienteInputs = form.querySelectorAll('.cliente-id-input');
    clienteInputs.forEach(input => {
        // Crear observer para cambios
        const originalValue = input.value;
        setInterval(() => {
            if (input.value !== originalValue && input.value !== '') {
                console.log('[Filtros] Cambio en cliente detectado');
                setTimeout(() => {
                    form.submit();
                }, 300);
            }
        }, 100);
    });
});
</script>
@endpush
