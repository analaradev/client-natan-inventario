@extends('layouts.app')

@section('title', 'Registrar Abono')

@section('page-title', 'Registrar Abono')
@section('page-description', 'Registrar nuevo pago para el apartado')

@section('content')
<x-page-layout 
    title="Registrar Abono"
    :description="'Apartado ' . $apartado->folio"
    button-text="Volver al Apartado"
    button-icon="fas fa-arrow-left"
    :button-route="route('apartados.show', $apartado)"
>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Formulario de Abono -->
        <div class="lg:col-span-2">
            <x-card title="Nuevo Abono">
                <form action="{{ route('apartados.abonos.store', $apartado) }}" method="POST" id="abonoForm">
                    @csrf

                    <div class="space-y-6">
                        <!-- Fecha y Monto -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2" for="fecha_abono">
                                    <i class="fas fa-calendar text-primary-600 mr-1"></i>
                                    Fecha del Abono <span class="text-red-500">*</span>
                                </label>
                                <input type="date" 
                                       name="fecha_abono" 
                                       id="fecha_abono" 
                                       class="w-full px-4 py-2.5 border-2 border-gray-300 rounded-lg focus:border-primary-500 focus:ring-2 focus:ring-primary-200 transition-colors" 
                                       value="{{ old('fecha_abono', date('Y-m-d')) }}" 
                                       required>
                                @error('fecha_abono')
                                    <p class="text-red-500 text-sm mt-1">
                                        <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                                    </p>
                                @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2" for="monto">
                                    <i class="fas fa-dollar-sign text-green-600 mr-1"></i>
                                    Monto del Abono <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500">$</span>
                                    <input type="number" 
                                           step="0.01" 
                                           name="monto" 
                                           id="monto" 
                                           class="w-full pl-8 pr-4 py-2.5 border-2 border-gray-300 rounded-lg focus:border-green-500 focus:ring-2 focus:ring-green-200 transition-colors" 
                                           value="{{ old('monto') }}" 
                                           min="0.01" 
                                           max="{{ $apartado->saldo_pendiente }}" 
                                           placeholder="0.00"
                                           required>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Máximo: <span class="font-semibold text-orange-600">${{ number_format($apartado->saldo_pendiente, 2) }}</span>
                                </p>
                                <div id="saldoProyectado" class="mt-2 hidden rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-800">
                                    <i class="fas fa-calculator mr-1"></i>
                                    Saldo proyectado: <span class="font-bold" id="saldoProyectadoMonto">$0.00</span>
                                </div>
                                @error('monto')
                                    <p class="text-red-500 text-sm mt-1">
                                        <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                                    </p>
                                @enderror
                            </div>
                        </div>

                        <!-- Método de Pago -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2" for="metodo_pago">
                                <i class="fas fa-credit-card text-blue-600 mr-1"></i>
                                Método de Pago <span class="text-red-500">*</span>
                            </label>
                            <select name="metodo_pago" 
                                    id="metodo_pago" 
                                    class="w-full px-4 py-2.5 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-colors bg-white" 
                                    required>
                                <option value="efectivo" {{ old('metodo_pago') == 'efectivo' ? 'selected' : '' }}>
                                    💵 Efectivo
                                </option>
                                <option value="transferencia" {{ old('metodo_pago') == 'transferencia' ? 'selected' : '' }}>
                                    🏦 Transferencia Bancaria
                                </option>
                                <option value="tarjeta" {{ old('metodo_pago') == 'tarjeta' ? 'selected' : '' }}>
                                    💳 Tarjeta de Crédito/Débito
                                </option>
                            </select>
                            @error('metodo_pago')
                                <p class="text-red-500 text-sm mt-1">
                                    <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                                </p>
                            @enderror
                        </div>

                        <!-- Comprobante -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2" for="comprobante">
                                <i class="fas fa-receipt text-purple-600 mr-1"></i>
                                Comprobante / Referencia
                            </label>
                            <input type="text" 
                                   name="comprobante" 
                                   id="comprobante" 
                                   class="w-full px-4 py-2.5 border-2 border-gray-300 rounded-lg focus:border-purple-500 focus:ring-2 focus:ring-purple-200 transition-colors" 
                                   value="{{ old('comprobante') }}" 
                                   placeholder="Ej: Número de transferencia, ticket, folio...">
                            <p class="text-xs text-gray-500 mt-1">
                                <i class="fas fa-info-circle mr-1"></i>
                                Opcional: Número de confirmación, ticket o cualquier referencia del pago
                            </p>
                            @error('comprobante')
                                <p class="text-red-500 text-sm mt-1">
                                    <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                                </p>
                            @enderror
                        </div>

                        <!-- Observaciones -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2" for="observaciones">
                                <i class="fas fa-sticky-note text-yellow-600 mr-1"></i>
                                Observaciones
                            </label>
                            <textarea name="observaciones" 
                                      id="observaciones" 
                                      rows="3" 
                                      class="w-full px-4 py-2.5 border-2 border-gray-300 rounded-lg focus:border-yellow-500 focus:ring-2 focus:ring-yellow-200 transition-colors resize-none" 
                                      placeholder="Notas adicionales sobre este abono (opcional)">{{ old('observaciones') }}</textarea>
                            @error('observaciones')
                                <p class="text-red-500 text-sm mt-1">
                                    <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                                </p>
                            @enderror
                        </div>
                    </div>

                    <!-- Botones de Acción -->
                    <div class="flex flex-col sm:flex-row justify-end gap-3 mt-8 pt-6 border-t border-gray-200">
                        <x-button 
                            variant="secondary" 
                            icon="fas fa-times" 
                            onclick="window.location='{{ route('apartados.show', $apartado) }}'"
                            type="button">
                            Cancelar
                        </x-button>
                        <x-button 
                            variant="success" 
                            icon="fas fa-check-circle" 
                            type="submit">
                            Registrar Abono
                        </x-button>
                    </div>
                </form>
            </x-card>
        </div>

        <!-- Información del Apartado -->
        <div class="lg:col-span-1 space-y-6">
            <!-- Información General -->
            <x-card title="Información del Apartado">
                <div class="space-y-4">
                    <div class="flex items-center justify-between py-2 border-b border-gray-100">
                        <span class="text-sm text-gray-600">
                            <i class="fas fa-user text-primary-600 mr-2"></i>
                            Cliente
                        </span>
                        <span class="font-semibold text-gray-800">{{ $apartado->cliente->nombre }}</span>
                    </div>
                    <div class="flex items-center justify-between py-2 border-b border-gray-100">
                        <span class="text-sm text-gray-600">
                            <i class="fas fa-hashtag text-blue-600 mr-2"></i>
                            Folio
                        </span>
                        <span class="font-mono font-semibold text-gray-800">{{ $apartado->folio }}</span>
                    </div>
                    <div class="flex items-center justify-between py-2">
                        <span class="text-sm text-gray-600">
                            <i class="fas fa-calendar text-green-600 mr-2"></i>
                            Fecha
                        </span>
                        <span class="font-semibold text-gray-800">{{ $apartado->fecha_apartado->format('d/m/Y') }}</span>
                    </div>
                </div>
            </x-card>

            <!-- Estado de Cuenta -->
            <x-card title="Estado de Cuenta">
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
                    <div class="flex justify-between items-center py-3 bg-orange-50 rounded-lg px-3">
                        <span class="text-gray-800 font-bold">Saldo Pendiente:</span>
                        <span class="text-orange-600 font-bold text-2xl">
                            ${{ number_format($apartado->saldo_pendiente, 2) }}
                        </span>
                    </div>

                    <!-- Barra de Progreso -->
                    <div class="bg-blue-50 p-4 rounded-lg">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-sm text-blue-800 font-medium">Porcentaje Pagado:</span>
                            <span class="text-lg font-bold text-blue-900">{{ $apartado->porcentajePagado }}%</span>
                        </div>
                        <div class="w-full bg-blue-200 rounded-full h-2.5">
                            <div class="bg-blue-600 h-2.5 rounded-full transition-all duration-300" 
                                 style="width: {{ $apartado->porcentajePagado }}%"></div>
                        </div>
                    </div>
                </div>
            </x-card>
        </div>
    </div>

    <!-- Historial de Abonos Previos -->
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
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($apartado->abonos()->latest()->get() as $abono)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $loop->iteration }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <i class="fas fa-calendar text-gray-400 mr-1"></i>
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
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-card>
    @endif
