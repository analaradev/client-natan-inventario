@props([
    'bold' => false,
    'center' => false,
    'wrap' => false,
])

@php
    $classes = 'px-6 py-4 text-sm ' . ($wrap ? 'whitespace-normal break-words' : 'whitespace-nowrap');
    if ($bold) $classes .= ' font-medium text-gray-900';
    else $classes .= ' text-gray-500';
    if ($center) $classes .= ' text-center';
@endphp

<td {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</td>
