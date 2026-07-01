@extends('layouts.app')

@section('title', 'Ajuste Masivo')

@section('page-title', 'Ajuste Masivo de Inventario')
@section('page-description', 'Registra varios movimientos con el mismo contexto')

@php
    $context = $context ?? [];
    $subinventariosCollection = collect($subinventarios);
    $soloSubinventarioId = $subinventariosCollection->count() === 1 ? $subinventariosCollection->first()?->id : null;
    $selectedOrigenStock = old('origen_stock', $context['origen_stock'] ?? 'general');
    $selectedSubinventarioId = old('subinventario_id', $context['subinventario_id'] ?? $soloSubinventarioId);
    $selectedTipoMovimiento = old('tipo_movimiento', $context['tipo_movimiento'] ?? null);
    $selectedTipoEntrada = old('tipo_entrada', $context['tipo_entrada'] ?? null);
    $selectedTipoSalida = old('tipo_salida', $context['tipo_salida'] ?? null);
    $selectedFecha = old('fecha', $context['fecha'] ?? date('Y-m-d'));
    $selectedObservaciones = old('observaciones', $context['observaciones'] ?? null);
@endphp

@section('content')
<x-page-layout
    title="Ajuste Masivo"
    description="Captura varios libros y guarda un movimiento por cada línea"
    button-text="Volver a Movimientos"
    button-icon="fas fa-arrow-left"
    :button-route="route('movimientos.index')"
