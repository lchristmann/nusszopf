@props([
    'as' => 'div',
    'fluid' => false,
    'size' => 'default',
])

@php
    $sizeClass = match ($size) {
        'large' => 'sm:max-w-2xl',
        default => 'sm:max-w-xl',
    };
@endphp

<{{ $as }} {{ $attributes->class(['px-6 sm:px-16 lg:px-24 xl:px-32']) }}>
    <div @class([
        'lg:container sm:mx-auto' => ! $fluid,
        $sizeClass => ! $fluid,
        'w-full' => $fluid,
    ])>
        {{ $slot }}
    </div>
</{{ $as }}>
