@extends('layouts.app')

@section('title', 'Detalle del Cliente')

@section('page-title', 'Detalle del Cliente')
@section('page-description', 'Información completa del cliente')

@section('content')
<x-page-layout 
    title="Detalle del Cliente"
    :description="'Información completa de: ' . $cliente->nombre"
    button-text="Volver a Clientes"
    button-icon="fas fa-arrow-left"
    :button-route="route('clientes.index')"
>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Información principal -->
        <div class="lg:col-span-2">
            <x-card title="Información del Cliente" class="h-full">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <p class="text-sm text-gray-600 mb-1">ID</p>
                        <p class="text-lg font-semibold text-gray-800">{{ $cliente->id }}</p>
                    </div>

                    <div>
                        <p class="text-sm text-gray-600 mb-1">Nombre</p>
                        <p class="text-lg font-semibold text-gray-800">{{ $cliente->nombre }}</p>
                    </div>

                    <div>
                        <p class="text-sm text-gray-600 mb-1">Teléfono</p>
                        <p class="text-lg font-semibold {{ $cliente->telefono ? 'text-gray-800' : 'text-gray-400 italic' }}">
                            @if($cliente->telefono)
                                <i class="fas fa-phone text-gray-500 mr-2"></i>{{ $cliente->telefono }}
                            @else
                                Sin teléfono registrado
                            @endif
                        </p>
                    </div>

                    <div>
                        <p class="text-sm text-gray-600 mb-1">Total de Ventas</p>
                        <p class="text-lg font-semibold text-gray-800">
                            {{ $cliente->ventas->where('estado', '!=', 'cancelada')->count() }} ventas
                        </p>
                        @if($cliente->ventas->where('estado', 'cancelada')->count() > 0)
                            <p class="text-xs text-gray-500 mt-1">
                                ({{ $cliente->ventas->where('estado', 'cancelada')->count() }} canceladas)
                            </p>
                        @endif
                    </div>

                    <div>
                        <p class="text-sm text-gray-600 mb-1">Monto Total Comprado</p>
                        <p class="text-lg font-semibold text-green-600">
                            ${{ number_format($cliente->ventas->where('estado', '!=', 'cancelada')->sum('total'), 2) }}
                        </p>
                        @if($cliente->ventas->where('estado', 'cancelada')->count() > 0)
                            <p class="text-xs text-gray-500 mt-1">
                                (Excluye ventas canceladas)
                            </p>
                        @endif
                    </div>

                    <div>
                        <p class="text-sm text-gray-600 mb-1">Total Adeudado</p>
                        <p class="text-lg font-semibold text-red-600">
                            ${{ number_format($cliente->ventas->where('estado', '!=', 'cancelada')->where('es_a_plazos', true)->sum(function($v) { return $v->total - $v->total_pagado; }), 2) }}
                        </p>
                        @if($cliente->ventas->where('es_a_plazos', true)->where('estado', '!=', 'cancelada')->where('estado_pago', '!=', 'completado')->count() > 0)
                            <p class="text-xs text-orange-600 mt-1">
                                {{ $cliente->ventas->where('es_a_plazos', true)->where('estado', '!=', 'cancelada')->where('estado_pago', '!=', 'completado')->count() }} venta(s) pendiente(s)
                            </p>
                        @endif
                    </div>
                </div>
            </x-card>
        </div>

        <!-- Acciones -->
        <div class="lg:col-span-1">
            <x-card title="Acciones" class="h-full">
                <div class="space-y-3">
                    <x-button 
                        variant="primary" 
                        icon="fas fa-edit" 
                        onclick="window.location='{{ route('clientes.edit', $cliente->id) }}'" 
                        class="w-full justify-center"
                    >
                        Editar Cliente
                    </x-button>
                    
                    <x-button 
                        variant="secondary" 
                        icon="fas fa-arrow-left" 
                        onclick="window.location='{{ route('clientes.index') }}'" 
                        class="w-full justify-center"
                    >
                        Volver al Listado
                    </x-button>
                    
                    @if($cliente->ventas->count() == 0 && $cliente->apartados->count() == 0)
                        <form action="{{ route('clientes.destroy', $cliente->id) }}" method="POST">
                            @csrf
                            @method('DELETE')
                            <x-button 
                                type="submit" 
                                variant="danger" 
                                icon="fas fa-trash" 
                                onclick="return confirm('¿Estás seguro de eliminar este cliente?')" 
                                class="w-full justify-center"
                            >
                                Eliminar Cliente
                            </x-button>
                        </form>
                    @else
                        <div class="p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                            <p class="text-xs text-yellow-800">
                                <i class="fas fa-info-circle mr-1"></i>
                                No se puede eliminar porque tiene ventas o apartados asociados
                            </p>
                        </div>
                    @endif
                </div>
            </x-card>
        </div>
    </div>

    <!-- Información de fechas -->
    <x-card title="Información de Registro">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="flex items-center justify-between py-3 border-b border-gray-100">
                <span class="text-gray-600 font-medium">
                    <i class="fas fa-calendar-plus text-blue-500 mr-2"></i>
                    Fecha de Registro
                </span>
                <span class="text-gray-800 font-semibold">
                    {{ $cliente->created_at->format('d/m/Y H:i') }}
                </span>
            </div>
            <div class="flex items-center justify-between py-3 border-b border-gray-100">
                <span class="text-gray-600 font-medium">
                    <i class="fas fa-clock text-green-500 mr-2"></i>
                    Última Actualización
                </span>
                <span class="text-gray-800 font-semibold">
                    {{ $cliente->updated_at->format('d/m/Y H:i') }}
                </span>
            </div>
        </div>
    </x-card>

    <!-- Estadísticas detalladas de ventas -->
    @if($cliente->ventas->count() > 0)
        <x-card title="Resumen de Ventas">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <!-- Ventas Completadas -->
                <div class="text-center p-4 bg-green-50 rounded-lg border border-green-200">
                    <div class="text-3xl font-bold text-green-600">
                        {{ $cliente->ventas->where('estado', 'completada')->count() }}
                    </div>
                    <div class="text-sm text-green-700 mt-1">Completadas</div>
                    <div class="text-xs text-gray-600 mt-1">
                        ${{ number_format($cliente->ventas->where('estado', 'completada')->sum('total'), 2) }}
                    </div>
                </div>

                <!-- Ventas a Plazos Activas -->
                @php
                    $ventasAPlazosActivas = $cliente->ventas
                        ->where('es_a_plazos', true)
                        ->where('estado', '!=', 'cancelada')
                        ->where('estado_pago', '!=', 'completado');
                @endphp
                <div class="text-center p-4 bg-orange-50 rounded-lg border border-orange-200">
                    <div class="text-3xl font-bold text-orange-600">
                        {{ $ventasAPlazosActivas->count() }}
                    </div>
                    <div class="text-sm text-orange-700 mt-1">A Plazos</div>
                    <div class="text-xs text-gray-600 mt-1">
                        Saldo: ${{ number_format($ventasAPlazosActivas->sum(function($v) { return $v->total - $v->total_pagado; }), 2) }}
                    </div>
                </div>

                <!-- Ventas Pendientes -->
                <div class="text-center p-4 bg-yellow-50 rounded-lg border border-yellow-200">
                    <div class="text-3xl font-bold text-yellow-600">
                        {{ $cliente->ventas->where('estado', 'pendiente')->count() }}
                    </div>
                    <div class="text-sm text-yellow-700 mt-1">Pendientes</div>
                    <div class="text-xs text-gray-600 mt-1">
                        ${{ number_format($cliente->ventas->where('estado', 'pendiente')->sum('total'), 2) }}
                    </div>
                </div>

                <!-- Ventas Canceladas -->
                @if($cliente->ventas->where('estado', 'cancelada')->count() > 0)
                    <div class="text-center p-4 bg-red-50 rounded-lg border border-red-200">
                        <div class="text-3xl font-bold text-red-600">
                            {{ $cliente->ventas->where('estado', 'cancelada')->count() }}
                        </div>
                        <div class="text-sm text-red-700 mt-1">Canceladas</div>
                        <div class="text-xs text-gray-600 mt-1">
                            No cuentan en totales
                        </div>
                    </div>
                @endif
            </div>
        </x-card>
    @endif

    <!-- Historial de ventas -->
    @if($ventas->count() > 0)
        <x-card title="Historial de Ventas">
            <x-data-table 
                :headers="['Código', 'Fecha', 'Total', 'Estado']"
                :rows="$ventas"
                emptyMessage="No hay ventas registradas"
                emptyIcon="fas fa-shopping-cart"
            >
                @foreach($ventas as $venta)
                    <x-data-table-row class="{{ $venta->estado === 'cancelada' ? 'opacity-60 bg-gray-50' : '' }}">
                        <x-data-table-cell bold>
                            #{{ $venta->id }}
                            @if($venta->estado === 'cancelada')
                                <span class="ml-2 text-xs text-red-600">
                                    <i class="fas fa-ban"></i>
                                </span>
                            @endif
                        </x-data-table-cell>
                        <x-data-table-cell>{{ $venta->fecha_venta->format('d/m/Y H:i') }}</x-data-table-cell>
                        <x-data-table-cell>
                            <span class="{{ $venta->estado === 'cancelada' ? 'line-through text-gray-500' : '' }}">
                                ${{ number_format($venta->total, 2) }}
                            </span>
                            @if($venta->estado === 'cancelada')
                                <span class="block text-xs text-red-600 mt-1">
                                    No cuenta en totales
                                </span>
                            @endif
                        </x-data-table-cell>
                        <x-data-table-cell>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $venta->getEstadoUnificadoBadgeColor() }}">
                                <i class="{{ $venta->getEstadoUnificadoIcon() }} mr-1"></i>
                                {{ $venta->getEstadoUnificadoLabel() }}
                            </span>
                        </x-data-table-cell>
                        <x-data-table-cell>
                            <a href="{{ route('ventas.show', $venta->id) }}" 
                               class="text-blue-600 hover:text-blue-800 transition-colors font-medium text-sm">
                                <i class="fas fa-eye mr-1"></i> Ver detalles
                            </a>
                        </x-data-table-cell>
                    </x-data-table-row>
                @endforeach
            </x-data-table>

            <!-- Paginación -->
            @if($ventas->hasPages())
                <div class="mt-4 px-6 py-4 border-t border-gray-200">
                    {{ $ventas->appends(request()->except(['ventas_page', 'apartados_page']))->links() }}
                </div>
            @endif
        </x-card>
    @else
        <x-card>
            <div class="text-center py-12">
                <i class="fas fa-shopping-cart text-gray-300 text-6xl mb-4"></i>
                <h3 class="text-lg font-semibold text-gray-700 mb-2">Sin ventas registradas</h3>
                <p class="text-gray-500 mb-4">Este cliente aún no tiene ventas asociadas</p>
                <x-button 
                    variant="primary" 
                    icon="fas fa-plus"
                    onclick="window.location='{{ route('ventas.create') }}'"
                >
                    Crear Nueva Venta
                </x-button>
            </div>
        </x-card>
    @endif

    <!-- Historial de apartados -->
    @if($apartados->count() > 0)
        <x-card title="Historial de Apartados">
            <x-data-table
                :headers="['Folio', 'Fecha', 'Libros', 'Total', 'Pagado / Saldo', 'Estado']"
                :rows="$apartados"
                emptyMessage="No hay apartados registrados"
                emptyIcon="fas fa-handshake"
            >
                @foreach($apartados as $apartado)
                    <x-data-table-row class="{{ $apartado->estado === 'cancelado' ? 'opacity-60 bg-gray-50' : '' }}">
                        <x-data-table-cell bold>
                            {{ $apartado->folio }}
                            <span class="block text-xs text-gray-500">#{{ $apartado->id }}</span>
                        </x-data-table-cell>
                        <x-data-table-cell>
                            {{ $apartado->fecha_apartado->format('d/m/Y') }}
                            @if($apartado->fecha_limite)
                                <span class="block text-xs {{ $apartado->estaVencido ? 'text-red-600 font-semibold' : 'text-gray-500' }}">
                                    <i class="fas fa-clock mr-1"></i>Vence: {{ $apartado->fecha_limite->format('d/m/Y') }}
                                </span>
                            @endif
                        </x-data-table-cell>
                        <x-data-table-cell>
                            {{ $apartado->detalles_count }} libro(s)
                        </x-data-table-cell>
                        <x-data-table-cell>
                            ${{ number_format($apartado->monto_total, 2) }}
                        </x-data-table-cell>
                        <x-data-table-cell>
                            <span class="block text-sm text-green-600 font-medium">
                                <i class="fas fa-check mr-1"></i>${{ number_format($apartado->totalPagado, 2) }}
                            </span>
                            <span class="block text-sm text-orange-600">
                                <i class="fas fa-exclamation-circle mr-1"></i>${{ number_format($apartado->saldo_pendiente, 2) }}
                            </span>
                            <span class="block text-xs text-gray-500">{{ $apartado->porcentajePagado }}% pagado</span>
                        </x-data-table-cell>
                        <x-data-table-cell>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $apartado->getBadgeColor() }}">
                                <i class="{{ $apartado->getIcon() }} mr-1"></i>
                                {{ $apartado->getEstadoLabel() }}
                            </span>
                        </x-data-table-cell>
                        <x-data-table-cell>
                            <a href="{{ route('apartados.show', $apartado->id) }}"
                               class="text-blue-600 hover:text-blue-800 transition-colors font-medium text-sm">
                                <i class="fas fa-eye mr-1"></i> Ver detalles
                            </a>
                        </x-data-table-cell>
                    </x-data-table-row>
                @endforeach
            </x-data-table>

            @if($apartados->hasPages())
                <div class="mt-4 px-6 py-4 border-t border-gray-200">
                    {{ $apartados->appends(request()->except(['ventas_page', 'apartados_page']))->links() }}
                </div>
            @endif
        </x-card>
    @endif
</x-page-layout>
@endsection
