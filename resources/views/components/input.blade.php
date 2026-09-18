@props([
    'as' => 'input',
    'size' => 'base',
    'color' => 'lilac',
    'type' => 'text',
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

@if ($as === 'textarea')
    <textarea {{ $attributes->class(['w-full text-current border-current bg-transparent rounded-none ring-2 ring-transparent transition-shadow duration-200 ease-out', $sizeClass, $colorClass]) }}>{{ $slot }}</textarea>
@else
    <input type="{{ $type }}" {{ $attributes->class(['w-full text-current border-current bg-transparent rounded-none ring-2 ring-transparent transition-shadow duration-200 ease-out', $sizeClass, $colorClass]) }} />
@endif
