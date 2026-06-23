@extends('layouts.app')

@section('title', 'Detalle del Sub-Inventario')

@section('content')
<x-page-layout 
    title="Sub-Inventario #{{ $subinventario->id }}"
    description="Detalle del sub-inventario"
    button-text="Volver a Sub-Inventarios"
    button-icon="fas fa-arrow-left"
    :button-route="route('subinventarios.index')"
>
    <!-- Información del sub-inventario -->
    <x-card class="mb-6">
        <div class="flex justify-between items-start mb-6">
            <div>
                <h3 class="text-xl font-bold text-gray-900 mb-2">
                    {{ $subinventario->descripcion ?: 'Sub-Inventario #' . $subinventario->id }}
                </h3>
                <div class="space-y-1 text-sm text-gray-600">
                    <p><i class="fas fa-calendar mr-2"></i><strong>Fecha:</strong> {{ $subinventario->fecha_subinventario->format('d/m/Y') }}</p>
                    <p><i class="fas fa-user mr-2"></i><strong>Usuario:</strong> {{ $subinventario->usuario }}</p>
                    <p><i class="fas fa-clock mr-2"></i><strong>Creado:</strong> {{ $subinventario->created_at->format('d/m/Y H:i') }}</p>
                </div>
            </div>
            
            <span class="px-3 py-2 inline-flex text-sm leading-5 font-semibold rounded-full {{ $subinventario->getBadgeColor() }}">
                <i class="{{ $subinventario->getIcon() }} mr-2"></i>
                {{ $subinventario->getEstadoLabel() }}
            </span>
        </div>

        @if($subinventario->observaciones)
            <div class="bg-gray-50 p-4 rounded-lg">
                <p class="text-sm text-gray-700">
                    <i class="fas fa-comment mr-2 text-gray-500"></i>
                    <strong>Observaciones:</strong> {{ $subinventario->observaciones }}
                </p>
            </div>
        @endif
    </x-card>

    <!-- Libros en Sub-Inventario -->
    <x-card class="mb-6">
        <div class="mb-4 flex justify-between items-center">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">
                    <i class="fas fa-book mr-2 text-blue-600"></i>Libros en Sub-Inventario
                </h3>
                <p class="text-sm text-gray-600 mt-1">
                    Total: {{ $subinventario->getTotalLibros() }} libros - {{ $subinventario->getTotalUnidades() }} unidades
                </p>
            </div>
            
            <!-- Botones de exportación e importación -->
            <div class="flex gap-3">
                @if($subinventario->estado === 'activo')
                    <x-button 
                        type="button" 
                        variant="primary" 
                        icon="fas fa-plus-circle"
                        onclick="window.location='{{ route('subinventarios.import-form', $subinventario) }}'"
                    >
                        Importar Libros
                    </x-button>
                @endif

                <x-button 
                    type="button" 
                    variant="success" 
                    icon="fas fa-file-excel"
                    onclick="window.location='{{ route('subinventarios.libros.export.excel', $subinventario) }}'"
                >
                    Exportar Excel
                </x-button>
                
                <x-button 
                    type="button" 
                    variant="danger" 
                    icon="fas fa-file-pdf"
                    onclick="window.location='{{ route('subinventarios.libros.export.pdf', $subinventario) }}'"
                >
                    Exportar PDF
                </x-button>
            </div>
        </div>

        @if($libros->count() > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Libro</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Código</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Precio</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cantidad en Sub-Inventario</th>
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
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-gray-900">{{ $libro->nombre }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $libro->codigo_barras }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    ${{ number_format($libro->precio, 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        {{ $libro->pivot->cantidad }} unidades
                                    </span>
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

    <!-- Acciones -->
    @if($subinventario->estado === 'activo')
        <x-card title="Acciones" class="lg:w-1/2 lg:ml-auto">
            <div class="space-y-3">
                <x-button variant="warning" icon="fas fa-edit" href="{{ route('subinventarios.edit', $subinventario) }}" class="w-full justify-center">
                    Editar
                </x-button>
            </div>
        </x-card>
    @endif
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
