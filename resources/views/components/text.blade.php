@props([
    'as' => 'p',
    'variant' => 'textSm',
])

@php
    $variantClass = match ($variant) {
        'titleLg' => 'nz-title-lg',
        'titleMd' => 'nz-title-md',
        'titleSm' => 'nz-title-sm',
        'titleSmSemi' => 'nz-title-sm-semi',
        'textLgSemi' => 'nz-text-lg-semi',
        'textLg' => 'nz-text-lg',
        'textLgThin' => 'nz-text-lg-thin',
        'textMd' => 'nz-text-md',
        'textSmMedium' => 'nz-text-sm-medium',
        'textSm' => 'nz-text-sm',
        'textXs' => 'nz-text-xs',
        default => 'nz-text-sm',
    };
@endphp

<{{ $as }} {{ $attributes->class([$variantClass]) }}>{{ $slot }}</{{ $as }}>
