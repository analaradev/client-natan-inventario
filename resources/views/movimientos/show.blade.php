@extends('layouts.app')

@section('title', 'Detalle del Movimiento')

@section('page-title', 'Detalle del Movimiento')
@section('page-description', 'Información completa del movimiento de inventario')

@section('content')
<x-page-layout 
    title="Detalle del Movimiento"
    description="Información completa del movimiento de inventario"
    button-text="Volver a Movimientos"
    button-icon="fas fa-arrow-left"
    :button-route="route('movimientos.index')"
>
    <!-- Grid principal: Información y Últimos Movimientos + Acciones -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Columna Izquierda: Información del Movimiento -->
        <div>
            <x-card title="Información del Movimiento" class="h-full">
                <div class="flex items-start justify-between mb-6">
                    <div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-2">
                            Movimiento #{{ $movimiento->id }}
                        </h3>
                        <p class="text-gray-600">
                            <i class="fas fa-calendar-alt"></i>
                            {{ $movimiento->created_at->format('d/m/Y H:i:s') }}
                        </p>
                    </div>
                    <div class="text-right">
                        @if($movimiento->tipo_movimiento === 'entrada')
                            <div class="inline-flex items-center px-4 py-2 bg-green-100 text-green-800 rounded-lg font-bold">
                                <i class="fas fa-arrow-down mr-2 text-xl"></i>
                                ENTRADA
                            </div>
                        @else
                            <div class="inline-flex items-center px-4 py-2 bg-red-100 text-red-800 rounded-lg font-bold">
                                <i class="fas fa-arrow-up mr-2 text-xl"></i>
                                SALIDA
                            </div>
                        @endif
                    </div>
                </div>

                <div class="space-y-4">
                    <!-- Información del Libro -->
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h4 class="text-sm font-medium text-gray-500 mb-3">
                            <i class="fas fa-book"></i> INFORMACIÓN DEL LIBRO
                        </h4>
                        <div class="space-y-2">
                            @if($movimiento->libro)
                                <div>
                                    <p class="text-xs text-gray-500">Nombre</p>
                                    <p class="text-lg font-semibold text-gray-900">{{ $movimiento->libro->nombre }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Código de Barras</p>
                                    <p class="font-mono text-gray-700">{{ $movimiento->libro->codigo_barras }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Stock Actual</p>
                                    <p class="text-lg font-bold text-primary-600">
                                        <i class="fas fa-boxes"></i> {{ $movimiento->libro->stock }} unidades
                                    </p>
                                </div>
                            @else
                                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                                    <p class="text-red-600 font-semibold">
                                        <i class="fas fa-exclamation-triangle"></i> Libro Eliminado
                                    </p>
                                    <p class="text-sm text-gray-600 mt-1">
                                        Este movimiento pertenecía a un libro que ya no existe en el sistema (ID: {{ $movimiento->libro_id }})
                                    </p>
                                </div>
                            @endif
                            </div>
                        </div>
                    </div>

                    <!-- Detalles del Movimiento -->
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h4 class="text-sm font-medium text-gray-500 mb-3">
                            <i class="fas fa-info-circle"></i> DETALLES DEL MOVIMIENTO
                        </h4>
                        <div class="space-y-2">
                            <div>
                                <p class="text-xs text-gray-500">Tipo de Movimiento</p>
                                <span class="inline-block px-3 py-1 rounded-full text-sm font-medium {{ $movimiento->getBadgeColor() }}">
                                    <i class="{{ $movimiento->getIcon() }}"></i>
                                    {{ $movimiento->getTipoLabel() }}
                                </span>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Cantidad</p>
                                <p class="text-2xl font-bold {{ $movimiento->tipo_movimiento === 'entrada' ? 'text-green-600' : 'text-red-600' }}">
                                    {{ $movimiento->tipo_movimiento === 'entrada' ? '+' : '-' }}{{ $movimiento->cantidad }} unidades
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Origen</p>
                                <p class="text-sm font-medium text-gray-900">
                                    <i class="{{ $movimiento->subinventario_id ? 'fas fa-store' : 'fas fa-warehouse' }} text-gray-400"></i>
                                    {{ $movimiento->getOrigenLabel() }}
                                </p>
                            </div>
                            @if($movimiento->ajusteMasivo)
                                <div>
                                    <p class="text-xs text-gray-500">Ajuste Masivo</p>
                                    <p class="text-sm font-medium text-gray-900">
                                        <i class="fas fa-layer-group text-gray-400"></i>
                                        {{ $movimiento->ajusteMasivo->folio }}
                                    </p>
                                </div>
                            @endif
                            @if($movimiento->precio_unitario)
                                <div>
                                    <p class="text-xs text-gray-500">Precio Unitario</p>
                                    <p class="text-lg font-semibold text-gray-900">
                                        ${{ number_format($movimiento->precio_unitario, 2) }}
                                    </p>
                                </div>
                                @if($movimiento->descuento)
                                    <div>
                                        <p class="text-xs text-gray-500">Descuento</p>
                                        <p class="text-lg font-semibold text-orange-600">
                                            {{ number_format($movimiento->descuento, 1) }}% 
                                            <span class="text-sm text-gray-500">
                                                (-${{ number_format(($movimiento->precio_unitario * $movimiento->descuento / 100), 2) }})
                                            </span>
                                        </p>
                                    </div>
                                @endif
                                <div class="pt-2 border-t border-gray-200">
                                    <p class="text-xs text-gray-500">Total</p>
                                    <p class="text-xl font-bold text-gray-900">
                                        @php
                                            $precioConDescuento = $movimiento->precio_unitario;
                                            if ($movimiento->descuento) {
                                                $precioConDescuento -= ($movimiento->precio_unitario * $movimiento->descuento / 100);
                                            }
                                        @endphp
                                        ${{ number_format($precioConDescuento * $movimiento->cantidad, 2) }}
                                    </p>
                                </div>
                            @endif
                            @if($movimiento->fecha)
                                <div class="pt-2 border-t border-gray-200">
                                    <p class="text-xs text-gray-500">Fecha del Movimiento</p>
                                    <p class="text-sm font-medium text-gray-900">
                                        <i class="fas fa-calendar text-gray-400"></i>
                                        {{ \Carbon\Carbon::parse($movimiento->fecha)->format('d/m/Y') }}
                                    </p>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Observaciones -->
                    @if($movimiento->observaciones)
                        <div class="p-4 bg-yellow-50 border-l-4 border-yellow-400 rounded">
                            <h4 class="text-sm font-medium text-yellow-800 mb-2">
                                <i class="fas fa-sticky-note"></i> Observaciones
                            </h4>
                            <p class="text-gray-700">{{ $movimiento->observaciones }}</p>
                        </div>
                    @endif

                    <!-- Información del Usuario -->
                    <div class="pt-4 border-t border-gray-200 flex justify-between items-center">
                        <div class="text-sm text-gray-600">
                            <i class="fas fa-user-circle"></i>
                            Registrado por: <strong>{{ $movimiento->usuario ?? 'N/A' }}</strong>
                        </div>
                        <div class="text-sm text-gray-500">
                            <i class="fas fa-clock"></i>
                            Hace {{ $movimiento->created_at->diffForHumans() }}
                        </div>
                    </div>
                </div>
            </x-card>
        </div>

        <!-- Columna Derecha: Últimos Movimientos y Acciones -->
        <div class="space-y-6">
            <x-card title="Últimos Movimientos del Libro">
                @if($movimientosLibro->isEmpty())
                    <div class="flex flex-col items-center justify-center h-full text-center py-12 text-gray-500">
                        <i class="fas fa-inbox text-4xl mb-3"></i>
                        <p>No hay otros movimientos registrados para este libro</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Fecha
                                    </th>
                                    <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Tipo
                                    </th>
                                    <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Cantidad
                                    </th>
                                    <th scope="col" class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Acción
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($movimientosLibro as $mov)
                                    <tr class="hover:bg-gray-50 transition-colors duration-150">
                                        <td class="px-3 py-3 whitespace-nowrap text-sm text-gray-900">
                                            <div>
                                                <div class="font-medium">{{ $mov->created_at->format('d/m/Y') }}</div>
                                                <div class="text-xs text-gray-500">{{ $mov->created_at->format('H:i') }}</div>
                                            </div>
                                        </td>
                                        <td class="px-3 py-3 whitespace-nowrap">
                                            <span class="px-2 py-1 rounded-full text-xs font-medium {{ $mov->getBadgeColor() }}">
                                                <i class="{{ $mov->getIcon() }}"></i>
                                                {{ $mov->getTipoLabel() }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-3 whitespace-nowrap text-sm">
                                            <span class="font-bold {{ $mov->tipo_movimiento === 'entrada' ? 'text-green-600' : 'text-red-600' }}">
                                                {{ $mov->tipo_movimiento === 'entrada' ? '+' : '-' }}{{ $mov->cantidad }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-3 whitespace-nowrap text-center text-sm">
                                            <a href="{{ route('movimientos.show', $mov) }}" 
                                               class="text-primary-600 hover:text-primary-900 font-medium">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4 text-center text-sm text-gray-500">
                        <i class="fas fa-info-circle"></i>
                        Mostrando los últimos 5 movimientos
                    </div>
                @endif
            </x-card>

            <!-- Acciones -->
            <x-card title="Acciones">
                <div class="space-y-3">
                    <x-button 
                        variant="secondary" 
                        icon="fas fa-arrow-left"
                        onclick="window.location='{{ route('movimientos.index') }}'"
                        class="w-full justify-center"
                    >
                        Volver al Listado
                    </x-button>
                    
                    @if($movimiento->libro)
                        <x-button 
                            variant="primary" 
                            icon="fas fa-book"
                            onclick="window.location='{{ route('inventario.show', $movimiento->libro) }}'"
                            class="w-full justify-center"
                        >
                            Ver Libro
                        </x-button>
                    @else
                        <x-button 
                            variant="secondary" 
                            icon="fas fa-exclamation-triangle"
                            class="w-full justify-center"
                            disabled
                        >
                            Libro No Disponible
                        </x-button>
                    @endif
                </div>
            </x-card>
        </div>
    </div>
</x-page-layout>
@endsection
