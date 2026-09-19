@props(['progress', 'label' => null])

{{-- Progressbar.molecule.js (color `lilac`). --}}
<div {{ $attributes }}>
    <div aria-hidden="true" class="w-full h-3 rounded-full bg-lilac-400">
        <div style="width: {{ $progress }}%" class="h-3 transition-all duration-300 ease-out rounded-full bg-lilac-600"></div>
    </div>
    <span class="sr-only">Progress {{ $progress }}%</span>
    @if ($label)
        <x-text class="mt-1 text-lilac-800" variant="textXs">{{ $label }}</x-text>
    @endif
</div>
