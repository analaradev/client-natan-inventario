@extends('layouts.app')

@section('title', 'Detalle del Apartado')

@section('page-title', 'Detalle del Apartado')
@section('page-description', 'Información completa del apartado')

@section('content')
<x-page-layout 
    title="Detalle del Apartado"
    :description="'Apartado ' . $apartado->folio"
    button-text="Volver a Apartados"
    button-icon="fas fa-arrow-left"
    :button-route="route('apartados.index')"
>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Información principal -->
        <div class="lg:col-span-2">
            <x-card title="Información del Apartado" class="h-full">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Folio</p>
                        <p class="text-lg font-mono font-bold text-gray-800">{{ $apartado->folio }}</p>
                    </div>

                    <div>
                        <p class="text-sm text-gray-600 mb-1">Estado</p>
                        <p class="text-lg font-semibold">
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $apartado->getBadgeColor() }}">
                                <i class="{{ $apartado->getIcon() }} mr-1"></i>
                                {{ $apartado->getEstadoLabel() }}
                            </span>
                        </p>
                    </div>

                    <div>
                        <p class="text-sm text-gray-600 mb-1">Cliente</p>
                        <p class="text-lg font-semibold text-gray-800">
                            <i class="fas fa-user text-primary-600 mr-2"></i>
                            {{ $apartado->cliente->nombre }}
                        </p>
                        @if($apartado->cliente->telefono)
                            <p class="text-sm text-gray-600 mt-1">
                                <i class="fas fa-phone mr-1"></i>
                                {{ $apartado->cliente->telefono }}
                            </p>
                        @endif
                    </div>

                    <div>
                        <p class="text-sm text-gray-600 mb-1">Fecha de Apartado</p>
                        <p class="text-lg font-semibold text-gray-800">{{ $apartado->fecha_apartado->format('d/m/Y') }}</p>
                    </div>

                    @if($apartado->fecha_limite)
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Fecha Límite</p>
                        <p class="text-lg font-semibold {{ $apartado->estaVencido ? 'text-red-600' : 'text-gray-800' }}">
                            {{ $apartado->fecha_limite->format('d/m/Y') }}
                            @if($apartado->estaVencido)
                                <span class="text-xs ml-2 px-2 py-1 bg-red-100 text-red-800 rounded-full">Vencido</span>
                            @endif
                        </p>
                    </div>
                    @endif

                    <div>
                        <p class="text-sm text-gray-600 mb-1">Cantidad de Libros</p>
                        <p class="text-lg font-semibold">
                            <span class="px-3 py-1 rounded-full text-sm bg-blue-100 text-blue-800">
                                {{ $apartado->detalles->count() }} libro(s)
                            </span>
                        </p>
                    </div>
                </div>
            </x-card>
        </div>

        <!-- Resumen de Totales -->
        <div class="lg:col-span-1">
            <x-card title="Resumen de Totales" class="h-full">
                <div class="flex flex-col justify-between h-full">
                    <div class="space-y-4">
                        <div class="flex justify-between items-center py-3 border-b border-gray-100">
                            <span class="text-gray-600 font-medium">Monto Total:</span>
                            <span class="text-gray-800 font-semibold text-lg">
                                ${{ number_format($apartado->monto_total, 2) }}
                            </span>
                        </div>

                        <div class="flex justify-between items-center py-3 border-b border-gray-100">
                            <span class="text-green-600 font-medium">Total Pagado:</span>
                            <span class="text-green-600 font-semibold text-lg">
                                ${{ number_format($apartado->totalPagado, 2) }}
                            </span>
                        </div>

                        <div class="flex justify-between items-center py-3 bg-primary-50 rounded-lg px-3">
                            <span class="text-gray-800 font-bold">Saldo Pendiente:</span>
                            <span class="text-orange-600 font-bold text-2xl">
                                ${{ number_format($apartado->saldo_pendiente, 2) }}
                            </span>
                        </div>

                        <!-- Barra de progreso -->
                        <div class="bg-blue-50 p-3 rounded-lg">
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-sm text-blue-800 font-medium">Porcentaje Pagado:</span>
                                <span class="text-lg font-bold text-blue-900">{{ $apartado->porcentajePagado }}%</span>
                            </div>
                            <div class="w-full bg-blue-200 rounded-full h-2">
                                <div class="bg-blue-600 h-2 rounded-full transition-all" style="width: {{ $apartado->porcentajePagado }}%"></div>
                            </div>
                        </div>
                    </div>

                    @if($apartado->observaciones)
                        <div class="mt-4 p-3 bg-yellow-50 border-l-4 border-yellow-400 rounded">
                            <p class="text-xs font-medium text-yellow-800 mb-1">
                                <i class="fas fa-sticky-note"></i> Observaciones
                            </p>
                            <p class="text-sm text-gray-700">{{ $apartado->observaciones }}</p>
                        </div>
                    @endif
                </div>
            </x-card>
        </div>
    </div>

    <!-- Libros Apartados -->
    <x-card title="Libros Apartados">
        <div class="space-y-4">
            @foreach($apartado->detalles as $detalle)
                <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition-colors">
                    <div class="flex items-start gap-4">
                        <!-- Icono del libro -->
                        <div class="flex-shrink-0">
                            <div class="w-16 h-20 bg-gradient-to-br from-primary-100 to-primary-200 rounded-lg flex items-center justify-center">
                                <i class="fas fa-book text-primary-600 text-2xl"></i>
                            </div>
                        </div>

                        <!-- Información del libro -->
                        <div class="flex-1 min-w-0">
                            <div class="flex justify-between items-start mb-3">
                                <div class="min-w-0 pr-3">
                                    <h3 class="font-semibold text-gray-900 text-base break-words">
                                        {{ $detalle->libro->nombre }}
                                    </h3>
                                    <p class="text-sm text-gray-600">
                                        Código: <span class="font-mono">{{ $detalle->libro->codigo }}</span>
                                    </p>
                                </div>
                                <a href="{{ route('inventario.show', $detalle->libro) }}" 
                                   class="text-primary-600 hover:text-primary-700"
                                   title="Ver libro">
                                    <i class="fas fa-external-link-alt"></i>
                                </a>
                            </div>

                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <!-- Precio Unitario -->
                                <div>
                                    <p class="text-xs text-gray-500 mb-1">Precio Unitario</p>
                                    <p class="text-sm font-semibold text-gray-900">
                                        ${{ number_format($detalle->precio_unitario, 2) }}
                                    </p>
                                </div>

                                <!-- Cantidad -->
                                <div>
                                    <p class="text-xs text-gray-500 mb-1">Cantidad</p>
                                    <p class="text-sm font-semibold text-gray-900">
                                        {{ $detalle->cantidad }} unidad(es)
                                    </p>
                                </div>

                                <!-- Descuento -->
                                <div>
                                    <p class="text-xs text-gray-500 mb-1">Descuento</p>
                                    <p class="text-sm font-semibold {{ $detalle->descuento > 0 ? 'text-orange-600' : 'text-gray-900' }}">
                                        {{ $detalle->descuento > 0 ? $detalle->descuento . '%' : '0%' }}
                                    </p>
                                </div>

                                <!-- Subtotal -->
                                <div>
                                    <p class="text-xs text-gray-500 mb-1">Subtotal</p>
                                    <p class="text-sm font-bold text-primary-600">
                                        ${{ number_format($detalle->subtotal, 2) }}
                                    </p>
                                </div>
                            </div>

                            @if($detalle->descuento > 0)
                                @php
                                    $precioConDescuento = $detalle->precio_unitario - ($detalle->precio_unitario * $detalle->descuento / 100);
                                @endphp
                                <div class="mt-2 text-xs text-orange-600 bg-orange-50 px-2 py-1 rounded inline-block">
                                    <i class="fas fa-tag"></i>
                                    Precio con descuento: ${{ number_format($precioConDescuento, 2) }}
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </x-card>

    <!-- Historial de Abonos -->
    @if($apartado->abonos->count() > 0)
    <x-card title="Historial de Abonos">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Monto</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Método</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Saldo Anterior</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Saldo Nuevo</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Usuario</th>
                        @if($apartado->estado === 'activo')
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($apartado->abonos()->latest()->get() as $abono)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $loop->iteration }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            {{ $abono->fecha_abono->format('d/m/Y') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-green-600">
                            ${{ number_format($abono->monto, 2) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                <i class="fas fa-{{ $abono->metodo_pago === 'efectivo' ? 'money-bill' : ($abono->metodo_pago === 'tarjeta' ? 'credit-card' : 'exchange-alt') }} mr-1"></i>
                                {{ $abono->getMetodoPagoLabel() }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                            ${{ number_format($abono->saldo_anterior, 2) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                            ${{ number_format($abono->saldo_nuevo, 2) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                            <i class="fas fa-user mr-1"></i> {{ $abono->usuario }}
                        </td>
                        @if($apartado->estado === 'activo')
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            @if($loop->first)
                                <form action="{{ route('apartados.abonos.destroy', $abono) }}" method="POST" class="inline" onsubmit="return confirm('¿Eliminar este abono?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-900" title="Eliminar último abono">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            @endif
                        </td>
                        @endif
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-card>
    @endif

    <!-- Información de fechas y acciones -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <x-card title="Información de Registro">
            <div class="space-y-4">
                <div class="flex items-center justify-between py-3 border-b border-gray-100">
                    <span class="text-gray-600 font-medium">
                        <i class="fas fa-user-circle text-blue-500 mr-2"></i>
                        Registrado por
                    </span>
                    <span class="text-gray-800 font-semibold">
                        {{ $apartado->usuario ?? 'N/A' }}
                    </span>
                </div>
                <div class="flex items-center justify-between py-3 border-b border-gray-100">
                    <span class="text-gray-600 font-medium">
                        <i class="fas fa-calendar-plus text-green-500 mr-2"></i>
                        Fecha de Registro
                    </span>
                    <span class="text-gray-800 font-semibold">
                        {{ $apartado->created_at->format('d/m/Y H:i') }}
                    </span>
                </div>
                <div class="flex items-center justify-between py-3">
                    <span class="text-gray-600 font-medium">
                        <i class="fas fa-clock text-orange-500 mr-2"></i>
                        Última Actualización
                    </span>
                    <span class="text-gray-800 font-semibold">
                        {{ $apartado->updated_at->format('d/m/Y H:i') }}
                    </span>
                </div>
            </div>
        </x-card>

        <!-- Acciones -->
        <x-card title="Acciones">
            <div class="space-y-3">
                @if($apartado->estado === 'activo')
                    @if($apartado->saldo_pendiente > 0)
                        <x-button 
                            href="{{ route('apartados.abonos.create', $apartado) }}" 
                            variant="success" 
                            icon="fas fa-dollar-sign"
                            class="w-full justify-center">
                            Registrar Abono
                        </x-button>
                    @else
                        <form action="{{ route('apartados.liquidar', $apartado) }}" method="POST">
                            @csrf
                            @method('PUT')
                            <x-button 
                                type="submit" 
                                variant="success" 
                                icon="fas fa-check-circle"
                                onclick="return confirm('¿Liquidar este apartado y crear la venta?')"
                                class="w-full justify-center">
                                Liquidar Apartado
                            </x-button>
                        </form>
                    @endif
                    
                    <form action="{{ route('apartados.cancelar', $apartado) }}" method="POST">
                        @csrf
                        @method('PUT')
                        <x-button 
                            type="submit" 
                            variant="danger" 
                            icon="fas fa-times-circle"
                            onclick="return confirm('¿Está seguro de cancelar este apartado?')"
                            class="w-full justify-center">
                            Cancelar Apartado
                        </x-button>
                    </form>
                @endif

                @if($apartado->estado === 'liquidado' && $apartado->venta_id)
                    <x-button 
                        href="{{ route('ventas.show', $apartado->venta_id) }}" 
                        variant="info" 
                        icon="fas fa-receipt"
                        class="w-full justify-center">
                        Ver Venta Generada
                    </x-button>
                @endif
                
                <x-button 
                    variant="secondary" 
                    icon="fas fa-arrow-left" 
                    onclick="window.location='{{ route('apartados.index') }}'" 
                    class="w-full justify-center">
                    Volver al Listado
                </x-button>
            </div>
        </x-card>
    </div>
</x-page-layout>
@endsection
