@extends('layouts.app')

@section('title', 'Dashboard')

@section('page-title', 'Dashboard')
@section('page-description', 'Resumen general del sistema')

@section('content')
@php
    $isAdminLibreria = \App\Helpers\AuthHelper::isAdminLibreria();
@endphp

<x-page-layout 
    title="Panel de Control"
    description="Resumen general del inventario y movimientos"
>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <x-stat-card 
            icon="fas fa-book"
            label="Total Libros"
            :value="$totalLibros"
            bg-color="bg-gray-800"
            icon-color="text-white"
        />

        <x-stat-card 
            icon="fas fa-dollar-sign"
            label="Valor Inventario"
            :value="'$' . number_format($valorInventario, 2)"
            bg-color="bg-green-100"
            icon-color="text-green-600"
        />

        <x-stat-card 
            icon="fas fa-boxes"
            label="Stock Total"
            :value="$stockTotal"
            bg-color="bg-yellow-100"
            icon-color="text-yellow-600"
        />
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <x-card title="Acceso Rápido - Inventario">
            <div class="space-y-2">
                <a href="{{ route('inventario.index') }}" class="block p-4 bg-gray-50 hover:bg-gray-100 rounded-lg transition-colors">
                    <i class="fas fa-boxes text-gray-800 mr-2"></i>
                    Ver Inventario de Libros
                </a>
                @if(\App\Helpers\AuthHelper::isAdminLibreria())
                    <a href="{{ route('inventario.create') }}" class="block p-4 bg-gray-50 hover:bg-gray-100 rounded-lg transition-colors">
                        <i class="fas fa-plus text-green-600 mr-2"></i>
                        Agregar Nuevo Libro
                    </a>
                @else
                    <div class="block p-4 bg-gray-200 rounded-lg cursor-not-allowed opacity-60">
                        <i class="fas fa-plus text-gray-400 mr-2"></i>
                        Agregar Nuevo Libro
                    </div>
                @endif
            </div>
        </x-card>

        <x-card title="Acceso Rápido - Ventas y Movimientos">
            <div class="space-y-2">
                <a href="{{ route('ventas.index') }}" class="block p-4 bg-green-50 hover:bg-green-100 rounded-lg transition-colors">
                    <i class="fas fa-cash-register text-green-600 mr-2"></i>
                    Ver Ventas
                </a>
                @if(\App\Helpers\AuthHelper::isAdminLibreria())
                    <a href="{{ route('ventas.create') }}" class="block p-4 bg-green-50 hover:bg-green-100 rounded-lg transition-colors">
                        <i class="fas fa-plus-circle text-green-600 mr-2"></i>
                        Registrar Nueva Venta
                    </a>
                @else
                    <div class="block p-4 bg-gray-200 rounded-lg cursor-not-allowed opacity-60">
                        <i class="fas fa-plus-circle text-gray-400 mr-2"></i>
                        Registrar Nueva Venta
                    </div>
                @endif
                <a href="{{ route('movimientos.index') }}" class="block p-4 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors">
                    <i class="fas fa-exchange-alt text-blue-600 mr-2"></i>
                    Ver Movimientos
                </a>
                @if(\App\Helpers\AuthHelper::isAdminLibreria())
                    <a href="{{ route('movimientos.create') }}" class="block p-4 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors">
                        <i class="fas fa-plus text-blue-600 mr-2"></i>
                        Registrar Nuevo Movimiento
                    </a>
                @else
                    <div class="block p-4 bg-gray-200 rounded-lg cursor-not-allowed opacity-60">
                        <i class="fas fa-plus text-gray-400 mr-2"></i>
                        Registrar Nuevo Movimiento
                    </div>
                @endif
            </div>
        </x-card>

        <x-card title="Acceso Rápido - Apartados">
            <div class="space-y-2">
                <a href="{{ route('apartados.index') }}" class="block p-4 bg-purple-50 hover:bg-purple-100 rounded-lg transition-colors">
                    <i class="fas fa-bookmark text-purple-600 mr-2"></i>
                    Ver Apartados
                </a>
                @if(\App\Helpers\AuthHelper::isAdminLibreria())
                    <button onclick="openAbonoModal()" class="w-full text-left p-4 bg-orange-50 hover:bg-orange-100 rounded-lg transition-colors">
                        <i class="fas fa-dollar-sign text-orange-600 mr-2"></i>
                        Registrar Abono Rápido
                    </button>
                @else
                    <div class="w-full text-left p-4 bg-gray-200 rounded-lg cursor-not-allowed opacity-60">
                        <i class="fas fa-dollar-sign text-gray-400 mr-2"></i>
                        Registrar Abono Rápido
                    </div>
                @endif
            </div>
        </x-card>

        <x-card title="Información del Sistema">
            <div class="space-y-2 text-gray-600">
                <p><i class="fas fa-calendar mr-2"></i> Fecha: {{ date('d/m/Y') }}</p>
                <p><i class="fas fa-clock mr-2"></i> Hora: {{ date('H:i:s') }}</p>
                <p><i class="fas fa-user mr-2"></i> Usuario: Admin</p>
                <p><i class="fas fa-code-branch mr-2"></i> Versión: <span class="font-semibold text-primary-600">{{ trim(file_exists(base_path('version.txt')) ? file_get_contents(base_path('version.txt')) : 'v2.1.0') }}</span></p>
            </div>
        </x-card>
    </div>
