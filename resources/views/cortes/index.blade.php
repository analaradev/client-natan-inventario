@extends('layouts.app')

@section('title', 'Cortes de Caja')

@section('page-title', 'Cortes de Caja')
@section('page-description', 'Cierres formales de ingresos del día')

@section('content')
<x-page-layout 
    title="Cortes de Caja"
    description="Gestión y consulta de cierres diarios"
>
    <x-slot name="header">
        <x-button 
            variant="primary" 
            icon="fas fa-plus"
            onclick="window.location='{{ route('cortes.create') }}'"
        >
            Nuevo Corte
        </x-button>
    </x-slot>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <x-stat-card 
            icon="fas fa-receipt"
            label="Total Cortes"
            :value="$estadisticas['total_cortes']"
            bg-color="bg-gray-800"
            icon-color="text-white"
        />

        <x-stat-card 
            icon="fas fa-calculator"
            label="Total Sistema"
            :value="'$' . number_format($estadisticas['total_sistema'], 2)"
            bg-color="bg-blue-100"
            icon-color="text-blue-600"
        />

        <x-stat-card 
            icon="fas fa-cash-register"
            label="Total Reportado"
            :value="'$' . number_format($estadisticas['total_reportado'], 2)"
            bg-color="bg-green-100"
            icon-color="text-green-600"
        />

        <x-stat-card 
            icon="fas fa-balance-scale"
            label="Diferencia"
            :value="'$' . number_format($estadisticas['diferencia'], 2)"
            :bg-color="abs((float) $estadisticas['diferencia']) > 0.009 ? 'bg-red-100' : 'bg-purple-100'"
            :icon-color="abs((float) $estadisticas['diferencia']) > 0.009 ? 'text-red-600' : 'text-purple-600'"
        />
    </div>

    <x-card class="overflow-visible">
        <form method="GET" action="{{ route('cortes.index') }}" class="overflow-visible">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4 overflow-visible items-end">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-calendar-day text-gray-400"></i> Fecha del corte
                    </label>
                    <input 
                        type="date" 
                        name="fecha_corte" 
                        value="{{ request('fecha_corte') }}" 
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                    >
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-cash-register text-gray-400"></i> Caja de
                    </label>
                    <select name="tipo_inventario" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <option value="">Todos</option>
                        <option value="general" {{ request('tipo_inventario') === 'general' ? 'selected' : '' }}>Inventario general</option>
                        <option value="subinventario" {{ request('tipo_inventario') === 'subinventario' ? 'selected' : '' }}>Subinventarios</option>
                    </select>
                </div>
            </div>

            <div class="flex flex-wrap justify-between items-center gap-3 pt-2">
                <div class="flex gap-3">
                    <x-button type="submit" variant="primary" icon="fas fa-filter">
                        Aplicar Filtros
                    </x-button>

                    @if(request()->hasAny(['fecha_corte', 'tipo_inventario']))
                        <x-button 
                            type="button" 
                            variant="secondary" 
                            icon="fas fa-times"
                            onclick="window.location='{{ route('cortes.index') }}'"
                        >
                            Limpiar Filtros
                        </x-button>
                    @endif
                </div>

                <div class="flex gap-3">
                    <x-button 
                        type="button" 
                        variant="success" 
                        icon="fas fa-file-excel"
                        onclick="window.location='{{ route('cortes.export.excel', request()->query()) }}'"
                    >
                        Exportar Excel
                    </x-button>

                    <x-button 
                        type="button" 
                        variant="danger" 
                        icon="fas fa-file-pdf"
                        onclick="window.location='{{ route('cortes.export.pdf', request()->query()) }}'"
                    >
                        Exportar PDF
                    </x-button>
                </div>
            </div>
        </form>
    </x-card>

    <x-card>
        <x-data-table 
            :headers="['Fecha', 'Caja de', 'Sistema', 'Reportado', 'Diferencia', 'Usuario']"
            :rows="$cortes"
            emptyMessage="No hay cortes registrados"
            emptyIcon="fas fa-receipt"
        >
            @foreach($cortes as $corte)
                <x-data-table-row>
                    <x-data-table-cell bold>{{ $corte->fecha_corte->format('d/m/Y') }}</x-data-table-cell>
                    <x-data-table-cell wrap>
                        @if($corte->tipo_inventario === 'subinventario')
                            {{ $corte->subinventario->descripcion ?? 'Subinventario' }}
                        @elseif($corte->tipo_inventario === 'general')
                            Inventario general
                        @else
                            Todos
                        @endif
                    </x-data-table-cell>
                    <x-data-table-cell class="text-right font-semibold text-gray-900">${{ number_format($corte->total_sistema, 2) }}</x-data-table-cell>
                    <x-data-table-cell class="text-right">${{ number_format($corte->total_reportado, 2) }}</x-data-table-cell>
                    <x-data-table-cell class="text-right {{ abs((float) $corte->diferencia) > 0.009 ? 'text-red-600 font-semibold' : 'text-green-700 font-semibold' }}">
                        ${{ number_format($corte->diferencia, 2) }}
                    </x-data-table-cell>
                    <x-data-table-cell wrap>{{ $corte->usuario_cierre }}</x-data-table-cell>
                    <td class="px-6 py-4 text-sm whitespace-nowrap">
                        <div class="flex items-center justify-end gap-1">
                            <a href="{{ route('cortes.show', $corte) }}" class="inline-flex items-center justify-center px-3 py-1.5 rounded-lg bg-gray-800 hover:bg-gray-900 text-white" title="Ver detalles">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="{{ route('cortes.export-individual.excel', $corte) }}" class="inline-flex items-center justify-center px-3 py-1.5 rounded-lg bg-green-600 hover:bg-green-700 text-white" title="Descargar Excel">
                                <i class="fas fa-file-excel"></i>
                            </a>
                            <a href="{{ route('cortes.export-individual.pdf', $corte) }}" class="inline-flex items-center justify-center px-3 py-1.5 rounded-lg bg-red-600 hover:bg-red-700 text-white" title="Descargar PDF">
                                <i class="fas fa-file-pdf"></i>
                            </a>
                        </div>
                    </td>
                </x-data-table-row>
            @endforeach
        </x-data-table>

        @if($cortes->hasPages())
            <div class="mt-4 px-6 py-4 border-t border-gray-200">
                {{ $cortes->appends(request()->query())->links() }}
            </div>
        @endif
    </x-card>
</x-page-layout>
@endsection
