@props([
    'as' => 'input',
    'size' => 'base',
    // 'steel', matching Input.atom.js's own default (`color = 'steel'`) —
    // every call site that needs a different color already passes one
    // explicitly (docs/rewrite/golden-master-review.md).
    'color' => 'steel',
    'type' => 'text',
    // Input.atom.js `displayRing`: the search field draws its own border and no hover/focus ring.
    'displayRing' => true,
    // The validation key when it is neither the `name` nor the `wire:model` target.
    'errorFor' => null,
])

@php
    $sizeClass = $size === 'large'
        ? 'px-5 py-4 border-2 text-lg font-semibold'
        : 'px-3 py-2 border-2 text-lg font-normal';

    $colorClass = match ($color) {
        'stone' => 'hover:ring-stone-800/25 focus:ring-stone-800/25',
        'steel' => 'hover:ring-steel-700/25 focus:ring-steel-700/25',
        'moss' => 'hover:ring-moss-800/25 focus:ring-moss-800/25',
        default => 'hover:ring-lilac-800/25 focus:ring-lilac-800/25',
    };
@endphp

@php
    // Input.atom.js's exact base classes (docs/design/visual-language.md) —
    // `rounded-md`, not squared corners, and `placeholder-current` so
    // placeholder text takes the same color as the input's own text color
    // rather than Tailwind's default gray.
    $baseClass = 'inline-block w-full text-current border-current bg-transparent rounded-md appearance-none placeholder-current transition-shadow duration-200 ease-out focus:outline-none focus:placeholder-transparent disabled:opacity-50 disabled:pointer-events-none';
@endphp

@php
    // Decision A-7: an error message is tied to its field (App\Support\FieldError).
    $attributes = $attributes->merge(\App\Support\FieldError::attributes(\App\Support\FieldError::field($attributes, $errorFor), $errors ?? null));
    $ringClass = $displayRing ? 'ring-2 ring-transparent' : '';
    $colorClass = $displayRing ? $colorClass : '';
@endphp

@if ($as === 'textarea')
    <textarea {{ $attributes->class([$baseClass, $sizeClass, $colorClass, $ringClass]) }}>{{ $slot }}</textarea>
@else
    <input type="{{ $type }}" {{ $attributes->class([$baseClass, $sizeClass, $colorClass, $ringClass]) }} />
@endif