</x-page-layout>

<!-- Modal para Buscar Apartado -->
<div id="abonoModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-lg bg-white">
        <div class="mt-3">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-gray-900">
                    <i class="fas fa-search text-orange-600 mr-2"></i>
                    Buscar Apartado para Abono
                </h3>
                <button onclick="closeAbonoModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <form id="searchApartadoForm" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-hashtag text-primary-600 mr-1"></i>
                        Buscar por Folio
                    </label>
                    <input type="text" 
                           id="searchFolio" 
                           class="w-full px-4 py-2.5 border-2 border-gray-300 rounded-lg focus:border-orange-500 focus:ring-2 focus:ring-orange-200 transition-colors" 
                           placeholder="Ej: AP-2024-0001">
                </div>

                <div class="text-center text-gray-500 text-sm">
                    <span>o</span>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-user text-primary-600 mr-1"></i>
                        Buscar por Cliente
                    </label>
                    <input type="text" 
                           id="searchCliente" 
                           class="w-full px-4 py-2.5 border-2 border-gray-300 rounded-lg focus:border-orange-500 focus:ring-2 focus:ring-orange-200 transition-colors" 
                           placeholder="Nombre del cliente"
                           autocomplete="off">
                    <div id="clienteSuggestions" class="mt-2 bg-white border border-gray-200 rounded-lg shadow-lg hidden max-h-60 overflow-y-auto"></div>
                </div>

                <div id="apartadosResults" class="hidden space-y-2 max-h-60 overflow-y-auto">
                    <!-- Aquí se mostrarán los apartados encontrados -->
                </div>

                <div class="flex gap-3 pt-4">
                    <button type="button" 
                            onclick="closeAbonoModal()" 
                            class="flex-1 px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition-colors">
                        <i class="fas fa-times mr-1"></i> Cancelar
                    </button>
                    <button type="button" 
                            onclick="searchApartado()" 
                            class="flex-1 px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors">
                        <i class="fas fa-search mr-1"></i> Buscar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const isAdminLibreria = {{ $isAdminLibreria ? 'true' : 'false' }};

function openAbonoModal() {
    if (!isAdminLibreria) {
        alert('No tienes permisos para realizar esta acción. Solo Admin Librería puede registrar abonos.');
        return;
    }
    document.getElementById('abonoModal').classList.remove('hidden');
    document.getElementById('searchFolio').focus();
}

function closeAbonoModal() {
    document.getElementById('abonoModal').classList.add('hidden');
    document.getElementById('searchFolio').value = '';
    document.getElementById('searchCliente').value = '';
    document.getElementById('apartadosResults').classList.add('hidden');
    document.getElementById('clienteSuggestions').classList.add('hidden');
}

