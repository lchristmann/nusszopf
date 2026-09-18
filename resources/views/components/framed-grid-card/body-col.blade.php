@props(['variant' => 'twelveCols'])

@php
    $variantClass = match ($variant) {
        'twoCols' => 'col-span-12 lg:col-span-5',
        'oneCol' => 'col-span-12 lg:col-span-10 lg:col-start-2',
        default => 'col-span-12',
    };
@endphp

<div {{ $attributes->class([$variantClass]) }}>
    {{ $slot }}
</div>