</x-page-layout>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const montoInput = document.getElementById('monto');
    const saldoProyectado = document.getElementById('saldoProyectado');
    const saldoProyectadoMonto = document.getElementById('saldoProyectadoMonto');
    const saldoActual = {{ (float) $apartado->saldo_pendiente }};

    function formatCurrency(value) {
        return '$' + value.toLocaleString('es-MX', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function updateSaldoProyectado() {
        const monto = parseFloat(montoInput.value);

        if (!monto || monto <= 0) {
            saldoProyectado.classList.add('hidden');
            return;
        }

        const nuevoSaldo = Math.max(saldoActual - monto, 0);
        saldoProyectadoMonto.textContent = formatCurrency(nuevoSaldo);
        saldoProyectado.classList.remove('hidden');
        saldoProyectado.classList.toggle('border-green-200', nuevoSaldo === 0);
        saldoProyectado.classList.toggle('bg-green-50', nuevoSaldo === 0);
        saldoProyectado.classList.toggle('text-green-800', nuevoSaldo === 0);
        saldoProyectado.classList.toggle('border-blue-200', nuevoSaldo !== 0);
        saldoProyectado.classList.toggle('bg-blue-50', nuevoSaldo !== 0);
        saldoProyectado.classList.toggle('text-blue-800', nuevoSaldo !== 0);
    }

    if (montoInput) {
        montoInput.addEventListener('input', updateSaldoProyectado);
        updateSaldoProyectado();
    }
});
</script>
@endpush
@endsection
