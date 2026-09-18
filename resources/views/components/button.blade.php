@props([
    'as' => 'button',
    'variant' => 'outline',
    'size' => 'base',
    'color' => 'lilac',
    'type' => 'button',
])

{{--
    Reproduces the historical Button atom (docs/design/visual-language.md,
    "Shape language"). `outline` is the only real bordered-pill visual
    treatment — `filled` was declared but never actually used anywhere in
    the historical product (BUG-014, resolved) and is not implemented here.
    `clean` renders no border/ring at all, for icon-only/dismiss buttons.
--}}

@php
    $sizeClass = match ($size) {
        'baseClean' => 'font-medium text-lg p-0',
        'small' => 'font-medium text-lg py-1 px-3',
        'large' => 'font-medium text-lg py-3 px-6',
        'circle' => 'text-lg p-2 rounded-full',
        default => 'font-medium text-lg py-2 px-4',
    };

    $colorClass = $variant === 'clean' ? '' : match ($color) {
        'stone' => 'nz-btn-stone',
        'steel' => 'nz-btn-steel',
        'warning' => 'nz-btn-warning',
        'blue' => 'nz-btn-blue',
        'turquoise' => 'nz-btn-turquoise',
        'yellow' => 'nz-btn-yellow',
        'moss' => 'nz-btn-moss',
        default => 'nz-btn-lilac',
    };
@endphp

@if ($as === 'a')
    <a {{ $attributes->class(['inline-flex items-center justify-center', $sizeClass, $colorClass]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class(['inline-flex items-center justify-center', $sizeClass, $colorClass]) }}>{{ $slot }}</button>
@endif
