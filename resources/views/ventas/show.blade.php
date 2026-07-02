@extends('layouts.app')

@section('title', 'Detalle de Venta')

@section('page-title', 'Detalle de Venta')
@section('page-description', 'Información completa de la venta')

@section('content')
@php
    $isAdminLibreria = \App\Helpers\AuthHelper::isAdminLibreria();
@endphp

<x-page-layout 
    title="Detalle de Venta"
    :description="'Venta #' . $venta->id"
    button-text="Volver a Ventas"
    button-icon="fas fa-arrow-left"
    :button-route="route('ventas.index')"
>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Información principal -->
        <div class="lg:col-span-2">
            <x-card title="Información de la Venta" class="h-full">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <p class="text-sm text-gray-600 mb-1">ID de Venta</p>
                        <div class="flex items-center gap-2">
                            <p class="text-lg font-mono font-bold text-gray-800">#{{ $venta->id }}</p>
                            @if($venta->esApartado())
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                    <i class="fas fa-box-open mr-1"></i>
                                    Apartado #{{ $venta->apartado->id }}
                                </span>
                            @endif
                        </div>
                    </div>

                    <div>
                        <p class="text-sm text-gray-600 mb-1">Estado</p>
                        <p class="text-lg font-semibold">
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $venta->getEstadoUnificadoBadgeColor() }}">
                                <i class="{{ $venta->getEstadoUnificadoIcon() }} mr-1"></i>
                                {{ $venta->getEstadoUnificadoLabel() }}
                            </span>
                        </p>
                    </div>

                    <div>
                        <p class="text-sm text-gray-600 mb-1">Cliente / Referencia</p>
                        @if($venta->cliente)
                            <p class="text-lg font-semibold text-gray-800">
                                <i class="fas fa-user text-primary-600 mr-2"></i>
                                {{ $venta->cliente->nombre }}
                            </p>
                            @if($venta->cliente->telefono)
                                <p class="text-sm text-gray-600 mt-1">
                                    <i class="fas fa-phone mr-1"></i>
                                    {{ $venta->cliente->telefono }}
                                </p>
                            @endif
                            @if($venta->cliente_referencia)
                                <p class="text-sm text-gray-600 mt-1">
                                    <i class="fas fa-user-tag mr-1"></i>
                                    Referencia: {{ $venta->cliente_referencia }}
                                </p>
                            @endif
                        @elseif($venta->cliente_referencia)
                            <p class="text-lg font-semibold text-gray-800">
                                <i class="fas fa-user-tag text-primary-600 mr-2"></i>
                                {{ $venta->cliente_referencia }}
                            </p>
                            <p class="text-sm text-gray-500 mt-1">Referencia de texto</p>
                        @else
                            <p class="text-lg font-semibold text-gray-400 italic">
                                Sin cliente asignado
                            </p>
                        @endif
                    </div>

                    <div>
                        <p class="text-sm text-gray-600 mb-1">Fecha de Venta</p>
                        <p class="text-lg font-semibold text-gray-800">{{ $venta->fecha_venta->format('d/m/Y') }}</p>
                    </div>

                    <div>
                        <p class="text-sm text-gray-600 mb-1">Metodo de Pago</p>
                        <p class="text-lg font-semibold text-gray-800">
                            <i class="fas fa-wallet text-gray-400 mr-1"></i>
                            {{ \App\Models\IngresoCaja::metodosPago()[$venta->metodo_pago] ?? ucfirst(str_replace('_', ' ', $venta->metodo_pago ?? 'no_especificado')) }}
                        </p>
                    </div>

                    <div>
                        <p class="text-sm text-gray-600 mb-1">Cantidad de Libros</p>
                        <p class="text-lg font-semibold">
                            <span class="px-3 py-1 rounded-full text-sm bg-blue-100 text-blue-800">
                                {{ $venta->movimientos->count() }} libro(s)
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
                            <span class="text-gray-600 font-medium">Subtotal:</span>
                            <span class="text-gray-800 font-semibold text-lg">
                                ${{ number_format($venta->subtotal, 2) }}
                            </span>
                        </div>

                        @if($venta->descuento_global > 0)
                            <div class="flex justify-between items-center py-3 border-b border-gray-100">
                                <span class="text-orange-600 font-medium">
                                    Descuento ({{ $venta->descuento_global }}%):
                                </span>
                                <span class="text-orange-600 font-semibold text-lg">
                                    -${{ number_format(($venta->subtotal * $venta->descuento_global / 100), 2) }}
                                </span>
                            </div>
                        @endif

                        @if($venta->tiene_envio && $venta->costo_envio > 0)
                            <div class="flex justify-between items-center py-3 border-b border-gray-100">
                                <span class="text-blue-600 font-medium">
                                    <i class="fas fa-shipping-fast mr-1"></i> Costo de Envío:
                                </span>
                                <span class="text-blue-600 font-semibold text-lg">
                                    +${{ number_format($venta->costo_envio, 2) }}
                                </span>
                            </div>
                        @endif

                        <div class="flex justify-between items-center py-3 bg-primary-50 rounded-lg px-3">
                            <span class="text-gray-800 font-bold">Total:</span>
                            <span class="text-primary-600 font-bold text-2xl">
                                ${{ number_format($venta->total, 2) }}
                            </span>
                        </div>
                    </div>

                    @if($venta->observaciones)
                        <div class="mt-4 p-3 bg-yellow-50 border-l-4 border-yellow-400 rounded">
                            <p class="text-xs font-medium text-yellow-800 mb-1">
                                <i class="fas fa-sticky-note"></i> Observaciones
                            </p>
                            <p class="text-sm text-gray-700">{{ $venta->observaciones }}</p>
                        </div>
                    @endif
                </div>
            </x-card>
        </div>
    </div>

    <!-- Libros de la Venta -->
    <x-card title="Libros de la Venta">
        <div class="space-y-4">
            @foreach($venta->movimientos as $movimiento)
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
                                    @if($movimiento->libro)
                                        <h3 class="font-semibold text-gray-900 text-base break-words">
                                            {{ $movimiento->libro->nombre }}
                                        </h3>
                                        <p class="text-sm text-gray-600">
                                            Código: <span class="font-mono">{{ $movimiento->libro->codigo_barras }}</span>
                                        </p>
                                    @else
                                        <h3 class="font-semibold text-red-600 text-base">
                                            (Libro eliminado)
                                        </h3>
                                        <p class="text-sm text-gray-500">
                                            ID del libro: {{ $movimiento->libro_id }}
                                        </p>
                                    @endif
                                </div>
                                @if($movimiento->libro)
                                    <a href="{{ route('inventario.show', $movimiento->libro) }}" 
                                       class="text-primary-600 hover:text-primary-700"
                                       title="Ver libro">
                                        <i class="fas fa-external-link-alt"></i>
                                    </a>
                                @endif
                            </div>

                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <!-- Precio Unitario -->
                                <div>
                                    <p class="text-xs text-gray-500 mb-1">Precio Unitario</p>
                                    <p class="text-sm font-semibold text-gray-900">
                                        ${{ number_format($movimiento->precio_unitario, 2) }}
                                    </p>
                                </div>

                                <!-- Cantidad -->
                                <div>
                                    <p class="text-xs text-gray-500 mb-1">Cantidad</p>
                                    <p class="text-sm font-semibold text-gray-900">
                                        {{ $movimiento->cantidad }} unidad(es)
                                    </p>
                                </div>

                                <!-- Descuento -->
                                <div>
                                    <p class="text-xs text-gray-500 mb-1">Descuento</p>
                                    <p class="text-sm font-semibold {{ $movimiento->descuento > 0 ? 'text-orange-600' : 'text-gray-900' }}">
                                        {{ $movimiento->descuento > 0 ? $movimiento->descuento . '%' : '0%' }}
                                    </p>
                                </div>

                                <!-- Subtotal -->
                                <div>
                                    <p class="text-xs text-gray-500 mb-1">Subtotal</p>
                                    <p class="text-sm font-bold text-primary-600">
                                        @php
                                            $precioConDescuento = $movimiento->precio_unitario;
                                            if ($movimiento->descuento) {
                                                $precioConDescuento -= ($movimiento->precio_unitario * $movimiento->descuento / 100);
                                            }
                                            $subtotal = $precioConDescuento * $movimiento->cantidad;
                                        @endphp
                                        ${{ number_format($subtotal, 2) }}
                                    </p>
                                </div>
                            </div>

                            @if($movimiento->descuento > 0)
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

    @if($venta->pagos->count() > 0)
        <x-card title="Historial de Pagos">
            <div class="space-y-3">
                @foreach($venta->pagos->sortByDesc('fecha_pago') as $pago)
                    <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition-colors">
                        <div class="flex items-start gap-4">
                            <div class="flex-shrink-0">
                                <div class="w-14 h-14 bg-green-100 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-dollar-sign text-green-600 text-lg"></i>
                                </div>
                            </div>

                            <div class="flex-1 min-w-0">
                                <div class="flex justify-between items-start mb-3">
                                    <div class="min-w-0 pr-3">
                                        <h3 class="text-lg font-bold text-green-600">
                                            ${{ number_format($pago->monto, 2) }}
                                        </h3>
                                        <p class="text-sm text-gray-500">
                                            Pago registrado el {{ \Carbon\Carbon::parse($pago->fecha_pago)->format('d/m/Y') }}
                                        </p>
                                    </div>

                                    @if($isAdminLibreria && $venta->estado !== 'cancelada')
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
                                    @endif
                                </div>

                                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                    <div>
                                        <p class="text-xs text-gray-500 mb-1">Fecha</p>
                                        <p class="text-sm font-semibold text-gray-900">
                                            <i class="fas fa-calendar text-gray-400 mr-1"></i>
                                            {{ \Carbon\Carbon::parse($pago->fecha_pago)->format('d/m/Y') }}
                                        </p>
                                    </div>

                                    <div>
                                        <p class="text-xs text-gray-500 mb-1">Método</p>
                                        <p class="text-sm font-semibold text-gray-800 capitalize">
                                            <i class="fas fa-credit-card text-gray-400 mr-1"></i>
                                            {{ $pago->metodo_pago }}
                                        </p>
                                    </div>

                                    <div>
                                        <p class="text-xs text-gray-500 mb-1">Comprobante</p>
                                        <p class="text-sm font-semibold text-gray-800 font-mono break-words">
                                            {{ $pago->comprobante ?: 'N/A' }}
                                        </p>
                                    </div>

                                    <div>
                                        <p class="text-xs text-gray-500 mb-1">Registrado</p>
                                        <p class="text-sm text-gray-700">
                                            {{ $pago->created_at->format('d/m/Y H:i') }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
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
                        {{ $venta->usuario ?? 'N/A' }}
                    </span>
                </div>
                
                <div class="flex items-center justify-between py-3 border-b border-gray-100">
                    <span class="text-gray-600 font-medium">
                        <i class="fas fa-warehouse text-purple-500 mr-2"></i>
                        Origen de Inventario
                    </span>
                    <span class="text-gray-800 font-semibold">
                        @if($venta->tipo_inventario === 'subinventario' && $venta->subinventario)
                            <span class="px-2 py-1 bg-purple-100 text-purple-800 rounded text-sm">
                                <i class="fas fa-store mr-1"></i>
                                {{ $venta->subinventario->descripcion ?: 'Sub-Inv #' . $venta->subinventario_id }}
                            </span>
                        @else
                            <span class="px-2 py-1 bg-gray-100 text-gray-800 rounded text-sm">
                                <i class="fas fa-boxes mr-1"></i>
                                Inventario General
                            </span>
                        @endif
                    </span>
                </div>
                
                <div class="flex items-center justify-between py-3 border-b border-gray-100">
                    <span class="text-gray-600 font-medium">
                        <i class="fas fa-calendar-plus text-green-500 mr-2"></i>
                        Fecha de Registro
                    </span>
                    <span class="text-gray-800 font-semibold">
                        {{ $venta->created_at->format('d/m/Y H:i') }}
                    </span>
                </div>
                <div class="flex items-center justify-between py-3">
                    <span class="text-gray-600 font-medium">
                        <i class="fas fa-clock text-orange-500 mr-2"></i>
                        Última Actualización
                    </span>
                    <span class="text-gray-800 font-semibold">
                        {{ $venta->updated_at->format('d/m/Y H:i') }}
                    </span>
                </div>
            </div>
        </x-card>

        <!-- Acciones -->
        <x-card title="Acciones">
            <div class="space-y-3">
                
                @if($isAdminLibreria)
                    <x-button 
                        href="{{ route('ventas.edit', $venta) }}" 
                        variant="warning" 
                        icon="fas fa-edit"
                        class="w-full justify-center">
                        Editar Venta
                    </x-button>
                @else
                    <button 
                        disabled
                        class="w-full inline-flex items-center justify-center px-4 py-2 rounded-lg font-medium text-sm cursor-not-allowed bg-gray-200 text-gray-400 opacity-60"
                    >
                        <i class="fas fa-edit mr-2"></i>
                        Editar Venta
                    </button>
                @endif
                
                @if($venta->estado === 'completada')
                    @if($isAdminLibreria)
                        <form action="{{ route('ventas.cancelar', $venta) }}" method="POST">
                            @csrf
                            <x-button 
                                type="submit" 
                                variant="warning" 
                                icon="fas fa-ban"
                                onclick="return confirm('¿Estás seguro de cancelar esta venta? Se restaurará el stock de los libros.')"
                                class="w-full justify-center">
                                Cancelar Venta
                            </x-button>
                        </form>
                    @else
                        <button 
                            disabled
                            class="w-full inline-flex items-center justify-center px-4 py-2 rounded-lg font-medium text-sm cursor-not-allowed bg-gray-200 text-gray-400 opacity-60"
                        >
                            <i class="fas fa-ban mr-2"></i>
                            Cancelar Venta
                        </button>
                    @endif
                @endif
                
                <x-button variant="secondary" icon="fas fa-arrow-left" onclick="window.location='{{ route('ventas.index') }}'" class="w-full justify-center">
                    Volver al Listado
                </x-button>
                
                @if($venta->estado !== 'completada')
                    @if($isAdminLibreria)
                        <form action="{{ route('ventas.destroy', $venta) }}" method="POST">
                            @csrf
                            @method('DELETE')
                            <x-button type="submit" variant="danger" icon="fas fa-trash" onclick="return confirm('¿Estás seguro de eliminar esta venta?')" class="w-full justify-center">
                                Eliminar
                            </x-button>
                        </form>
                    @else
                        <button 
                            disabled
                            class="w-full inline-flex items-center justify-center px-4 py-2 rounded-lg font-medium text-sm cursor-not-allowed bg-gray-200 text-gray-400 opacity-60"
                        >
                            <i class="fas fa-trash mr-2"></i>
                            Eliminar
                        </button>
                    @endif
                @endif
            </div>
        </x-card>
    </div>
</x-page-layout>
@endsection
