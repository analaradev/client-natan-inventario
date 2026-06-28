@extends('layouts.app')

@section('title', 'Inventario de Libros')

@section('page-title', 'Inventario de Libros')
@section('page-description', 'Gestión y control del inventario de libros')

@section('content')
@php
    $isAdminLibreria = \App\Helpers\AuthHelper::isAdminLibreria();
@endphp

<x-page-layout 
    title="Listado de Libros"
    description="Total: {{ $totalLibros }} libros"
>
    <x-slot name="header">
        <x-button 
            variant="info" 
            icon="fas fa-box-open"
            onclick="window.location='{{ route('subinventarios.index') }}'"
            class="mr-2"
        >
            Sub-Inventarios
        </x-button>
        @if($isAdminLibreria)
            <x-button 
                variant="success" 
                icon="fas fa-file-excel"
                onclick="window.location='{{ route('inventario.import') }}'"
                class="mr-2"
            >
                Importar Excel
            </x-button>
            <x-button 
                variant="primary" 
                icon="fas fa-plus"
                onclick="window.location='{{ route('inventario.create') }}'"
            >
                Registrar Libro
            </x-button>
        @else
            <button 
                disabled
                class="inline-flex items-center px-4 py-2 rounded-lg font-medium text-sm cursor-not-allowed bg-gray-200 text-gray-400 opacity-60"
            >
                <i class="fas fa-plus mr-2"></i>
                Registrar Libro
            </button>
        @endif
    </x-slot>

    <!-- Estadísticas rápidas -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <x-stat-card 
            icon="fas fa-book"
            label="Total Libros"
            :value="$totalLibros"
            bg-color="bg-gray-800"
            icon-color="text-white"
        />

        <x-stat-card 
            icon="fas fa-boxes"
            label="Stock Total"
            :value="$stockTotal"
            bg-color="bg-green-100"
            icon-color="text-green-600"
        />

        <x-stat-card 
            icon="fas fa-dollar-sign"
            label="Valor Total"
            :value="'$' . number_format($valorTotal, 2)"
            bg-color="bg-purple-100"
            icon-color="text-purple-600"
        />
    </div>

    <!-- Filtros y búsqueda -->
    <x-card class="overflow-visible">
        <form method="GET" action="{{ route('inventario.index') }}" class="overflow-visible">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-[1fr_auto_auto_auto] gap-4 mb-4 overflow-visible items-end">
                <!-- Búsqueda por nombre o código -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-search text-gray-400"></i> Búsqueda
                    </label>
                    <input 
                        type="text" 
                        name="search" 
                        value="{{ request('search') }}"
                        placeholder="Buscar por nombre o código..." 
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                    >
                </div>

                <!-- Filtro por stock -->
                <div class="w-full md:w-40">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-boxes text-gray-400"></i> Stock
                    </label>
                    <select name="stock_filter" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <option value="">Todos</option>
                        <option value="0-100" {{ request('stock_filter') == '0-100' ? 'selected' : '' }}>Menos de 100</option>
                        <option value="100-200" {{ request('stock_filter') == '100-200' ? 'selected' : '' }}>100 a 200</option>
                        <option value="200-300" {{ request('stock_filter') == '200-300' ? 'selected' : '' }}>200 a 300</option>
                        <option value="300-400" {{ request('stock_filter') == '300-400' ? 'selected' : '' }}>300 a 400</option>
                        <option value="400-up" {{ request('stock_filter') == '400-up' ? 'selected' : '' }}>400 o más</option>
                    </select>
                </div>

                <!-- Filtro por precio -->
                <div class="w-full md:w-40">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-dollar-sign text-gray-400"></i> Precio
                    </label>
                    <select name="precio_filter" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <option value="">Todos</option>
                        <option value="0-100" {{ request('precio_filter') == '0-100' ? 'selected' : '' }}>Menos de $100</option>
                        <option value="100-200" {{ request('precio_filter') == '100-200' ? 'selected' : '' }}>$100 a $200</option>
                        <option value="200-300" {{ request('precio_filter') == '200-300' ? 'selected' : '' }}>$200 a $300</option>
                        <option value="300-400" {{ request('precio_filter') == '300-400' ? 'selected' : '' }}>$300 a $400</option>
                        <option value="400-up" {{ request('precio_filter') == '400-up' ? 'selected' : '' }}>$400 o más</option>
                    </select>
                </div>

                <!-- Ordenar por -->
                <div class="w-full md:w-40">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-sort text-gray-400"></i> Ordenar
                    </label>
                    <select name="ordenar" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <option value="reciente" {{ request('ordenar', 'reciente') == 'reciente' ? 'selected' : '' }}>Más recientes</option>
                        <option value="nombre_asc" {{ request('ordenar') == 'nombre_asc' ? 'selected' : '' }}>Nombre (A-Z)</option>
                        <option value="nombre_desc" {{ request('ordenar') == 'nombre_desc' ? 'selected' : '' }}>Nombre (Z-A)</option>
                    </select>
                </div>
            </div>

            <!-- Botones de acción -->
            <div class="flex flex-wrap justify-between items-center gap-3 pt-2">
                <div class="flex gap-3">
                    <x-button type="submit" variant="primary" icon="fas fa-filter">
                        Aplicar Filtros
                    </x-button>

                    @if(request()->hasAny(['search', 'stock_filter', 'precio_filter', 'ordenar']))
                        <x-button type="button" variant="secondary" icon="fas fa-times" 
                                  onclick="window.location='{{ route('inventario.index') }}'">
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
                        onclick="window.location='{{ route('inventario.export.excel', request()->query()) }}'"
                    >
                        Exportar Excel
                    </x-button>
                    
                    <x-button 
                        type="button" 
                        variant="danger" 
                        icon="fas fa-file-pdf"
                        onclick="window.location='{{ route('inventario.export.pdf', request()->query()) }}'"
                    >
                        Exportar PDF
                    </x-button>
                </div>
            </div>
        </form>
    </x-card>

    <!-- Tabla de libros -->
    <x-card>
        <x-data-table 
            :headers="['ID', 'Nombre', 'Código de Barras', 'Precio', 'Stock']"
            :rows="$libros"
            emptyMessage="No hay libros registrados"
            emptyIcon="fas fa-book"
        >
            @foreach($libros as $libro)
                <x-data-table-row>
                    <x-data-table-cell bold>{{ $libro->id }}</x-data-table-cell>
                    <x-data-table-cell bold wrap class="max-w-md">{{ $libro->nombre }}</x-data-table-cell>
                    <x-data-table-cell>{{ $libro->codigo_barras }}</x-data-table-cell>
                    <x-data-table-cell>${{ number_format($libro->precio, 2) }}</x-data-table-cell>
                    <x-data-table-cell wrap>
                        <div class="max-w-[11rem] space-y-1">
                            <div class="inline-flex items-center gap-2 rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $libro->stock_total > 10 ? 'bg-green-100 text-green-800' : ($libro->stock_total > 0 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                                <span>Total:</span>
                                <span>{{ number_format($libro->stock_total) }}</span>
                            </div>
                            <div class="flex flex-wrap gap-1">
                                <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-700">
                                    Gral <strong class="text-gray-900">{{ number_format($libro->stock_general_disponible) }}</strong>
                                </span>
                                <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2 py-0.5 text-[11px] font-medium text-blue-700">
                                    Subs <strong class="text-blue-900">{{ number_format($libro->stock_subinventario) }}</strong>
                                </span>
                                <span class="inline-flex items-center gap-1 rounded-full bg-purple-50 px-2 py-0.5 text-[11px] font-medium text-purple-700">
                                    Aptos <strong class="text-purple-900">{{ number_format($libro->stock_apartado) }}</strong>
                                </span>
                            </div>
                        </div>
                    </x-data-table-cell>
                    <x-data-table-actions
                        :viewRoute="route('inventario.show', $libro->id)"
                        :editRoute="route('inventario.edit', $libro->id)"
                        :deleteRoute="route('inventario.destroy', $libro->id)"
                        deleteMessage="¿Estás seguro de eliminar este libro?"
                        :requireAdmin="true"
                    />
                </x-data-table-row>
            @endforeach
        </x-data-table>

        <!-- Paginación -->
        @if($libros->hasPages())
            <div class="mt-4 px-6 py-4 border-t border-gray-200">
                {{ $libros->appends(request()->query())->links() }}
            </div>
        @endif
    </x-card>
</x-page-layout>
@endsection
