@extends('layouts.app')

@section('title', 'Usuarios')

@section('content')
@php
    use App\Helpers\AuthHelper;
    $isAdminLibreria = AuthHelper::isAdminLibreria();
@endphp
<x-page-layout 
    title="Gestión de Usuarios"
    description="Administra los usuarios del sistema"
>
    {{-- Botón Nuevo Usuario temporalmente deshabilitado --}}
    {{-- 
    <x-slot name="header">
        <x-button 
            variant="primary" 
            icon="fas fa-user-plus"
            onclick="window.location='{{ route('usuarios.create') }}'"
        >
            Nuevo Usuario
        </x-button>
    </x-slot>
    --}}
    @if($error)
        <x-alert type="danger" :message="$error" />
    @endif

    <!-- Estadísticas rápidas -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <x-stat-card 
            icon="fas fa-users"
            label="Total Congregantes"
            :value="$congregantes->total()"
            bg-color="bg-gray-100"
            icon-color="text-gray-600"
        />

        <x-stat-card 
            icon="fas fa-user-check"
            label="Congregantes Activos"
            :value="$totalActivos"
            bg-color="bg-green-100"
            icon-color="text-green-600"
        />

        <x-stat-card 
            icon="fas fa-user-plus"
            label="Congregantes Nuevos"
            :value="$totalNuevos"
            bg-color="bg-blue-100"
            icon-color="text-blue-600"
        />
    </div>

    <!-- Barra de búsqueda -->
    <x-card>
        <form method="GET" action="{{ route('usuarios.index') }}">
            <div class="grid grid-cols-1 md:grid-cols-[1fr_auto] gap-4 items-end">
                <!-- Campo de búsqueda -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-search text-gray-400"></i> Buscar Congregante
                    </label>
                    <input 
                        type="text" 
                        name="search" 
                        value="{{ request('search') }}"
                        placeholder="Buscar por código/ID o nombre..." 
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    >
                </div>

                <!-- Botones -->
                <div class="flex gap-3">
                    <x-button type="submit" variant="primary" icon="fas fa-search">
                        Buscar
                    </x-button>

                    @if(request('search'))
                        <x-button type="button" variant="secondary" icon="fas fa-times" 
                                  onclick="window.location='{{ route('usuarios.index') }}'">
                            Limpiar
                        </x-button>
                    @endif
                </div>
            </div>

            @if(request('search'))
                <div class="mt-4 pt-4 border-t border-gray-200">
                    <p class="text-sm text-gray-600">
                        <i class="fas fa-info-circle text-blue-500"></i> 
                        Mostrando resultados para: <strong class="text-gray-900">"{{ request('search') }}"</strong>
                    </p>
                </div>
            @endif
        </form>
    </x-card>

    <!-- Tabla de congregantes -->
    <x-card>
        <x-data-table 
            :headers="['Código', 'Nombre Completo', 'Contacto', 'Ciudad', 'Status', 'Email']"
            :rows="$congregantes"
            :showActions="true"
            emptyMessage="No se encontraron congregantes"
            emptyIcon="fas fa-users"
        >
            @foreach($congregantes as $congregante)
                <x-data-table-row>
                    <x-data-table-cell bold>{{ $congregante['CODCONGREGANTE'] }}</x-data-table-cell>
                    <x-data-table-cell>
                        <div class="text-sm font-medium text-gray-900">
                            {{ trim($congregante['NOMBRE']) ?: 'Sin nombre' }}
                        </div>
                        <div class="text-sm text-gray-500">
                            {{ trim($congregante['APELLIDOS']) ?: 'Sin apellidos' }}
                        </div>
                    </x-data-table-cell>
                    <x-data-table-cell>
                        @if(!empty(trim($congregante['CEL'])))
                            <div class="text-sm text-gray-900">
                                <i class="fas fa-phone mr-1"></i> {{ trim($congregante['CEL']) }}
                            </div>
                        @else
                            <div class="text-sm text-gray-400 italic">
                                Sin celular
                            </div>
                        @endif
                        @if(!empty(trim($congregante['TELCASA'])))
                            <div class="text-sm text-gray-500">
                                <i class="fas fa-home mr-1"></i> {{ trim($congregante['TELCASA']) }}
                            </div>
                        @endif
                    </x-data-table-cell>
                    <x-data-table-cell>
                        {{ !empty(trim($congregante['CIUDAD'])) ? trim($congregante['CIUDAD']) : 'Sin ciudad' }}
                    </x-data-table-cell>
                    <x-data-table-cell>
                        @if($congregante['CODSTATUS'] == '1')
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                Nuevo
                            </span>
                        @elseif($congregante['CODSTATUS'] == '2')
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                Activo
                            </span>
                        @elseif($congregante['CODSTATUS'] == '3')
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                Inactivo
                            </span>
                        @else
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                {{ $congregante['CODSTATUS'] ?: 'Sin status' }}
                            </span>
                        @endif
                    </x-data-table-cell>
                    <x-data-table-cell>
                        @if(!empty(trim($congregante['MAIL'])))
                            <span class="text-gray-500">{{ trim($congregante['MAIL']) }}</span>
                        @else
                            <span class="text-gray-400 italic">Sin email</span>
                        @endif
                    </x-data-table-cell>
                    <x-data-table-actions
                        :viewRoute="route('usuarios.show', $congregante['CODCONGREGANTE'])"
                        :editRoute="route('usuarios.edit', $congregante['CODCONGREGANTE'])"
                        :requireAdmin="true"
                    />
                </x-data-table-row>
            @endforeach
        </x-data-table>

        <!-- Paginación -->
        @if($congregantes->hasPages())
            <div class="mt-4 px-6 py-4 border-t border-gray-200">
                {{ $congregantes->appends(request()->query())->links() }}
            </div>
        @endif
    </x-card>
</x-page-layout>
@endsection
