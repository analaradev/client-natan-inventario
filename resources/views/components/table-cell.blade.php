@props([
    'align' => 'left',
    'wrap' => false,
])

@php
    $alignClasses = [
        'left' => 'text-left',
        'center' => 'text-center',
        'right' => 'text-right',
    ];
@endphp

<td {{ $attributes->merge(['class' => 'px-6 py-4 text-sm text-gray-900 ' . ($wrap ? 'whitespace-normal break-words ' : 'whitespace-nowrap ') . $alignClasses[$align]]) }}>
    {{ $slot }}
</td>
