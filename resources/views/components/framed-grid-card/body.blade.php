@props(['gap' => 'small'])

@php
    $gapClass = match ($gap) {
        'medium' => 'gap-4',
        'large' => 'gap-6',
        default => 'gap-2',
    };
@endphp

<div {{ $attributes->class(['grid grid-cols-12 py-12 md:py-16 rounded-b-lg', $gapClass]) }}>
    {{ $slot }}
</div>
