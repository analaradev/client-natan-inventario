@extends('layouts.app')

@section('title', 'Corte de Caja')

@section('page-title', 'Corte de Caja')
@section('page-description', 'Detalle completo del cierre')

@section('content')
<x-page-layout
    title="Corte de Caja #{{ $corte->id }}"
    :description="'Fecha: ' . $corte->fecha_corte->format('d/m/Y')"
    button-text="Volver a Cortes"
    button-icon="fas fa-arrow-left"
    :button-route="route('cortes.index')"
>
    <x-slot name="header">
        <x-button 
            href="{{ route('cortes.export-individual.excel', $corte) }}" 
            variant="success" 
            icon="fas fa-file-excel"
            class="mr-2"
        >
            Descargar Excel
        </x-button>
        <x-button 
            href="{{ route('cortes.export-individual.pdf', $corte) }}" 
            variant="danger" 
            icon="fas fa-file-pdf"
        >
            Descargar PDF
        </x-button>
    </x-slot>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
            <x-card title="Información del Corte" class="h-full">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Folio</p>
                        <p class="text-lg font-mono font-bold text-gray-800">#{{ $corte->id }}</p>
                    </div>

                    <div>
                        <p class="text-sm text-gray-600 mb-1">Estado</p>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                            <i class="fas fa-check-circle mr-1"></i>
                            Cerrado
                        </span>
                    </div>

                    <div>
                        <p class="text-sm text-gray-600 mb-1">Fecha del corte</p>
                        <p class="text-lg font-semibold text-gray-800">{{ $corte->fecha_corte->format('d/m/Y') }}</p>
                    </div>

                    <div>
                        <p class="text-sm text-gray-600 mb-1">Caja de</p>
                        <p class="text-lg font-semibold text-gray-800">
                            @if($corte->tipo_inventario === 'subinventario')
                                {{ $corte->subinventario->descripcion ?? 'Subinventario' }}
                            @elseif($corte->tipo_inventario === 'general')
                                Inventario general
                            @else
                                Todas las cajas
                            @endif
                        </p>
                    </div>

                    <div>
                        <p class="text-sm text-gray-600 mb-1">Cerrado por</p>
                        <p class="text-lg font-semibold text-gray-800">
                            <i class="fas fa-user text-primary-600 mr-2"></i>
                            {{ $corte->usuario_cierre }}
                        </p>
                    </div>

                    <div>
                        <p class="text-sm text-gray-600 mb-1">Ingresos incluidos</p>
                        <p class="text-lg font-semibold">
                            <span class="px-3 py-1 rounded-full text-sm bg-blue-100 text-blue-800">
                                {{ $corte->ingresos->count() }} movimiento(s)
                            </span>
                        </p>
                    </div>
                </div>
            </x-card>
        </div>

        <div class="lg:col-span-1">
            <x-card title="Resumen del Cierre" class="h-full">
                <div class="flex flex-col justify-between h-full">
                    <div class="space-y-4">
                        <div class="flex justify-between items-center py-3 border-b border-gray-100">
                            <span class="text-gray-600 font-medium">Sistema:</span>
                            <span class="text-gray-800 font-semibold text-lg">${{ number_format($corte->total_sistema, 2) }}</span>
                        </div>

                        <div class="flex justify-between items-center py-3 border-b border-gray-100">
                            <span class="text-gray-600 font-medium">Reportado:</span>
                            <span class="text-gray-800 font-semibold text-lg">${{ number_format($corte->total_reportado, 2) }}</span>
                        </div>

                        <div class="flex justify-between items-center py-3 rounded-lg px-3 {{ abs((float) $corte->diferencia) > 0.009 ? 'bg-red-50' : 'bg-green-50' }}">
                            <span class="font-bold {{ abs((float) $corte->diferencia) > 0.009 ? 'text-red-700' : 'text-green-700' }}">Diferencia:</span>
                            <span class="font-bold text-2xl {{ abs((float) $corte->diferencia) > 0.009 ? 'text-red-600' : 'text-green-600' }}">
                                ${{ number_format($corte->diferencia, 2) }}
                            </span>
                        </div>
                    </div>

                    <div class="mt-4 p-3 bg-gray-50 border-l-4 border-gray-300 rounded">
                        <p class="text-xs font-medium text-gray-700 mb-1">
                            <i class="fas fa-sticky-note"></i> Observaciones
                        </p>
                        <p class="text-sm text-gray-700 whitespace-pre-line">{{ $corte->observaciones ?: 'Sin observaciones' }}</p>
                    </div>
                </div>
            </x-card>
        </div>
    </div>

    <x-card title="Metodo de pago">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            @foreach(\App\Models\IngresoCaja::metodosPago() as $key => $label)
                <div class="border border-gray-200 rounded-lg p-4">
                    <p class="text-sm text-gray-600 mb-1">{{ $label }}</p>
                    <p class="text-xl font-bold text-gray-900">${{ number_format($resumen['metodos'][$key] ?? 0, 2) }}</p>
                </div>
            @endforeach
        </div>
    </x-card>

    @include('cortes.partials.ingresos-cards', ['ingresos' => $corte->ingresos])

    @include('cortes.partials.productos-table', ['productos' => $productos])
</x-page-layout>
@endsection
