@props([
    'headers' => [],
    'rows' => [],
    'emptyMessage' => 'No hay datos disponibles',
    'emptyIcon' => 'fas fa-inbox',
    'actions' => true,
    'showActions' => true,
])

<div class="w-full overflow-hidden">
    <table class="w-full table-fixed divide-y divide-gray-200" style="width: 100%; table-layout: fixed;">
        <thead class="bg-gray-50">
            <tr>
                @foreach($headers as $header)
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        {{ $header }}
                    </th>
                @endforeach
                @if($showActions)
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Acciones
                    </th>
                @endif
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
            @if(count($rows) > 0)
                {{ $slot }}
            @else
                <tr>
                    <td colspan="{{ count($headers) + ($showActions ? 1 : 0) }}" class="px-6 py-12">
                        <div class="text-center">
                            <i class="{{ $emptyIcon }} text-gray-300 text-6xl mb-4"></i>
                            <p class="text-gray-500 text-lg">{{ $emptyMessage }}</p>
                        </div>
                    </td>
                </tr>
            @endif
        </tbody>
    </table>
</div>