>
    <x-card>
        @if($errors->any())
            <div class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-red-800">
                <div class="flex items-start gap-3">
                    <i class="fas fa-exclamation-triangle mt-0.5 text-red-500"></i>
                    <div>
                        <p class="font-semibold">Revisa el ajuste masivo</p>
                        <ul class="mt-2 list-disc list-inside text-sm space-y-1">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endif

        <div id="stockValidationBanner" class="hidden mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-red-800">
            <div class="flex items-start gap-3">
                <i class="fas fa-exclamation-triangle mt-0.5 text-red-500"></i>
                <div>
                    <p class="font-semibold">No se puede guardar</p>
                    <p class="text-sm" id="stockValidationMessage"></p>
                </div>
            </div>
        </div>

        <form action="{{ route('movimientos.masivo.store') }}" method="POST" id="ajusteMasivoForm">
            @csrf

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="lg:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Tipo de Inventario <span class="text-red-500">*</span>
                    </label>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <label class="relative block cursor-pointer">
                            <input
                                type="radio"
                                name="origen_stock"
                                value="general"
                                {{ $selectedOrigenStock === 'general' ? 'checked' : '' }}
                                class="radio-origen sr-only"
                            >
                            <div class="origen-box p-4 bg-white border-2 border-gray-200 rounded-lg transition-all hover:border-blue-300 hover:shadow-md">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center space-x-3">
                                        <div class="origen-icon flex-shrink-0 w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center transition-colors">
                                            <i class="fas fa-warehouse text-xl text-blue-600"></i>
                                        </div>
                                        <div>
                                            <p class="font-semibold text-gray-900">Inventario General</p>
                                            <p class="text-sm text-gray-500">Bodega principal</p>
                                        </div>
                                    </div>
                                    <div class="origen-check w-6 h-6 border-2 border-gray-300 rounded-full flex items-center justify-center transition-all">
                                        <i class="fas fa-check text-xs text-white opacity-0"></i>
                                    </div>
                                </div>
                            </div>
                        </label>

                        <label class="relative block cursor-pointer">
                            <input
                                type="radio"
                                name="origen_stock"
                                value="subinventario"
                                {{ $selectedOrigenStock === 'subinventario' ? 'checked' : '' }}
                                class="radio-origen sr-only"
                            >
                            <div class="origen-box p-4 bg-white border-2 border-gray-200 rounded-lg transition-all hover:border-green-300 hover:shadow-md">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center space-x-3">
                                        <div class="origen-icon flex-shrink-0 w-12 h-12 bg-green-100 rounded-full flex items-center justify-center transition-colors">
                                            <i class="fas fa-box-open text-xl text-green-600"></i>
                                        </div>
                                        <div>
                                            <p class="font-semibold text-gray-900">Subinventario</p>
                                            <p class="text-sm text-gray-500">Punto de venta asignado</p>
                                        </div>
                                    </div>
                                    <div class="origen-check w-6 h-6 border-2 border-gray-300 rounded-full flex items-center justify-center transition-all">
                                        <i class="fas fa-check text-xs text-white opacity-0"></i>
                                    </div>
                                </div>
                            </div>
                        </label>
                    </div>

                    <style>
                        input[name="origen_stock"][value="general"]:checked ~ .origen-box {
                            border-color: #3B82F6 !important;
                            background-color: #EFF6FF !important;
                        }
                        input[name="origen_stock"][value="general"]:checked ~ .origen-box .origen-icon,
                        input[name="origen_stock"][value="general"]:checked ~ .origen-box .origen-check {
                            background-color: #3B82F6 !important;
                            border-color: #3B82F6 !important;
                        }
                        input[name="origen_stock"][value="general"]:checked ~ .origen-box .origen-icon i,
                        input[name="origen_stock"][value="general"]:checked ~ .origen-box .origen-check i {
                            color: white !important;
                            opacity: 1 !important;
                        }
                        input[name="origen_stock"][value="subinventario"]:checked ~ .origen-box {
                            border-color: #10B981 !important;
                            background-color: #ECFDF5 !important;
                        }
                        input[name="origen_stock"][value="subinventario"]:checked ~ .origen-box .origen-icon,
                        input[name="origen_stock"][value="subinventario"]:checked ~ .origen-box .origen-check {
                            background-color: #10B981 !important;
                            border-color: #10B981 !important;
                        }
                        input[name="origen_stock"][value="subinventario"]:checked ~ .origen-box .origen-icon i,
                        input[name="origen_stock"][value="subinventario"]:checked ~ .origen-box .origen-check i {
                            color: white !important;
                            opacity: 1 !important;
                        }
                    </style>
                </div>

                <div id="subinventarioContainer" class="hidden lg:col-span-2">
                    <label for="subinventario_id" class="block text-sm font-medium text-gray-700 mb-2">
                        Subinventario <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <span class="absolute left-3 top-2.5 text-gray-400">
                            <i class="fas fa-store"></i>
                        </span>
                        <select name="subinventario_id" id="subinventario_id"
                                class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                            <option value="">Selecciona el subinventario</option>
                            @foreach($subinventarios as $subinventario)
                                <option value="{{ $subinventario->id }}" {{ $selectedSubinventarioId == $subinventario->id ? 'selected' : '' }}>
                                    #{{ $subinventario->id }} - {{ $subinventario->descripcion ?? 'Sin descripción' }}
                                    @if($subinventario->fecha_subinventario)
                                        ({{ $subinventario->fecha_subinventario->format('d/m/Y') }})
                                    @endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="lg:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Tipo de Movimiento <span class="text-red-500">*</span>
                    </label>
                    <div class="grid grid-cols-2 gap-4">
                        <label class="cursor-pointer">
                            <input type="radio" name="tipo_movimiento" value="entrada" class="hidden peer" {{ $selectedTipoMovimiento === 'entrada' ? 'checked' : '' }} required>
                            <div class="p-4 border-2 border-gray-300 rounded-lg text-center peer-checked:border-green-500 peer-checked:bg-green-50 transition-all hover:border-green-300">
                                <i class="fas fa-arrow-down text-3xl text-green-600 mb-2"></i>
                                <p class="font-medium text-gray-900">Entrada</p>
                                <p class="text-xs text-gray-500">Agregar stock</p>
                            </div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="tipo_movimiento" value="salida" class="hidden peer" {{ $selectedTipoMovimiento === 'salida' ? 'checked' : '' }} required>
                            <div class="p-4 border-2 border-gray-300 rounded-lg text-center peer-checked:border-red-500 peer-checked:bg-red-50 transition-all hover:border-red-300">
                                <i class="fas fa-arrow-up text-3xl text-red-600 mb-2"></i>
                                <p class="font-medium text-gray-900">Salida</p>
                                <p class="text-xs text-gray-500">Retirar stock</p>
                            </div>
                        </label>
                    </div>
                </div>

                <div id="tipoEntradaContainer" class="hidden lg:col-span-2">
                    <label for="tipo_entrada" class="block text-sm font-medium text-gray-700 mb-2">
                        Motivo de Entrada <span class="text-red-500">*</span>
                    </label>
                    <select name="tipo_entrada" id="tipo_entrada" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <option value="">Selecciona el motivo</option>
                        @foreach(\App\Models\Movimiento::tiposEntrada() as $key => $label)
                            @if($key !== 'devolucion_subinventario')
                                <option value="{{ $key }}" {{ $selectedTipoEntrada === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endif
                        @endforeach
                    </select>
                </div>

                <div id="tipoSalidaContainer" class="hidden lg:col-span-2">
                    <label for="tipo_salida" class="block text-sm font-medium text-gray-700 mb-2">
                        Motivo de Salida <span class="text-red-500">*</span>
                    </label>
                    <select name="tipo_salida" id="tipo_salida" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <option value="">Selecciona el motivo</option>
                        @foreach(\App\Models\Movimiento::tiposSalida() as $key => $label)
                            @if($key !== 'transferencia_subinventario')
                                <option value="{{ $key }}" {{ $selectedTipoSalida === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endif
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="fecha" class="block text-sm font-medium text-gray-700 mb-2">
                        Fecha del Ajuste
                    </label>
                    <div class="relative">
                        <span class="absolute left-3 top-2.5 text-gray-400">
                            <i class="fas fa-calendar"></i>
                        </span>
                        <input type="date" name="fecha" id="fecha" value="{{ $selectedFecha }}"
                               class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                    </div>
                </div>

                <div>
                    <label for="observaciones" class="block text-sm font-medium text-gray-700 mb-2">
                        Observación general
                    </label>
                    <input type="text" name="observaciones" id="observaciones" maxlength="500" value="{{ $selectedObservaciones }}"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                           placeholder="Ej: Corrección de inventario físico">
                </div>

                <div class="lg:col-span-2 bg-white rounded-xl mt-2" style="border: 1px solid #e2e8f0; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); padding: 24px;">
                    <label class="block text-sm font-bold mb-2" style="color: #334155; font-size: 14px; font-weight: 700;">
                        Selecciona tu archivo Excel:
                    </label>

                    <div class="mb-2">
                        <label for="archivo" id="dropzone" class="cursor-pointer transition-colors" style="border: 1px dashed #cbd5e1; background-color: rgba(248, 250, 252, 0.5); padding: 16px 24px; border-radius: 8px; display: flex; align-items: center; justify-content: flex-start; min-height: 56px; width: 100%;">
                            <input
                                type="file"
                                name="archivo"
                                id="archivo"
                                form="excelImportForm"
                                accept=".xlsx,.xls,.csv"
                                required
                                class="hidden"
                            >
                            <span id="archivo-name" class="select-none" style="color: #94a3b8; font-size: 14px; font-weight: 400;">
                                Seleccionar archivo ningún archivo seleccionado
                            </span>
                        </label>
                    </div>

                    <div style="display: flex; align-items: flex-start; gap: 8px; font-size: 12px; color: #64748b; margin-top: 8px; margin-bottom: 24px;">
                        <i class="fas fa-info-circle mt-0.5" style="color: #64748b; font-size: 12px; margin-top: 2px;"></i>
                        <span>
                            <strong>Formatos aceptados:</strong> Excel (.xlsx, .xls) o CSV | <strong>Columnas:</strong> ID (columna A), Cantidad (columna B)
                        </span>
                    </div>

                    <div style="display: flex; gap: 16px; flex-wrap: wrap;">
                        <button
                            type="submit"
                            form="excelImportForm"
                            class="transition-all cursor-pointer"
                            style="flex: 1; min-width: 200px; background-color: #00ad43; border: none; padding: 12px 24px; font-weight: 700; border-radius: 8px; color: white; display: flex; align-items: center; justify-content: center; gap: 8px; font-size: 14px;"
                            onmouseover="this.style.backgroundColor='#009439'"
                            onmouseout="this.style.backgroundColor='#00ad43'"
                        >
                            <i class="fas fa-upload"></i>
                            Importar Libros
                        </button>

                        <button
                            type="button"
                            onclick="window.location='{{ route('movimientos.masivo.template') }}'"
                            class="transition-all cursor-pointer"
                            style="flex: 1; min-width: 200px; background-color: #2d3748; border: none; padding: 12px 24px; font-weight: 700; border-radius: 8px; color: white; display: flex; align-items: center; justify-content: center; gap: 8px; font-size: 14px;"
                            onmouseover="this.style.backgroundColor='#1a202c'"
                            onmouseout="this.style.backgroundColor='#2d3748'"
                        >
                            <i class="fas fa-download"></i>
                            Descargar Plantilla
                        </button>
                    </div>
                </div>

                @isset($preview)
                    <div class="lg:col-span-2 rounded-lg border {{ $hasErrors ? 'border-red-200 bg-red-50' : 'border-green-200 bg-green-50' }} p-4">
                        <div class="flex items-start gap-3">
                            <i class="fas {{ $hasErrors ? 'fa-exclamation-triangle text-red-500' : 'fa-check-circle text-green-600' }} mt-0.5"></i>
                            <div>
                                <p class="font-semibold {{ $hasErrors ? 'text-red-800' : 'text-green-800' }}">
                                    {{ $hasErrors ? 'Hay errores en el Excel' : 'Excel listo para confirmar' }}
                                </p>
                                <p class="text-sm {{ $hasErrors ? 'text-red-700' : 'text-green-700' }}">
                                    {{ $hasErrors ? 'Corrige las filas marcadas y vuelve a subir el archivo.' : 'Al confirmar se creará un movimiento individual por cada fila válida.' }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="lg:col-span-2">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                            <div class="rounded-lg bg-gray-50 border border-gray-200 p-4">
                                <p class="text-xs text-gray-500">Inventario</p>
                                <p class="font-semibold text-gray-900">
                                    {{ $context['origen_stock'] === 'subinventario' ? 'Subinventario' : 'Inventario general' }}
                                </p>
                            </div>
                            <div class="rounded-lg bg-gray-50 border border-gray-200 p-4">
                                <p class="text-xs text-gray-500">Movimiento</p>
                                <p class="font-semibold {{ $context['tipo_movimiento'] === 'entrada' ? 'text-green-700' : 'text-red-700' }}">
                                    {{ ucfirst($context['tipo_movimiento']) }}
                                </p>
                            </div>
                            <div class="rounded-lg bg-gray-50 border border-gray-200 p-4">
                                <p class="text-xs text-gray-500">Fecha</p>
                                <p class="font-semibold text-gray-900">{{ \Carbon\Carbon::parse($context['fecha'])->format('d/m/Y') }}</p>
                            </div>
                            <div class="rounded-lg bg-gray-50 border border-gray-200 p-4">
                                <p class="text-xs text-gray-500">Filas válidas</p>
                                <p class="font-semibold text-gray-900">{{ $validRows->count() }} / {{ count($preview) }}</p>
                            </div>
                        </div>

                        <div class="overflow-x-auto border border-gray-200 rounded-lg">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fila</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Libro</th>
                                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Stock</th>
                                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Cantidad</th>
                                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Resultante</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Observación</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-100">
                                    @foreach($preview as $row)
                                        <tr class="{{ !empty($row['errores']) ? 'bg-red-50' : '' }}">
                                            <td class="px-4 py-3 text-sm text-gray-700">{{ $row['fila'] ?: '-' }}</td>
                                            <td class="px-4 py-3">
                                                @if($row['libro_id'])
                                                    <div class="text-sm font-medium text-gray-900">{{ $row['nombre'] }}</div>
                                                    <div class="text-xs text-gray-500">ID: {{ $row['libro_id'] }} · {{ $row['codigo_barras'] ?: 'Sin código' }}</div>
                                                @else
                                                    <span class="text-sm text-red-700">No encontrado</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-center text-sm text-gray-700">{{ $row['stock_actual'] ?? '-' }}</td>
                                            <td class="px-4 py-3 text-center text-sm font-semibold">{{ $row['cantidad'] ?: '-' }}</td>
                                            <td class="px-4 py-3 text-center text-sm font-semibold {{ ($row['stock_resultante'] ?? 0) < 0 ? 'text-red-600' : 'text-blue-700' }}">
                                                {{ $row['stock_resultante'] ?? '-' }}
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-700">{{ $row['observaciones'] ?: '-' }}</td>
                                            <td class="px-4 py-3 text-sm">
                                                @if(empty($row['errores']))
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                        <i class="fas fa-check mr-1"></i> OK
                                                    </span>
                                                @else
                                                    <ul class="text-red-700 list-disc list-inside space-y-1">
                                                        @foreach($row['errores'] as $error)
                                                            <li>{{ $error }}</li>
                                                        @endforeach
                                                    </ul>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4 flex justify-end gap-3">
                            <x-button type="button" variant="secondary" icon="fas fa-times" onclick="window.location='{{ route('movimientos.index') }}'">
                                Cancelar
                            </x-button>

                            @if(!$hasErrors)
                                <x-button type="submit" variant="primary" icon="fas fa-save" form="confirmExcelImportForm">
                                    Confirmar Ajuste Masivo del Excel
                                </x-button>
                            @else
                                <button
                                    type="button"
                                    disabled
                                    class="px-4 py-2 rounded-lg font-medium flex items-center justify-center bg-gray-300 text-gray-500 cursor-not-allowed"
                                >
                                    <i class="fas fa-save mr-2"></i>
                                    Corrige errores para guardar
                                </button>
                            @endif
                        </div>
                    </div>
                @endisset


            </div>
        </form>

        <form action="{{ route('movimientos.masivo.import.preview') }}" method="POST" enctype="multipart/form-data" id="excelImportForm" class="hidden">
            @csrf
            <input type="hidden" name="tipo_movimiento" id="import_tipo_movimiento">
            <input type="hidden" name="tipo_entrada" id="import_tipo_entrada">
            <input type="hidden" name="tipo_salida" id="import_tipo_salida">
            <input type="hidden" name="origen_stock" id="import_origen_stock">
            <input type="hidden" name="subinventario_id" id="import_subinventario_id">
            <input type="hidden" name="fecha" id="import_fecha">
            <input type="hidden" name="observaciones" id="import_observaciones">
        </form>

        @isset($preview)
            @if(!$hasErrors)
                <form action="{{ route('movimientos.masivo.store') }}" method="POST" id="confirmExcelImportForm" class="hidden">
                    @csrf
                    <input type="hidden" name="tipo_movimiento" value="{{ $context['tipo_movimiento'] }}">
                    <input type="hidden" name="tipo_entrada" value="{{ $context['tipo_entrada'] }}">
                    <input type="hidden" name="tipo_salida" value="{{ $context['tipo_salida'] }}">
                    <input type="hidden" name="origen_stock" value="{{ $context['origen_stock'] }}">
                    <input type="hidden" name="subinventario_id" value="{{ $context['subinventario_id'] }}">
                    <input type="hidden" name="fecha" value="{{ $context['fecha'] }}">
                    <input type="hidden" name="observaciones" value="{{ $context['observaciones'] }}">

                    @foreach($validRows as $index => $row)
                        <input type="hidden" name="items[{{ $index }}][libro_id]" value="{{ $row['libro_id'] }}">
                        <input type="hidden" name="items[{{ $index }}][cantidad]" value="{{ $row['cantidad'] }}">
                        <input type="hidden" name="items[{{ $index }}][observaciones]" value="{{ $row['observaciones'] }}">
                    @endforeach
                </form>
            @endif
        @endisset
    </x-card>
</x-page-layout>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const librosData = @json($libros);
    const subinventariosData = @json($subinventarios);
    const oldItemsRaw = @json(old('items', []));
    const itemsBody = document.getElementById('itemsBody');
    const addRowBtn = document.getElementById('addRowBtn');
    const subinventarioContainer = document.getElementById('subinventarioContainer');
    const subinventarioSelect = document.getElementById('subinventario_id');
    const tipoMovimientoInputs = document.querySelectorAll('input[name="tipo_movimiento"]');
    const origenInputs = document.querySelectorAll('input[name="origen_stock"]');
    const tipoEntradaContainer = document.getElementById('tipoEntradaContainer');
    const tipoSalidaContainer = document.getElementById('tipoSalidaContainer');
    const tipoEntradaSelect = document.getElementById('tipo_entrada');
    const tipoSalidaSelect = document.getElementById('tipo_salida');
    const stockValidationBanner = document.getElementById('stockValidationBanner');
    const stockValidationMessage = document.getElementById('stockValidationMessage');
    const totalLineas = document.getElementById('totalLineas');
    const totalUnidades = document.getElementById('totalUnidades');
    const excelImportForm = document.getElementById('excelImportForm');
    const manualCaptureEnabled = Boolean(itemsBody && addRowBtn);

    let rowIndex = 0;

    function currentOrigenStock() {
        return document.querySelector('input[name="origen_stock"]:checked')?.value || 'general';
    }

    function currentTipoMovimiento() {
        return document.querySelector('input[name="tipo_movimiento"]:checked')?.value || null;
    }

    function subinventarioSeleccionado() {
        if (!subinventarioSelect || !subinventarioSelect.value) {
            return null;
        }

        return subinventariosData.find(item => item.id == subinventarioSelect.value) || null;
    }

    function seleccionarUnicoSubinventario() {
        if (!subinventarioSelect || subinventarioSelect.value) {
            return;
        }

        const opciones = Array.from(subinventarioSelect.options).filter(option => option.value);
        if (opciones.length === 1) {
            subinventarioSelect.value = opciones[0].value;
        }
    }

    function librosDisponibles() {
        if (currentOrigenStock() !== 'subinventario') {
            return librosData.map(libro => ({
                ...libro,
                stock_disponible: Math.max(0, Number(libro.stock || 0) - Number(libro.stock_apartado || 0)),
            }));
        }

        const subinventario = subinventarioSeleccionado();
        if (!subinventario) {
            return [];
        }

        return (subinventario.libros || []).map(libro => {
            const base = librosData.find(item => item.id == libro.id) || {};
            const stock = Number(libro.pivot?.cantidad ?? 0);
            return {
                ...base,
                ...libro,
                stock: stock,
                stock_disponible: stock,
            };
        });
    }

    function populateSelect(select, selectedValue = '') {
        const libros = librosDisponibles();
        select.innerHTML = '';

        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = libros.length ? 'Selecciona un libro' : 'No hay libros disponibles';
        select.appendChild(placeholder);

        libros.forEach(libro => {
            const option = document.createElement('option');
            option.value = libro.id;
            option.textContent = `${libro.nombre} (${libro.codigo_barras || 'Sin código'})`;
            option.dataset.stock = libro.stock_disponible;
            option.dataset.precio = libro.precio;
            if (String(libro.id) === String(selectedValue)) {
                option.selected = true;
            }
            select.appendChild(option);
        });

        if (selectedValue && select.value !== String(selectedValue)) {
            select.value = '';
        }
    }

    function findStock(libroId) {
        const libro = librosDisponibles().find(item => String(item.id) === String(libroId));
        return libro ? Number(libro.stock_disponible || 0) : null;
    }

    function updateRow(row) {
        const select = row.querySelector('.libro-select');
        const cantidadInput = row.querySelector('.cantidad-input');
        const stockCell = row.querySelector('.stock-cell');
        const resultCell = row.querySelector('.result-cell');
        const cantidad = Number(cantidadInput.value || 0);
        const stock = select.value ? findStock(select.value) : null;
        const tipo = currentTipoMovimiento();

        row.classList.remove('bg-red-50');

        if (stock === null) {
            stockCell.textContent = '-';
            resultCell.textContent = '-';
            return;
        }

        const resultante = tipo === 'entrada' ? stock + cantidad : stock - cantidad;
        stockCell.textContent = stock;
        resultCell.textContent = resultante;
        resultCell.className = 'result-cell px-4 py-3 text-center text-sm font-semibold';

        if (tipo === 'salida' && resultante < 0) {
            row.classList.add('bg-red-50');
            resultCell.classList.add('text-red-600');
        } else if (tipo === 'entrada') {
            resultCell.classList.add('text-green-700');
        } else {
            resultCell.classList.add(resultante < 10 ? 'text-amber-600' : 'text-blue-700');
        }
    }

    function updateTotals() {
        if (!manualCaptureEnabled) {
            return;
        }

        const rows = Array.from(itemsBody.querySelectorAll('tr'));
        const unidades = rows.reduce((total, row) => {
            return total + Number(row.querySelector('.cantidad-input')?.value || 0);
        }, 0);

        totalLineas.textContent = rows.length;
        totalUnidades.textContent = unidades;
    }

    function refreshAllRows() {
        if (!manualCaptureEnabled) {
            return;
        }

        itemsBody.querySelectorAll('tr').forEach(row => {
            const select = row.querySelector('.libro-select');
            const previousValue = select.value;
            populateSelect(select, previousValue);
            updateRow(row);
        });
        updateTotals();
    }

    function addRow(data = {}) {
        if (!manualCaptureEnabled) {
            return;
        }

        const index = rowIndex++;
        const row = document.createElement('tr');
        row.innerHTML = `
            <td class="px-4 py-3 min-w-[280px]">
                <select name="items[${index}][libro_id]" class="libro-select w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" required></select>
            </td>
            <td class="stock-cell px-4 py-3 text-center text-sm text-gray-700">-</td>
            <td class="px-4 py-3">
                <input type="number" name="items[${index}][cantidad]" min="1" value="${data.cantidad || 1}" class="cantidad-input w-24 px-3 py-2 border border-gray-300 rounded-lg text-center focus:ring-2 focus:ring-primary-500 focus:border-primary-500" required>
            </td>
            <td class="result-cell px-4 py-3 text-center text-sm font-semibold">-</td>
            <td class="px-4 py-3 min-w-[220px]">
                <input type="text" name="items[${index}][observaciones]" value="${escapeAttribute(data.observaciones || '')}" maxlength="255" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" placeholder="Opcional">
            </td>
            <td class="px-4 py-3 text-center">
                <button type="button" class="remove-row text-red-600 hover:text-red-800" title="Quitar libro">
                    <i class="fas fa-times"></i>
                </button>
            </td>
        `;

        itemsBody.appendChild(row);
        const select = row.querySelector('.libro-select');
        populateSelect(select, data.libro_id || '');
        updateRow(row);

        select.addEventListener('change', () => {
            hideValidation();
            updateRow(row);
        });
        row.querySelector('.cantidad-input').addEventListener('input', () => {
            hideValidation();
            updateRow(row);
            updateTotals();
        });
        row.querySelector('.remove-row').addEventListener('click', () => {
            row.remove();
            updateTotals();
            if (!itemsBody.querySelector('tr')) {
                addRow();
            }
        });

        updateTotals();
    }

    function escapeAttribute(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function toggleSubinventario() {
        const show = currentOrigenStock() === 'subinventario';
        subinventarioContainer.classList.toggle('hidden', !show);
        subinventarioSelect.required = show;
        if (show) {
            seleccionarUnicoSubinventario();
        } else {
            subinventarioSelect.value = '';
        }
    }

    function toggleTipoMovimiento() {
        const tipo = currentTipoMovimiento();
        tipoEntradaContainer.classList.toggle('hidden', tipo !== 'entrada');
        tipoSalidaContainer.classList.toggle('hidden', tipo !== 'salida');
        tipoEntradaSelect.required = tipo === 'entrada';
        tipoSalidaSelect.required = tipo === 'salida';
        refreshAllRows();
    }

    function hideValidation() {
        stockValidationBanner.classList.add('hidden');
    }

    function showValidation(message) {
        stockValidationMessage.textContent = message;
        stockValidationBanner.classList.remove('hidden');
        stockValidationBanner.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    origenInputs.forEach(input => input.addEventListener('change', () => {
        hideValidation();
        toggleSubinventario();
        refreshAllRows();
    }));

    tipoMovimientoInputs.forEach(input => input.addEventListener('change', () => {
        hideValidation();
        toggleTipoMovimiento();
    }));

    subinventarioSelect.addEventListener('change', () => {
        hideValidation();
        refreshAllRows();
    });

    if (addRowBtn) {
        addRowBtn.addEventListener('click', () => addRow());
    }

    excelImportForm.addEventListener('submit', function(event) {
        const tipo = currentTipoMovimiento();
        if (!tipo) {
            event.preventDefault();
            showValidation('Selecciona Entrada o Salida antes de previsualizar el Excel.');
            return;
        }

        if (currentOrigenStock() === 'subinventario' && !subinventarioSelect.value) {
            event.preventDefault();
            showValidation('Selecciona el subinventario antes de previsualizar el Excel.');
            return;
        }

        document.getElementById('import_tipo_movimiento').value = tipo;
        document.getElementById('import_tipo_entrada').value = tipoEntradaSelect.value;
        document.getElementById('import_tipo_salida').value = tipoSalidaSelect.value;
        document.getElementById('import_origen_stock').value = currentOrigenStock();
        document.getElementById('import_subinventario_id').value = subinventarioSelect.value;
        document.getElementById('import_fecha').value = document.getElementById('fecha').value;
        document.getElementById('import_observaciones').value = document.getElementById('observaciones').value;
    });

    // Event listeners para actualizar el nombre del archivo y drag & drop
    const fileInput = document.getElementById('archivo');
    const fileNameSpan = document.getElementById('archivo-name');
    const dropzone = document.getElementById('dropzone');

    if (fileInput && fileNameSpan) {
        fileInput.addEventListener('change', function() {
            if (this.files && this.files.length > 0) {
                fileNameSpan.textContent = this.files[0].name;
                fileNameSpan.classList.remove('text-slate-400');
                fileNameSpan.classList.add('text-slate-800', 'font-medium');
            } else {
                fileNameSpan.textContent = 'Seleccionar archivo ningún archivo seleccionado';
                fileNameSpan.classList.remove('text-slate-800', 'font-medium');
                fileNameSpan.classList.add('text-slate-400');
            }
        });
    }

    if (dropzone && fileInput) {
        ['dragenter', 'dragover'].forEach(eventName => {
            dropzone.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropzone.style.borderColor = '#00ad43';
                dropzone.style.backgroundColor = 'rgba(240, 253, 244, 0.5)';
            }, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropzone.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropzone.style.borderColor = '#cbd5e1';
                dropzone.style.backgroundColor = 'rgba(248, 250, 252, 0.5)';
            }, false);
        });

        dropzone.addEventListener('drop', (e) => {
            const dt = e.dataTransfer;
            const files = dt.files;
            if (files && files.length > 0) {
                fileInput.files = files;
                fileInput.dispatchEvent(new Event('change'));
            }
        }, false);
    }

    document.getElementById('ajusteMasivoForm').addEventListener('submit', function(event) {
        if (!manualCaptureEnabled) {
            return;
        }

        const rows = Array.from(itemsBody.querySelectorAll('tr'));
        const seen = new Set();

        for (const row of rows) {
            const select = row.querySelector('.libro-select');
            const cantidad = Number(row.querySelector('.cantidad-input').value || 0);
            const stock = select.value ? findStock(select.value) : null;

            if (!select.value) {
                event.preventDefault();
                showValidation('Cada línea debe tener un libro seleccionado.');
                return;
            }

            if (seen.has(select.value)) {
                event.preventDefault();
                showValidation('No repitas el mismo libro dentro del ajuste masivo.');
                return;
            }
            seen.add(select.value);

            if (currentTipoMovimiento() === 'salida' && stock !== null && cantidad > stock) {
                event.preventDefault();
                showValidation('Hay una salida con cantidad mayor al stock disponible.');
                return;
            }
        }
    });

    toggleSubinventario();
    toggleTipoMovimiento();

    if (manualCaptureEnabled) {
        const oldItems = Array.isArray(oldItemsRaw) ? oldItemsRaw : Object.values(oldItemsRaw || {});
        if (oldItems.length) {
            oldItems.forEach(item => addRow(item));
        } else {
            addRow();
        }
    }
});
</script>
@endpush
