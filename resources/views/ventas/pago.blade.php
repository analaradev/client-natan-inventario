@extends('layouts.app')

@section('title', 'Registrar Pago')

@section('page-title', 'Registrar Pago')
@section('page-description', 'Registrar un nuevo pago para la venta a plazos')

@section('content')
<x-page-layout 
    title="Registrar Pago"
    :description="'Venta #' . $venta->id . ' - ' . $venta->cliente->nombre"
    button-text="Volver a la Venta"
    button-icon="fas fa-arrow-left"
    :button-route="route('ventas.show', $venta)"
>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Formulario de Pago -->
        <div class="lg:col-span-2">
            <x-card title="Información del Pago" icon="fas fa-hand-holding-usd">
                <form action="{{ route('pagos.store', $venta) }}" method="POST" class="space-y-6">
                    @csrf
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Monto -->
                        <div>
                            <label for="monto" class="block text-sm font-medium text-gray-700 mb-2">
                                Monto del Pago <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <span class="absolute left-3 top-2.5 text-gray-400">
                                    <i class="fas fa-dollar-sign"></i>
                                </span>
                                <input 
                                    type="number" 
                                    name="monto" 
                                    id="monto" 
                                    step="0.01" 
                                    min="0.01"
                                    max="{{ $venta->saldo_pendiente }}"
                                    value="{{ old('monto') }}"
                                    class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 @error('monto') border-red-500 @enderror"
                                    placeholder="0.00"
                                    required
                                    autofocus>
                            </div>
                            <p class="mt-1 text-sm text-gray-500">
                                Máximo: ${{ number_format($venta->saldo_pendiente, 2) }}
                            </p>
                            @error('monto')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Fecha de Pago -->
                        <div>
                            <label for="fecha_pago" class="block text-sm font-medium text-gray-700 mb-2">
                                Fecha del Pago <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <span class="absolute left-3 top-2.5 text-gray-400">
                                    <i class="fas fa-calendar"></i>
                                </span>
                                <input 
                                    type="date" 
                                    name="fecha_pago" 
                                    id="fecha_pago" 
                                    value="{{ old('fecha_pago', date('Y-m-d')) }}"
                                    class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 @error('fecha_pago') border-red-500 @enderror"
                                    required>
                            </div>
                            @error('fecha_pago')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Metodo de Pago -->
                        <div>
                            <label for="metodo_pago" class="block text-sm font-medium text-gray-700 mb-2">
                                Metodo de Pago <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <span class="absolute left-3 top-2.5 text-gray-400">
                                    <i class="fas fa-credit-card"></i>
                                </span>
                                <select 
                                    name="metodo_pago" 
                                    id="metodo_pago"
                                    class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 @error('metodo_pago') border-red-500 @enderror"
                                    required>
                                    <option value="">Seleccionar metodo de pago</option>
                                    <option value="efectivo" {{ old('metodo_pago') == 'efectivo' ? 'selected' : '' }}>Efectivo</option>
                                    <option value="transferencia" {{ old('metodo_pago') == 'transferencia' ? 'selected' : '' }}>Transferencia</option>
                                    <option value="tarjeta" {{ old('metodo_pago') == 'tarjeta' ? 'selected' : '' }}>Tarjeta</option>
                                    <option value="no_especificado" {{ old('metodo_pago') == 'no_especificado' ? 'selected' : '' }}>No especificado</option>
                                </select>
                            </div>
                            @error('metodo_pago')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Comprobante -->
                        <div>
                            <label for="comprobante" class="block text-sm font-medium text-gray-700 mb-2">
                                Número de Comprobante
                            </label>
                            <div class="relative">
                                <span class="absolute left-3 top-2.5 text-gray-400">
                                    <i class="fas fa-receipt"></i>
                                </span>
                                <input 
                                    type="text" 
                                    name="comprobante" 
                                    id="comprobante" 
                                    value="{{ old('comprobante') }}"
                                    class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 @error('comprobante') border-red-500 @enderror"
                                    placeholder="Opcional">
                            </div>
                            @error('comprobante')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Notas -->
                    <div>
                        <label for="notas" class="block text-sm font-medium text-gray-700 mb-2">
                            Notas del Pago
                        </label>
                        <textarea 
                            name="notas" 
                            id="notas" 
                            rows="4"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 @error('notas') border-red-500 @enderror"
                            placeholder="Agregar notas u observaciones sobre este pago (opcional)">{{ old('notas') }}</textarea>
                        @error('notas')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    @if($errors->has('error'))
                        <div class="p-4 bg-red-50 border border-red-200 rounded-lg">
                            <p class="text-sm text-red-800">
                                <i class="fas fa-exclamation-circle mr-2"></i>
                                {{ $errors->first('error') }}
                            </p>
                        </div>
                    @endif

                    <!-- Botones -->
                    <div class="flex gap-3 justify-end pt-4">
                        <x-button 
                            type="button" 
                            variant="secondary" 
                            icon="fas fa-times"
                            onclick="window.location='{{ route('ventas.show', $venta) }}'">
                            Cancelar
                        </x-button>
                        <x-button 
                            type="submit" 
                            variant="primary" 
                            icon="fas fa-save">
                            Registrar Pago
                        </x-button>
                    </div>
                </form>
            </x-card>
        </div>

        <!-- Resumen de la Venta -->
        <div class="lg:col-span-1">
            <x-card title="Resumen de la Venta" icon="fas fa-file-invoice-dollar">
                <div class="space-y-4">
                    <!-- Cliente -->
                    <div class="p-3 bg-blue-50 rounded-lg">
                        <p class="text-xs text-gray-600 mb-1">Cliente</p>
                        <p class="text-sm font-semibold text-gray-900">
                            <i class="fas fa-user text-blue-600 mr-1"></i>
                            {{ $venta->cliente->nombre }}
                        </p>
                        @if($venta->cliente->telefono)
                            <p class="text-xs text-gray-600 mt-1">
                                <i class="fas fa-phone mr-1"></i>
                                {{ $venta->cliente->telefono }}
                            </p>
                        @endif
                    </div>

                    <!-- Estado -->
                    <div class="p-3 bg-gray-50 rounded-lg">
                        <p class="text-xs text-gray-600 mb-2">Estado Actual</p>
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $venta->getEstadoUnificadoBadgeColor() }}">
                            <i class="{{ $venta->getEstadoUnificadoIcon() }} mr-1"></i>
                            {{ $venta->getEstadoUnificadoLabel() }}
                        </span>
                    </div>

                    <!-- Total de la Venta -->
                    <div class="p-3 bg-primary-50 rounded-lg">
                        <p class="text-xs text-gray-600 mb-1">Total de la Venta</p>
                        <p class="text-2xl font-bold text-primary-600">
                            ${{ number_format($venta->total, 2) }}
                        </p>
                    </div>

                    <!-- Total Pagado -->
                    <div class="p-3 bg-green-50 rounded-lg">
                        <p class="text-xs text-gray-600 mb-1">Total Pagado</p>
                        <p class="text-xl font-bold text-green-600">
                            ${{ number_format($venta->total_pagado, 2) }}
                        </p>
                        <div class="mt-2 bg-green-200 rounded-full h-2 overflow-hidden">
                            <div class="bg-green-600 h-2 transition-all duration-300" 
                                 style="width: {{ ($venta->total_pagado / $venta->total) * 100 }}%">
                            </div>
                        </div>
                        <p class="text-xs text-gray-600 mt-1 text-center">
                            {{ number_format(($venta->total_pagado / $venta->total) * 100, 1) }}% pagado
                        </p>
                    </div>

                    <!-- Saldo Pendiente -->
                    <div class="p-3 bg-orange-50 rounded-lg border-2 border-gray-200">
                        <p class="text-xs text-gray-600 mb-1">Saldo Pendiente</p>
                        <p class="text-2xl font-bold text-orange-600">
                            ${{ number_format($venta->saldo_pendiente, 2) }}
                        </p>
                    </div>

                    <!-- Fecha Límite -->
                    @if($venta->fecha_limite)
                        <div class="p-3 {{ $venta->fecha_limite->isPast() ? 'bg-red-50 border-red-200' : 'bg-blue-50 border-blue-200' }} border rounded-lg">
                            <p class="text-xs text-gray-600 mb-1">Fecha Límite</p>
                            <p class="text-sm font-semibold {{ $venta->fecha_limite->isPast() ? 'text-red-600' : 'text-blue-600' }}">
                                <i class="fas fa-calendar-check mr-1"></i>
                                {{ $venta->fecha_limite->format('d/m/Y') }}
                            </p>
                            @if($venta->fecha_limite->isPast())
                                <p class="text-xs text-red-600 mt-1 font-medium">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>
                                    Vencida
                                </p>
                            @else
                                <p class="text-xs text-gray-600 mt-1">
                                    {{ $venta->fecha_limite->diffForHumans() }}
                                </p>
                            @endif
                        </div>
                    @endif

                    <!-- Cantidad de Pagos Realizados -->
                    @if($venta->pagos->count() > 0)
                        <div class="p-3 bg-gray-50 rounded-lg">
                            <p class="text-xs text-gray-600 mb-1">Pagos Realizados</p>
                            <p class="text-lg font-bold text-gray-900">
                                <i class="fas fa-list-ol text-gray-400 mr-1"></i>
                                {{ $venta->pagos->count() }} pago(s)
                            </p>
                        </div>
                    @endif
                </div>
            </x-card>
        </div>
    </div>

    <!-- Historial de Pagos -->
    @if($venta->pagos->count() > 0)
        <x-card title="Historial de Pagos" icon="fas fa-history">
            <div class="space-y-3">
                @foreach($venta->pagos->sortByDesc('fecha_pago') as $pago)
                    <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition-colors">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 bg-gradient-to-br from-green-100 to-green-200 rounded-full flex items-center justify-center">
                                    <i class="fas fa-dollar-sign text-green-600 text-lg"></i>
                                </div>
                                <div>
                                    <p class="text-lg font-bold text-green-600">
                                        ${{ number_format($pago->monto, 2) }}
                                    </p>
                                    <p class="text-sm text-gray-500">
                                        {{ \Carbon\Carbon::parse($pago->fecha_pago)->format('d/m/Y') }}
                                    </p>
                                </div>
                            </div>

                            <form action="{{ route('pagos.destroy', $pago) }}" method="POST" 
                                  onsubmit="return confirm('¿Estás seguro de eliminar este pago?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" 
                                        class="text-red-600 hover:text-red-700 p-2 rounded-lg hover:bg-red-50 transition-colors"
                                        title="Eliminar pago">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>

                        <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                            <div>
                                <p class="text-xs text-gray-500">Método</p>
                                <p class="text-sm font-semibold text-gray-800 capitalize">
                                    <i class="fas fa-credit-card text-gray-400 mr-1"></i>
                                    {{ $pago->metodo_pago }}
                                </p>
                            </div>

                            @if($pago->comprobante)
                                <div>
                                    <p class="text-xs text-gray-500">Comprobante</p>
                                    <p class="text-sm font-semibold text-gray-800 font-mono">
                                        <i class="fas fa-receipt text-gray-400 mr-1"></i>
                                        {{ $pago->comprobante }}
                                    </p>
                                </div>
                            @endif

                            <div>
                                <p class="text-xs text-gray-500">Registrado</p>
                                <p class="text-sm text-gray-700">
                                    <i class="fas fa-clock text-gray-400 mr-1"></i>
                                    {{ $pago->created_at->format('d/m/Y H:i') }}
                                </p>
                            </div>
                        </div>

                        @if($pago->notas)
                            <div class="mt-3 p-2 bg-blue-50 border-l-4 border-blue-400 rounded">
                                <p class="text-xs text-blue-800">
                                    <i class="fas fa-sticky-note mr-1"></i>
                                    {{ $pago->notas }}
                                </p>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </x-card>
    @endif
</x-page-layout>
@endsection
