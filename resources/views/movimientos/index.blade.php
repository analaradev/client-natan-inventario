@extends('layouts.app')

@section('title', 'Movimientos de Inventario')

@section('page-title', 'Movimientos de Inventario')
@section('page-description', 'Historial completo de entradas y salidas')

@section('content')
@php
    $isAdminLibreria = \App\Helpers\AuthHelper::isAdminLibreria();
@endphp

<x-page-layout 
    title="Historial de Movimientos"
    description="Total: {{ $totalMovimientos }} movimientos"
>
    <x-slot name="header">
        @if($isAdminLibreria)
            <div class="flex flex-wrap gap-3">
                <x-button
                    variant="secondary"
                    icon="fas fa-layer-group"
                    onclick="window.location='{{ route('movimientos.masivo.create') }}'"
                >
                    Ajuste Masivo
                </x-button>
                <x-button
                    variant="primary"
                    icon="fas fa-plus"
                    onclick="window.location='{{ route('movimientos.create') }}'"
                >
                    Registrar Movimiento
                </x-button>
            </div>
        @else
            <button 
                disabled
                class="inline-flex items-center px-4 py-2 rounded-lg font-medium text-sm cursor-not-allowed bg-gray-200 text-gray-400 opacity-60"
            >
                <i class="fas fa-plus mr-2"></i>
                Registrar Movimiento
            </button>
        @endif
    </x-slot>
    <!-- Estadísticas rápidas -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <x-stat-card 
            icon="fas fa-arrow-up"
            label="Total Entradas"
            :value="$totalEntradas"
            bg-color="bg-green-100"
            icon-color="text-green-600"
        />

        <x-stat-card 
            icon="fas fa-arrow-down"
            label="Total Salidas"
            :value="$totalSalidas"
            bg-color="bg-red-100"
            icon-color="text-red-600"
        />

        <x-stat-card 
            icon="fas fa-exchange-alt"
            label="Movimientos"
            :value="$totalMovimientos"
            bg-color="bg-blue-100"
            icon-color="text-blue-600"
        />
    </div>

    <!-- Filtros -->
    <x-card class="overflow-visible">
        <form method="GET" action="{{ route('movimientos.index') }}" class="overflow-visible">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-[1fr_auto_auto_auto] gap-4 mb-4 overflow-visible items-end">
                <!-- Filtro por Libro -->
                <div>
                    <x-libro-search-filter 
                        name="libro_id" 
                        :libros="$libros"
                        :selected="request('libro_id')"
                        label="Libro"
                    />
                </div>

                <!-- Filtro por Tipo de Movimiento Unificado -->
                <div class="w-full md:w-48">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-exchange-alt text-gray-400"></i> Tipo de Movimiento
                    </label>
                    <select name="tipo_movimiento" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <option value="">Todos los movimientos</option>
                        <option value="entrada" {{ request('tipo_movimiento') == 'entrada' && !str_starts_with(request('tipo_movimiento'), 'entrada_') ? 'selected' : '' }}>
                            ↑ Entrada (general)
                        </option>
                        @foreach(\App\Models\Movimiento::tiposEntrada() as $key => $label)
                            <option value="entrada_{{ $key }}" {{ request('tipo_movimiento') == 'entrada_'.$key ? 'selected' : '' }}>
                                ↑ {{ $label }}
                            </option>
                        @endforeach
                        <option value="salida" {{ request('tipo_movimiento') == 'salida' && !str_starts_with(request('tipo_movimiento'), 'salida_') ? 'selected' : '' }}>
                            ↓ Salida (general)
                        </option>
                        @foreach(\App\Models\Movimiento::tiposSalida() as $key => $label)
                            <option value="salida_{{ $key }}" {{ request('tipo_movimiento') == 'salida_'.$key ? 'selected' : '' }}>
                                ↓ {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Filtro Fecha Desde -->
                <div class="w-full md:w-40">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-calendar-alt text-gray-400"></i> Fecha Desde
                    </label>
                    <input type="date" 
                           name="fecha_desde" 
                           value="{{ request('fecha_desde') }}" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                </div>

                <!-- Filtro Fecha Hasta -->
                <div class="w-full md:w-40">
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

                    @if(request()->hasAny(['libro_id', 'tipo_movimiento', 'tipo_especifico', 'fecha_desde', 'fecha_hasta']))
                        <x-button type="button" variant="secondary" icon="fas fa-times" 
                                  onclick="window.location='{{ route('movimientos.index') }}'">
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
                        onclick="window.location='{{ route('movimientos.export.excel', request()->query()) }}'"
                    >
                        Exportar Excel
                    </x-button>
                    
                    <x-button 
                        type="button" 
                        variant="danger" 
                        icon="fas fa-file-pdf"
                        onclick="window.location='{{ route('movimientos.export.pdf', request()->query()) }}'"
                    >
                        Exportar PDF
                    </x-button>
                </div>
            </div>
        </form>
    </x-card>

    <!-- Tabla de movimientos -->
    <x-card>
        <x-table 
            :headers="['Fecha', 'Libro', 'Tipo', 'Movimiento', 'Origen', 'Cantidad', 'Usuario', 'Acciones']"
            :items="$movimientos"
            emptyMessage="No hay movimientos registrados"
            emptyIcon="fas fa-exchange-alt"
        >
            @foreach($movimientos as $movimiento)
                <x-table-row>
                    <x-table-cell>
                        <div class="text-sm">
                            <div class="font-medium text-gray-900">{{ $movimiento->created_at->format('d/m/Y') }}</div>
                            <div class="text-gray-500">{{ $movimiento->created_at->format('H:i') }}</div>
                        </div>
                    </x-table-cell>
                    <x-table-cell wrap class="max-w-lg">
                        <div class="text-sm break-words">
                            @if($movimiento->libro)
                                <div class="font-medium text-gray-900">{{ $movimiento->libro->nombre }}</div>
                                <div class="text-gray-500 text-xs">{{ $movimiento->libro->codigo_barras }}</div>
                            @else
                                <div class="font-medium text-red-600">(Libro eliminado)</div>
                                <div class="text-gray-500 text-xs">ID: {{ $movimiento->libro_id }}</div>
                            @endif
                        </div>
                    </x-table-cell>
                    <x-table-cell>
                        <span class="px-3 py-1 rounded-full text-xs font-medium {{ $movimiento->getBadgeColor() }}">
                            <i class="{{ $movimiento->getIcon() }}"></i>
                            {{ $movimiento->getTipoLabel() }}
                        </span>
                        @if($movimiento->ajusteMasivo)
                            <div class="mt-1 text-gray-500" style="word-break: break-all; font-family: monospace; font-size: 10px;" title="{{ $movimiento->ajusteMasivo->folio }}">
                                <i class="fas fa-layer-group mr-1" style="font-size: 9px;"></i>{{ $movimiento->ajusteMasivo->folio }}
                            </div>
                        @endif
                    </x-table-cell>
                    <x-table-cell align="center">
                        @if($movimiento->tipo_movimiento === 'entrada')
                            <div class="flex items-center justify-center">
                                <span class="text-green-600 font-bold flex items-center">
                                    <i class="fas fa-arrow-up mr-1"></i> Entrada
                                </span>
                            </div>
                        @else
                            <div class="flex items-center justify-center">
                                <span class="text-red-600 font-bold flex items-center">
                                    <i class="fas fa-arrow-down mr-1"></i> Salida
                                </span>
                            </div>
                        @endif
                    </x-table-cell>
                    <x-table-cell>
                        <div class="text-sm text-gray-700">
                            <i class="{{ $movimiento->subinventario_id ? 'fas fa-store' : 'fas fa-warehouse' }} text-gray-400 mr-1"></i>
                            {{ $movimiento->getOrigenLabel() }}
                        </div>
                    </x-table-cell>
                    <x-table-cell align="center">
                        <span class="text-lg font-bold {{ $movimiento->tipo_movimiento === 'entrada' ? 'text-green-600' : 'text-red-600' }}">
                            {{ $movimiento->tipo_movimiento === 'entrada' ? '+' : '-' }}{{ $movimiento->cantidad }}
                        </span>
                    </x-table-cell>
                    <x-table-cell>
                        <div class="flex items-center text-gray-600">
                            <i class="fas fa-user-circle mr-2"></i>
                            {{ $movimiento->usuario ?? 'N/A' }}
                        </div>
                    </x-table-cell>
                    <x-table-cell align="center">
                        <x-button 
                            href="{{ route('movimientos.show', $movimiento) }}" 
                            variant="primary" 
                            size="sm"
                            icon="fas fa-eye"
                            title="Ver detalles">
                        </x-button>
                    </x-table-cell>
                </x-table-row>
            @endforeach
        </x-table>

        <!-- Paginación -->
        @if($movimientos->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $movimientos->appends(request()->query())->links() }}
            </div>
        @endif
    </x-card>
</x-page-layout>
@endsection
