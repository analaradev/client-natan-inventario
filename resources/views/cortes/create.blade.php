@extends('layouts.app')

@section('title', 'Nuevo Corte de Caja')

@section('content')
<x-page-layout
    title="Nuevo Corte de Caja"
    description="Revision y cierre de ingresos del dia"
    button-text="Volver"
    button-icon="fas fa-arrow-left"
    :button-route="route('cortes.index')"
>
    @if($errors->has('error'))
        <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg text-sm text-red-800">
            <i class="fas fa-exclamation-circle mr-2"></i>{{ $errors->first('error') }}
        </div>
    @endif

    <x-card title="Seleccionar ingresos" icon="fas fa-calendar-check" class="mb-6">
        <form method="GET" action="{{ route('cortes.create') }}" class="corte-ingresos-form grid grid-cols-1 md:grid-cols-2 gap-4 items-end">
            <div class="min-w-0">
                <label class="block text-sm font-medium text-gray-700 mb-2">Fecha</label>
                <input type="date" name="fecha_corte" value="{{ $fechaCorte }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg" required>
            </div>
            <div class="min-w-0">
                <label class="block text-sm font-medium text-gray-700 mb-2">Caja de</label>
                <select name="tipo_inventario" id="tipo_inventario_corte" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    <option value="todos" {{ $tipoInventario === 'todos' ? 'selected' : '' }}>Todos</option>
                    <option value="general" {{ $tipoInventario === 'general' ? 'selected' : '' }}>Inventario general</option>
                    <option value="subinventario" {{ $tipoInventario === 'subinventario' ? 'selected' : '' }}>Subinventario</option>
                </select>
            </div>
            <div id="subinventario_wrap" class="min-w-0 {{ $tipoInventario === 'subinventario' ? '' : 'opacity-60' }}">
                <label class="block text-sm font-medium text-gray-700 mb-2">Subinventario</label>
                <select name="subinventario_id" id="subinventario_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg disabled:bg-gray-100 disabled:text-gray-500 disabled:cursor-not-allowed" {{ $tipoInventario === 'subinventario' ? '' : 'disabled' }}>
                    <option value="">Seleccionar</option>
                    @foreach($subinventarios as $subinventario)
                        <option value="{{ $subinventario->id }}" {{ (string) $subinventarioId === (string) $subinventario->id ? 'selected' : '' }}>
                            {{ $subinventario->descripcion ?? ('Subinventario #' . $subinventario->id) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="corte-ingresos-submit flex md:col-span-2 lg:justify-end">
                <x-button type="submit" variant="primary" icon="fas fa-sync-alt" class="w-full lg:w-auto whitespace-nowrap">Actualizar</x-button>
            </div>
        </form>
    </x-card>

    <div class="corte-metodos-grid grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        <x-stat-card label="Efectivo" :value="'$' . number_format($resumen['metodos']['efectivo'], 2)" icon="fas fa-money-bill" bgColor="bg-green-600" />
        <x-stat-card label="Tarjeta" :value="'$' . number_format($resumen['metodos']['tarjeta'], 2)" icon="fas fa-credit-card" bgColor="bg-blue-600" />
        <x-stat-card label="Transferencia" :value="'$' . number_format($resumen['metodos']['transferencia'], 2)" icon="fas fa-exchange-alt" bgColor="bg-purple-600" />
        <x-stat-card label="Sin especificar" :value="'$' . number_format($resumen['metodos']['no_especificado'], 2)" icon="fas fa-question-circle" bgColor="bg-gray-600" />
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
            @include('cortes.partials.ingresos-table', ['ingresos' => $ingresos])
        </div>
        <div>
            <x-card title="Cerrar corte" icon="fas fa-lock">
                <form method="POST" action="{{ route('cortes.store') }}" class="space-y-4" id="corteStoreForm">
                    @csrf
                    <input type="hidden" name="fecha_corte" value="{{ $fechaCorte }}">
                    <input type="hidden" name="tipo_inventario" value="{{ $tipoInventario }}">
                    <input type="hidden" name="subinventario_id" value="{{ $subinventarioId }}">

                    <div class="p-4 bg-gray-50 rounded-lg">
                        <p class="text-sm text-gray-600">Total del sistema</p>
                        <p class="text-2xl font-bold text-gray-900">${{ number_format($resumen['total'], 2) }}</p>
                        <p class="text-xs text-gray-500 mt-1">{{ $resumen['cantidad'] }} ingresos incluidos</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Total contado/reportado</label>
                        <input type="number" name="total_reportado" id="total_reportado" step="0.01" min="0" value="{{ old('total_reportado', number_format($resumen['total'], 2, '.', '')) }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg" required>
                        <p id="total_reportado_error" class="mt-1 text-sm text-red-600 hidden">El total contado o reportado no puede ser negativo.</p>
                        @error('total_reportado')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Observaciones</label>
                        <textarea name="observaciones" rows="4" class="w-full px-3 py-2 border border-gray-300 rounded-lg">{{ old('observaciones') }}</textarea>
                    </div>

                    <x-button type="submit" variant="primary" icon="fas fa-check" class="w-full" :disabled="$ingresos->isEmpty()">
                        Cerrar Corte
                    </x-button>
                </form>
            </x-card>
        </div>
    </div>
</x-page-layout>

<style>
@media (min-width: 1024px) {
    .corte-ingresos-form {
        grid-template-columns: minmax(12rem, 1fr) 12rem minmax(16rem, 1.35fr) auto !important;
    }

    .corte-ingresos-submit {
        grid-column: auto !important;
    }

    .corte-metodos-grid {
        grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const tipoInventario = document.getElementById('tipo_inventario_corte');
    const subinventarioWrap = document.getElementById('subinventario_wrap');
    const subinventarioSelect = document.getElementById('subinventario_id');
    const corteStoreForm = document.getElementById('corteStoreForm');
    const totalReportadoInput = document.getElementById('total_reportado');
    const totalReportadoError = document.getElementById('total_reportado_error');

    function toggleSubinventario() {
        const mostrarSubinventario = tipoInventario && tipoInventario.value === 'subinventario';

        if (subinventarioWrap) {
            subinventarioWrap.classList.toggle('opacity-60', !mostrarSubinventario);
        }

        if (subinventarioSelect) {
            subinventarioSelect.disabled = !mostrarSubinventario;
            if (!mostrarSubinventario) {
                subinventarioSelect.value = '';
            } else {
                const opcionesReales = Array.from(subinventarioSelect.options).filter(option => option.value !== '');
                if (!subinventarioSelect.value && opcionesReales.length === 1) {
                    subinventarioSelect.value = opcionesReales[0].value;
                }
            }
        }
    }

    if (tipoInventario) {
        tipoInventario.addEventListener('change', toggleSubinventario);
        toggleSubinventario();
    }

    function validarTotalReportado() {
        if (!totalReportadoInput || !totalReportadoError) {
            return true;
        }

        const valor = Number(totalReportadoInput.value);
        const esInvalido = totalReportadoInput.value !== '' && valor < 0;

        totalReportadoError.classList.toggle('hidden', !esInvalido);
        totalReportadoInput.classList.toggle('border-red-500', esInvalido);

        return !esInvalido;
    }

    if (totalReportadoInput) {
        totalReportadoInput.addEventListener('input', validarTotalReportado);
    }

    if (corteStoreForm) {
        corteStoreForm.addEventListener('submit', function (event) {
            if (!validarTotalReportado()) {
                event.preventDefault();
                totalReportadoInput.focus();
            }
        });
    }
});
</script>
@endsection
