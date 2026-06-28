@extends('layouts.app')

@section('title', 'Clientes')

@section('page-title', 'Clientes')
@section('page-description', 'Gestión de clientes')

@section('content')
@php
    $isAdminLibreria = \App\Helpers\AuthHelper::isAdminLibreria();
    $canManageSalesOperations = \App\Helpers\AuthHelper::canManageSalesOperations();
@endphp

<x-page-layout 
    title="Listado de Clientes"
    description="Total: {{ $totalClientes }} clientes"
>
    <x-slot name="header">
        @if($canManageSalesOperations)
            <x-button 
                variant="primary" 
                icon="fas fa-plus"
                onclick="window.location='{{ route('clientes.create') }}'"
            >
                Registrar Cliente
            </x-button>
        @else
            <button 
                disabled
                class="inline-flex items-center px-4 py-2 rounded-lg font-medium text-sm cursor-not-allowed bg-gray-200 text-gray-400 opacity-60"
            >
                <i class="fas fa-plus mr-2"></i>
                Registrar Cliente
            </button>
        @endif
    </x-slot>

    <!-- Estadística rápida -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <x-stat-card 
            icon="fas fa-users"
            label="Total Clientes"
            :value="$totalClientes"
            bg-color="bg-gray-800"
            icon-color="text-white"
        />

        <x-stat-card 
            icon="fas fa-shopping-cart"
            label="Con Ventas"
            :value="$clientes->where('ventas_count', '>', 0)->count()"
            bg-color="bg-blue-100"
            icon-color="text-blue-600"
        />

        <x-stat-card 
            icon="fas fa-user-plus"
            label="Sin Ventas"
            :value="$clientes->where('ventas_count', 0)->count()"
            bg-color="bg-purple-100"
            icon-color="text-purple-600"
        />
    </div>

    <!-- Filtros y búsqueda -->
    <x-card class="overflow-visible">
        <form method="GET" action="{{ route('clientes.index') }}" class="overflow-visible">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4 overflow-visible items-end">
                <!-- Búsqueda -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-search text-gray-400"></i> Búsqueda
                    </label>
                    <input 
                        type="text" 
                        name="search" 
                        value="{{ request('search') }}"
                        placeholder="Buscar por nombre o teléfono..." 
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                    >
                </div>

                <!-- Ordenar por -->
                <div class="w-full md:w-48">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-sort text-gray-400"></i> Ordenar
                    </label>
                    <select name="ordenar" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <option value="reciente" {{ request('ordenar', 'reciente') == 'reciente' ? 'selected' : '' }}>Más recientes</option>
                        <option value="nombre_asc" {{ request('ordenar') == 'nombre_asc' ? 'selected' : '' }}>Nombre (A-Z)</option>
                        <option value="nombre_desc" {{ request('ordenar') == 'nombre_desc' ? 'selected' : '' }}>Nombre (Z-A)</option>
                        <option value="mas_ventas" {{ request('ordenar') == 'mas_ventas' ? 'selected' : '' }}>Más ventas</option>
                    </select>
                </div>
            </div>

            <!-- Botones de acción -->
            <div class="flex flex-wrap gap-3">
                <x-button type="submit" variant="primary" icon="fas fa-filter">
                    Aplicar Filtros
                </x-button>

                @if(request()->hasAny(['search', 'ordenar']))
                    <x-button type="button" variant="secondary" icon="fas fa-times" 
                              onclick="window.location='{{ route('clientes.index') }}'">
                        Limpiar Filtros
                    </x-button>
                @endif
            </div>
        </form>
    </x-card>

    <!-- Tabla de clientes -->
    <x-card>
        <x-data-table 
            :headers="['ID', 'Nombre', 'Teléfono', 'Ventas', 'Fecha Registro']"
            :rows="$clientes"
            emptyMessage="No hay clientes registrados"
            emptyIcon="fas fa-users"
        >
            @foreach($clientes as $cliente)
                <x-data-table-row>
                    <x-data-table-cell bold>{{ $cliente->id }}</x-data-table-cell>
                    <x-data-table-cell bold>{{ $cliente->nombre }}</x-data-table-cell>
                    <x-data-table-cell>
                        @if($cliente->telefono)
                            <span class="text-gray-700">
                                <i class="fas fa-phone text-gray-400 mr-1"></i>
                                {{ $cliente->telefono }}
                            </span>
                        @else
                            <span class="text-gray-400 italic text-sm">Sin teléfono</span>
                        @endif
                    </x-data-table-cell>
                    <x-data-table-cell>
                        <x-badge :type="$cliente->ventas_count > 0 ? 'success' : 'secondary'">
                            {{ $cliente->ventas_count }} {{ $cliente->ventas_count == 1 ? 'venta' : 'ventas' }}
                        </x-badge>
                    </x-data-table-cell>
                    <x-data-table-cell>{{ $cliente->created_at->format('d/m/Y') }}</x-data-table-cell>
                    <x-data-table-actions
                        :viewRoute="route('clientes.show', $cliente->id)"
                        :editRoute="route('clientes.edit', $cliente->id)"
                        :deleteRoute="route('clientes.destroy', $cliente->id)"
                        deleteMessage="¿Estás seguro de eliminar este cliente?"
                        :requireAdmin="true"
                    />
                </x-data-table-row>
            @endforeach
        </x-data-table>

        <!-- Paginación -->
        @if($clientes->hasPages())
            <div class="mt-4 px-6 py-4 border-t border-gray-200">
                {{ $clientes->appends(request()->query())->links() }}
            </div>
        @endif
    </x-card>
</x-page-layout>
@endsection