async function searchApartado() {
    if (!isAdminLibreria) {
        alert('No tienes permisos para realizar esta acción.');
        return;
    }
    
    const folio = document.getElementById('searchFolio').value.trim();
    const cliente = document.getElementById('searchCliente').value.trim();

    if (!folio && !cliente) {
        alert('Por favor ingrese un folio o nombre de cliente');
        return;
    }

    try {
        const params = new URLSearchParams();
        if (folio) params.append('folio', folio);
        if (cliente) params.append('cliente', cliente);

        const response = await fetch(`/api/apartados/buscar?${params.toString()}`);
        const apartados = await response.json();

        displayApartados(apartados);
    } catch (error) {
        console.error('Error al buscar apartado:', error);
        alert('Error al buscar el apartado');
    }
}

function displayApartados(apartados) {
    const resultsDiv = document.getElementById('apartadosResults');
    
    if (apartados.length === 0) {
        resultsDiv.innerHTML = '<p class="text-center text-gray-500 py-4">No se encontraron apartados activos</p>';
        resultsDiv.classList.remove('hidden');
        return;
    }

    resultsDiv.innerHTML = apartados.map(apartado => `
        <div class="p-4 border-2 border-gray-200 rounded-lg hover:border-orange-400 transition-colors cursor-pointer"
             onclick="goToAbono(${apartado.id})">
            <div class="flex justify-between items-start">
                <div>
                    <p class="font-semibold text-gray-900">${apartado.folio}</p>
                    <p class="text-sm text-gray-600">${apartado.cliente.nombre}</p>
                    <p class="text-xs text-gray-500 mt-1">Saldo: $${parseFloat(apartado.saldo_pendiente).toFixed(2)}</p>
                </div>
                <span class="px-2 py-1 text-xs rounded-full ${apartado.estado === 'activo' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'}">
                    ${apartado.estado}
                </span>
            </div>
        </div>
    `).join('');
    
    resultsDiv.classList.remove('hidden');
}

function goToAbono(apartadoId) {
    if (!isAdminLibreria) {
        alert('No tienes permisos para realizar esta acción. Solo Admin Librería puede registrar abonos.');
        return;
    }
    window.location.href = `/apartados/${apartadoId}/abonos/crear`;
}

// Búsqueda en tiempo real para cliente
let searchTimeout;
document.getElementById('searchCliente')?.addEventListener('input', function(e) {
    clearTimeout(searchTimeout);
    const query = e.target.value.trim();
    
    if (query.length < 2) {
        document.getElementById('clienteSuggestions').classList.add('hidden');
        return;
    }

    searchTimeout = setTimeout(async () => {
        try {
            const response = await fetch(`/api/clientes/buscar?q=${encodeURIComponent(query)}`);
            const clientes = await response.json();
            
            const suggestionsDiv = document.getElementById('clienteSuggestions');
            
            if (clientes.length === 0) {
                suggestionsDiv.classList.add('hidden');
                return;
            }

            suggestionsDiv.innerHTML = clientes.map(cliente => `
                <div class="p-3 hover:bg-gray-50 cursor-pointer border-b last:border-b-0"
                     onclick="selectCliente('${cliente.nombre}')">
                    <p class="font-medium text-gray-900">${cliente.nombre}</p>
                    ${cliente.telefono ? `<p class="text-xs text-gray-500">${cliente.telefono}</p>` : ''}
                </div>
            `).join('');
            
            suggestionsDiv.classList.remove('hidden');
        } catch (error) {
            console.error('Error al buscar clientes:', error);
        }
    }, 300);
});

function selectCliente(nombre) {
    document.getElementById('searchCliente').value = nombre;
    document.getElementById('clienteSuggestions').classList.add('hidden');
}

// Cerrar modal con tecla Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeAbonoModal();
    }
});

// Buscar con Enter
document.getElementById('searchFolio')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        searchApartado();
    }
});
</script>
@endsection
