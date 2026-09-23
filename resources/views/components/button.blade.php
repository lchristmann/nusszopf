@props([
    'as' => 'button',
    'variant' => 'outline',
    'size' => 'base',
    // 'steel', matching Button.atom.js's own default (`color = 'steel'`) —
    // every call site that needs a different color already passes one
    // explicitly (docs/rewrite/golden-master-review.md).
    'color' => 'steel',
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

@php
    // Button.atom.js: `outline-none focus:outline-none`, dimmed and
    // default-cursor while disabled, pointer otherwise; an `iconLeft`
    // wraps the label in a `flex items-center justify-center` row. No
    // display class of its own — a caller's `block`/`hidden` applies — except
    // that the `Link`/`Route` button variants render an `inline-block` anchor.
    $disabled = $attributes->has('disabled');
    $classes = ['inline-block' => $as === 'a', 'outline-none focus:outline-none', $sizeClass, $colorClass, 'opacity-50 cursor-default' => $disabled, 'cursor-pointer' => ! $disabled];
    $content = isset($iconLeft)
        ? new \Illuminate\Support\HtmlString('<div class="flex items-center justify-center">'.$iconLeft.'<span class="'.($size === 'large' ? 'ml-1' : '').'">'.$slot.'</span></div>')
        : $slot;
@endphp

@if ($as === 'a')
    <a {{ $attributes->class($classes) }}>{{ $content }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>{{ $content }}</button>
@endif
