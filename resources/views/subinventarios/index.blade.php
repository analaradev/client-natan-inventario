@extends('layouts.app')

@section('title', 'Sub-Inventarios')

@section('content')
@php
    $isAdminLibreria = \App\Helpers\AuthHelper::isAdminLibreria();
@endphp

<x-page-layout 
    title="Sub-Inventarios"
    description="Gestiona los sub-inventarios para días de venta"
>
    <x-slot name="header">
        @if($isAdminLibreria)
            <x-button 
                variant="primary" 
                icon="fas fa-plus"
                onclick="window.location='{{ route('subinventarios.create') }}'"
            >
                Nuevo Sub-Inventario
            </x-button>
        @else
            <button 
                disabled
                class="inline-flex items-center px-4 py-2 rounded-lg font-medium text-sm cursor-not-allowed bg-gray-200 text-gray-400 opacity-60"
            >
                <i class="fas fa-plus mr-2"></i>
                Nuevo Sub-Inventario
            </button>
        @endif
    </x-slot>
    <!-- Filtros y búsqueda -->
    <x-card class="overflow-visible">
        <form method="GET" action="{{ route('subinventarios.index') }}" class="overflow-visible">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-[1fr_auto_auto_auto] gap-4 mb-4 overflow-visible items-end">
                <!-- Búsqueda por descripción -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-search text-gray-400"></i> Búsqueda
                    </label>
                    <input 
                        type="text" 
                        name="search" 
                        value="{{ request('search') }}"
                        placeholder="Buscar por descripción..." 
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                    >
                </div>

                <!-- Filtro por estado -->
                <div class="w-full md:w-40">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-list-ul text-gray-400"></i> Estado
                    </label>
                    <select name="estado" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <option value="">Todos</option>
                        <option value="activo" {{ request('estado') == 'activo' ? 'selected' : '' }}>Activo</option>
                        <option value="completado" {{ request('estado') == 'completado' ? 'selected' : '' }}>Completado</option>
                        <option value="cancelado" {{ request('estado') == 'cancelado' ? 'selected' : '' }}>Cancelado</option>
                    </select>
                </div>

                <!-- Filtro por fecha -->
                <div class="w-full md:w-48">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-calendar text-gray-400"></i> Fecha
                    </label>
                    <input 
                        type="date" 
                        name="fecha" 
                        value="{{ request('fecha') }}"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                    >
                </div>

                <!-- Ordenar por -->
                <div class="w-full md:w-48">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-sort text-gray-400"></i> Ordenar
                    </label>
                    <select name="ordenar" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <option value="reciente" {{ request('ordenar', 'reciente') == 'reciente' ? 'selected' : '' }}>Más reciente</option>
                        <option value="antiguo" {{ request('ordenar') == 'antiguo' ? 'selected' : '' }}>Más antiguo</option>
                        <option value="fecha_asc" {{ request('ordenar') == 'fecha_asc' ? 'selected' : '' }}>Fecha sub-inventario (asc)</option>
                        <option value="fecha_desc" {{ request('ordenar') == 'fecha_desc' ? 'selected' : '' }}>Fecha sub-inventario (desc)</option>
                    </select>
                </div>
            </div>

            <!-- Botones de acción -->
            <div class="flex flex-wrap justify-between gap-3 pt-2">
                <div class="flex gap-3">
                    <x-button type="submit" variant="primary" icon="fas fa-filter">
                        Aplicar Filtros
                    </x-button>

                    @if(request()->hasAny(['search', 'estado', 'fecha', 'ordenar']))
                        <x-button type="button" variant="secondary" icon="fas fa-times" 
                                  onclick="window.location='{{ route('subinventarios.index') }}'">
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
                        onclick="window.location='{{ route('subinventarios.export.excel', request()->query()) }}'"
                    >
                        Exportar Excel
                    </x-button>
                    
                    <x-button 
                        type="button" 
                        variant="danger" 
                        icon="fas fa-file-pdf"
                        onclick="window.location='{{ route('subinventarios.export.pdf', request()->query()) }}'"
                    >
                        Exportar PDF
                    </x-button>
                </div>
            </div>
        </form>
    </x-card>

    <!-- Lista de sub-inventarios -->
    <x-card>
        @if($subinventarios->count() > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha Sub-Inventario</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Descripción</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Libros</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Unidades</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($subinventarios as $subinventario)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    #{{ $subinventario->id }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $subinventario->fecha_subinventario->format('d/m/Y') }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    {{ $subinventario->descripcion ?: 'Sin descripción' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $subinventario->getTotalLibros() }} libros
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $subinventario->getTotalUnidades() }} unidades
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-3 py-1 inline-flex items-center text-xs leading-5 font-semibold rounded-full {{ $subinventario->getBadgeColor() }}">
                                        <i class="{{ $subinventario->getIcon() }} mr-1"></i>
                                        {{ $subinventario->getEstadoLabel() }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                    <a href="{{ route('subinventarios.show', $subinventario) }}" 
                                       class="text-blue-600 hover:text-blue-900"
                                       title="Ver detalles">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    
                                    @if($subinventario->estado === 'activo')
                                        @if($isAdminLibreria)
                                            <a href="{{ route('subinventarios.edit', $subinventario) }}" 
                                               class="text-yellow-600 hover:text-yellow-900"
                                               title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </a>

                                        @else
                                            <button disabled class="text-gray-400 cursor-not-allowed opacity-60" title="Solo Admin Librería">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        @endif
                                    @endif

                                    @if($subinventario->estado !== 'completado')
                                        @if($isAdminLibreria)
                                            <form action="{{ route('subinventarios.destroy', $subinventario) }}" 
                                                  method="POST" 
                                                  class="inline"
                                                  onsubmit="return confirm('¿Eliminar este sub-inventario?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" 
                                                        class="text-red-600 hover:text-red-900"
                                                        title="Eliminar">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        @else
                                            <button disabled class="text-gray-400 cursor-not-allowed opacity-60" title="Solo Admin Librería">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div class="mt-4">
                {{ $subinventarios->links() }}
            </div>
        @else
            <div class="text-center py-8">
                <i class="fas fa-box-open text-gray-400 text-5xl mb-4"></i>
                <p class="text-gray-500 text-lg">No hay sub-inventarios registrados</p>
                @if($isAdminLibreria)
                    <a href="{{ route('subinventarios.create') }}" 
                       class="mt-4 inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        <i class="fas fa-plus mr-2"></i>Crear primer sub-inventario
                    </a>
                @else
                    <button 
                        disabled
                        class="mt-4 inline-flex items-center px-4 py-2 rounded-lg cursor-not-allowed bg-gray-200 text-gray-400 opacity-60">
                        <i class="fas fa-plus mr-2"></i>Crear primer sub-inventario
                    </button>
                @endif
            </div>
        @endif
    </x-card>
</x-page-layout>
@endsection
