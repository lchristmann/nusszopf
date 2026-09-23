@props([
    'as' => 'p',
    // Text.atom.js: `variant = 'textMd'` when none is given.
    'variant' => 'textMd',
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
        default => 'nz-text-md',
    };
@endphp

{{-- Text.atom.js: every non-heading text hyphenates (`hyphens-auto`, the ui-library utility). --}}
<{{ $as }} {{ $attributes->class([$variantClass, 'hyphens-auto' => ! in_array($as, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true)]) }}>{{ $slot }}</{{ $as }}>
