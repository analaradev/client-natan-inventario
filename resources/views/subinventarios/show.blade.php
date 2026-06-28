@extends('layouts.app')

@section('title', 'Detalle del Sub-Inventario')

@section('content')
<x-page-layout 
    title="{{ $subinventario->descripcion ?: 'Sub-Inventario #' . $subinventario->id }}"
    description="Detalle del sub-inventario"
    button-text="Volver a Sub-Inventarios"
    button-icon="fas fa-arrow-left"
    :button-route="route('subinventarios.index')"
>
    @php
        $totalLibros = $subinventario->getTotalLibros();
        $totalUnidades = $subinventario->getTotalUnidades();
        $valorDisponible = $subinventario->libros->sum(fn ($libro) => (float) $libro->precio * (int) $libro->pivot->cantidad);
    @endphp

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <x-stat-card 
            icon="fas fa-book"
            label="Títulos"
            :value="$totalLibros"
            bg-color="bg-gray-800"
            icon-color="text-white"
        />

        <x-stat-card 
            icon="fas fa-boxes"
            label="Unidades Disponibles"
            :value="$totalUnidades"
            bg-color="bg-green-100"
            icon-color="text-green-600"
        />

        <x-stat-card 
            icon="fas fa-dollar-sign"
            label="Valor Estimado"
            :value="'$' . number_format($valorDisponible, 2)"
            bg-color="bg-purple-100"
            icon-color="text-purple-600"
        />
    </div>

    <!-- Libros en Sub-Inventario -->
    <x-card class="mb-6">
        @if($libros->count() > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Libro</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Código</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Precio</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cantidades en Sub-Inventario</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Stock Actual</th>
                            @if($subinventario->estado === 'activo')
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Acciones</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($libros as $libro)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-700">
                                    {{ $libro->id }}
                                </td>
                                <td class="px-6 py-4 max-w-md whitespace-normal break-words">
                                    <div class="text-sm font-medium text-gray-900 break-words">{{ $libro->nombre }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $libro->codigo_barras }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    ${{ number_format($libro->precio, 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @php
                                        $reservado = (int) \DB::table('apartado_detalles as ad')
                                            ->join('apartados as a', 'a.id', '=', 'ad.apartado_id')
                                            ->where('ad.libro_id', $libro->id)
                                            ->where('a.subinventario_id', $subinventario->id)
                                            ->where('a.tipo_inventario', 'subinventario')
                                            ->where('a.estado', 'activo')
                                            ->sum('ad.cantidad');
                                    @endphp
                                    <div class="flex flex-col gap-1 text-xs">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full font-medium bg-blue-100 text-blue-800" style="width: max-content; max-width: 100%;">
                                            Disponible: {{ $libro->pivot->cantidad }} uds
                                        </span>
                                        @if($reservado > 0)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full font-medium bg-yellow-100 text-yellow-800" style="width: max-content; max-width: 100%;">
                                                Apartado: {{ $reservado }} uds
                                            </span>
                                            <span class="text-gray-400 font-medium pl-1">
                                                Total Físico: {{ $libro->pivot->cantidad + $reservado }} uds
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $libro->stock }} 
                                    @if($libro->stock_subinventario > 0)
                                        <span class="text-xs text-gray-400">({{ $libro->stock_subinventario }} en sub-inventarios)</span>
                                    @endif
                                </td>
                                @if($subinventario->estado === 'activo')
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <button onclick="devolverLibro({{ $libro->id }}, '{{ $libro->nombre }}', {{ $libro->pivot->cantidad }})"
                                                class="text-orange-600 hover:text-orange-900"
                                                title="Devolver al inventario general">
                                            <i class="fas fa-undo mr-1"></i>Devolver
                                        </button>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            @if($libros->hasPages())
                <div class="mt-4 px-6 py-4 border-t border-gray-200">
                    {{ $libros->links() }}
                </div>
            @endif
        @else
            <div class="text-center py-8">
                <i class="fas fa-inbox text-gray-400 text-5xl mb-4"></i>
                <p class="text-gray-500 text-lg">No hay libros en este sub-inventario</p>
            </div>
        @endif
    </x-card>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Datos del sub-inventario -->
        <x-card title="Datos del Sub-Inventario">
            <div class="flex justify-between items-start gap-4">
                <div class="space-y-2 text-sm text-gray-600">
                    <p><i class="fas fa-calendar mr-2"></i><strong>Fecha:</strong> {{ $subinventario->fecha_subinventario->format('d/m/Y') }}</p>
                    <p><i class="fas fa-user mr-2"></i><strong>Usuario:</strong> {{ $subinventario->usuario }}</p>
                    <p><i class="fas fa-clock mr-2"></i><strong>Creado:</strong> {{ $subinventario->created_at->format('d/m/Y H:i') }}</p>
                </div>

                <span class="px-3 py-2 inline-flex text-sm leading-5 font-semibold rounded-full {{ $subinventario->getBadgeColor() }}">
                    <i class="{{ $subinventario->getIcon() }} mr-2"></i>
                    {{ $subinventario->getEstadoLabel() }}
                </span>
            </div>

            @if($subinventario->observaciones)
                <div class="bg-gray-50 p-4 rounded-lg mt-4">
                    <p class="text-sm text-gray-700">
                        <i class="fas fa-comment mr-2 text-gray-500"></i>
                        <strong>Observaciones:</strong> {{ $subinventario->observaciones }}
                    </p>
                </div>
            @endif
        </x-card>

        <!-- Acciones -->
        <x-card title="Acciones">
            <div class="space-y-3">
                @if($subinventario->estado === 'activo')
                    <x-button variant="warning" icon="fas fa-edit" href="{{ route('subinventarios.edit', $subinventario) }}" class="w-full justify-center">
                        Editar
                    </x-button>

                    <x-button 
                        type="button" 
                        variant="primary" 
                        icon="fas fa-plus-circle"
                        onclick="window.location='{{ route('subinventarios.import-form', $subinventario) }}'"
                        class="w-full justify-center"
                    >
                        Importar Libros
                    </x-button>
                @endif

                <x-button 
                    type="button" 
                    variant="success" 
                    icon="fas fa-file-excel"
                    onclick="window.location='{{ route('subinventarios.libros.export.excel', $subinventario) }}'"
                    class="w-full justify-center"
                >
                    Exportar Excel
                </x-button>
                
                <x-button 
                    type="button" 
                    variant="danger" 
                    icon="fas fa-file-pdf"
                    onclick="window.location='{{ route('subinventarios.libros.export.pdf', $subinventario) }}'"
                    class="w-full justify-center"
                >
                    Exportar PDF
                </x-button>
            </div>
        </x-card>
    </div>
</x-page-layout>

@push('scripts')
<script>
    function devolverLibro(libroId, libroNombre, cantidad) {
        if (confirm(`¿Devolver todo el stock de "${libroNombre}" (${cantidad} unidades) al inventario general? La cantidad se pondrá a 0.`)) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = "{{ route('subinventarios.devolver-parcial', $subinventario) }}";
            form.innerHTML = `
                @csrf
                <input type="hidden" name="libro_id" value="${libroId}">
                <input type="hidden" name="cantidad" value="${cantidad}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }
</script>
@endpush
@endsection
