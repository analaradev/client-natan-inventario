@extends('layouts.app')

@section('title', 'Apartados')

@section('content')
@php
    $isAdminLibreria = \App\Helpers\AuthHelper::isAdminLibreria();
    $canManageSalesOperations = \App\Helpers\AuthHelper::canManageSalesOperations();
@endphp

<x-page-layout 
    title="Listado de Apartados"
    description="Total: {{ $apartados->total() }} apartados"
>
    <x-slot name="header">
        @if($canManageSalesOperations)
            <x-button 
                variant="primary" 
                icon="fas fa-plus"
                onclick="window.location='{{ route('apartados.create') }}'"
            >
                Nuevo Apartado
            </x-button>
        @else
            <button 
                disabled
                class="inline-flex items-center px-4 py-2 rounded-lg font-medium text-sm cursor-not-allowed bg-gray-200 text-gray-400 opacity-60"
            >
                <i class="fas fa-plus mr-2"></i>
                Nuevo Apartado
            </button>
        @endif
    </x-slot>

    <!-- Estadísticas -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem;">
        <x-stat-card 
            icon="fas fa-handshake"
            label="Total Apartados"
            :value="$estadisticas['total_apartados']"
            bg-color="bg-blue-100"
            icon-color="text-blue-600"
        />

        <x-stat-card 
            icon="fas fa-clock"
            label="Activos"
            :value="$estadisticas['activos']"
            bg-color="bg-yellow-100"
            icon-color="text-yellow-600"
        />

        <x-stat-card 
            icon="fas fa-check-circle"
            label="Liquidados"
            :value="$estadisticas['liquidados']"
            bg-color="bg-green-100"
            icon-color="text-green-600"
        />

        <x-stat-card 
            icon="fas fa-dollar-sign"
            label="Total Apartado"
            :value="'$' . number_format($estadisticas['total_apartado'], 2)"
            bg-color="bg-purple-100"
            icon-color="text-purple-600"
        />

        <x-stat-card 
            icon="fas fa-exclamation-circle"
            label="Saldo Pendiente"
            :value="'$' . number_format($estadisticas['saldo_pendiente_total'], 2)"
            bg-color="bg-orange-100"
            icon-color="text-orange-600"
        />

        @if($estadisticas['vencidos'] > 0)
        <x-stat-card 
            icon="fas fa-hourglass-end"
            label="Vencidos"
            :value="$estadisticas['vencidos']"
            bg-color="bg-red-100"
            icon-color="text-red-600"
        />
        @endif
    </div>

    <!-- Filtros -->
    <x-card class="overflow-visible">
        <form method="GET" action="{{ route('apartados.index') }}" class="overflow-visible">
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-[minmax(260px,1.5fr)_minmax(200px,1fr)_minmax(160px,0.75fr)_minmax(160px,0.75fr)] gap-4 mb-4">
                <div class="min-w-0">
                    @php
                        $selectedClienteId = request('cliente_id');
                        $selectedCliente = $selectedClienteId
                            ? ($clientes->firstWhere('id', (int) $selectedClienteId) ?? \App\Models\Cliente::find($selectedClienteId))
                            : null;
                    @endphp
                    <x-cliente-search-dynamic
                        name="cliente_id"
                        :selected="$selectedClienteId"
                        :clienteData="$selectedCliente"
                        label="Cliente"
                        :required="false"
                        placeholder="Buscar cliente..."
                        :showCreateOption="false"
                    />
                </div>

                <div class="min-w-0">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-list-check text-gray-400"></i> Estado
                    </label>
                    <select name="estado" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <option value="">Todos los estados</option>
                        <option value="activo" {{ request('estado') == 'activo' ? 'selected' : '' }}>Activo</option>
                        <option value="liquidado" {{ request('estado') == 'liquidado' ? 'selected' : '' }}>Liquidado</option>
                        <option value="cancelado" {{ request('estado') == 'cancelado' ? 'selected' : '' }}>Cancelado</option>
                    </select>
                </div>

                <div class="min-w-0">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-calendar-alt text-gray-400"></i> Fecha Desde
                    </label>
                    <input type="date" name="fecha_desde" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" value="{{ request('fecha_desde') }}">
                </div>

                <div class="min-w-0">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-calendar-check text-gray-400"></i> Fecha Hasta
                    </label>
                    <input type="date" name="fecha_hasta" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" value="{{ request('fecha_hasta') }}">
                </div>
            </div>

            <div class="flex flex-wrap justify-between items-center gap-3 pt-2">
                <div class="flex gap-3">
                    <x-button type="submit" variant="primary" icon="fas fa-filter">
                        Aplicar Filtros
                    </x-button>
                    @if(request()->hasAny(['cliente_id', 'estado', 'fecha_desde', 'fecha_hasta']))
                        <x-button type="button" variant="secondary" icon="fas fa-times"
                                  onclick="window.location='{{ route('apartados.index') }}'">
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
                        onclick="window.location='{{ route('apartados.export.excel', request()->query()) }}'"
                    >
                        Exportar Excel
                    </x-button>
                    
                    <x-button 
                        type="button" 
                        variant="danger" 
                        icon="fas fa-file-pdf"
                        onclick="window.location='{{ route('apartados.export.pdf', request()->query()) }}'"
                    >
                        Exportar PDF
                    </x-button>
                </div>
            </div>
        </form>
    </x-card>

    <!-- Tabla de Apartados -->
    <x-card>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID / Folio</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha / Límite</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cliente</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pagado / Saldo</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($apartados as $apartado)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900">#{{ $apartado->id }}</div>
                            <div class="text-xs text-gray-500">{{ $apartado->folio }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900">{{ $apartado->fecha_apartado->format('d/m/Y') }}</div>
                            @if($apartado->fecha_limite)
                                <div class="text-xs {{ $apartado->estaVencido ? 'text-red-600 font-semibold' : 'text-gray-500' }}">
                                    <i class="fas fa-clock mr-1"></i>Vence: {{ $apartado->fecha_limite->format('d/m/Y') }}
                                </div>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900">{{ $apartado->cliente->nombre }}</div>
                            <div class="text-xs text-gray-500">
                                <i class="fas fa-book mr-1"></i>{{ $apartado->detalles->count() }} libro(s)
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900">${{ number_format($apartado->monto_total, 2) }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-green-600 font-medium">
                                <i class="fas fa-check mr-1"></i>${{ number_format($apartado->totalPagado, 2) }}
                            </div>
                            <div class="text-sm text-orange-600">
                                <i class="fas fa-exclamation-circle mr-1"></i>${{ number_format($apartado->saldo_pendiente, 2) }}
                            </div>
                            <div class="text-xs text-gray-500">{{ $apartado->porcentajePagado }}% pagado</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $apartado->getBadgeColor() }}">
                                <i class="{{ $apartado->getIcon() }} mr-1"></i>
                                {{ $apartado->getEstadoLabel() }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <div class="flex justify-start gap-1">
                                <x-button
                                    href="{{ route('apartados.show', $apartado) }}"
                                    variant="primary"
                                    size="sm"
                                    icon="fas fa-eye"
                                    title="Ver detalles">
                                </x-button>
                                @if($apartado->estado === 'activo')
                                    @if($isAdminLibreria)
                                        <x-button
                                            href="{{ route('apartados.abonos.create', $apartado) }}"
                                            variant="success"
                                            size="sm"
                                            icon="fas fa-dollar-sign"
                                            title="Registrar abono">
                                        </x-button>
                                    @else
                                        <button disabled class="inline-flex items-center justify-center px-3 py-1.5 text-sm rounded-lg bg-gray-200 text-gray-400 cursor-not-allowed opacity-60" title="Solo Admin Librería">
                                            <i class="fas fa-dollar-sign"></i>
                                        </button>
                                    @endif
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-6 py-4 text-center text-gray-500">
                            No se encontraron apartados
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Paginación -->
        <div class="mt-4">
            {{ $apartados->links() }}
        </div>
    </x-card>
</x-page-layout>
@endsection

@push('scripts')
<script src="{{ asset('js/cliente-search-dynamic.js') }}"></script>
@endpush
