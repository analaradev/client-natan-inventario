@props([
    'viewRoute' => null,
    'editRoute' => null,
    'deleteRoute' => null,
    'deleteMessage' => '¿Estás seguro de eliminar este registro?',
    'requireAdmin' => false,
])

@php
    $isAdminLibreria = \App\Helpers\AuthHelper::isAdminLibreria();
    $canModify = !$requireAdmin || $isAdminLibreria;
@endphp

<td class="px-6 py-4 text-sm">
    <div class="flex flex-wrap justify-end gap-1">
        @if($viewRoute)
            <x-button 
                href="{{ $viewRoute }}" 
                variant="primary" 
                size="sm"
                icon="fas fa-eye"
                title="Ver detalles">
            </x-button>
        @endif
        
        @if($editRoute)
            @if($canModify)
                <x-button 
                    href="{{ $editRoute }}" 
                    variant="warning" 
                    size="sm"
                    icon="fas fa-edit"
                    title="Editar">
                </x-button>
            @else
                <button 
                    type="button"
                    disabled
                    class="inline-flex items-center justify-center px-2 py-1 text-xs font-medium rounded cursor-not-allowed bg-gray-200 text-gray-400 opacity-60"
                    title="Solo Admin Librería">
                    <i class="fas fa-edit"></i>
                </button>
            @endif
        @endif
        
        @if($deleteRoute)
            @if($canModify)
                <form action="{{ $deleteRoute }}" method="POST" class="inline">
                    @csrf
                    @method('DELETE')
                    <x-button 
                        type="submit" 
                        variant="danger" 
                        size="sm"
                        icon="fas fa-trash"
                        title="Eliminar"
                        onclick="return confirm('{{ $deleteMessage }}')">
                    </x-button>
                </form>
            @else
                <button 
                    type="button"
                    disabled
                    class="inline-flex items-center justify-center px-2 py-1 text-xs font-medium rounded cursor-not-allowed bg-gray-200 text-gray-400 opacity-60"
                    title="Solo Admin Librería">
                    <i class="fas fa-trash"></i>
                </button>
            @endif
        @endif

        {{ $slot }}
    </div>
</td>
