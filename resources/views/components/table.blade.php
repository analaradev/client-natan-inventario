@props([
    'headers' => [],
    'items' => [],
    'emptyMessage' => 'No hay datos para mostrar',
    'emptyIcon' => 'fas fa-inbox'
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
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
            @if(count($items) > 0)
                {{ $slot }}
            @else
                <tr>
                    <td colspan="{{ count($headers) }}" class="px-6 py-12 text-center text-gray-500">
                        <i class="{{ $emptyIcon }} text-4xl mb-3 text-gray-300"></i>
                        <p class="text-lg">{{ $emptyMessage }}</p>
                    </td>
                </tr>
            @endif
        </tbody>
    </table>
</div>
