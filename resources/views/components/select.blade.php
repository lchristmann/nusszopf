@props(['color' => 'lilac'])

{{-- Select.atom.js + Select.css (`nz-select-lilac`). `class` lands on the wrapper; everything else on the <select>. --}}
<div @class(['relative rounded-md cursor-pointer', 'nz-select-'.$color, $attributes->get('class')])>
    <select {{ $attributes->except('class')->class(['inline-block w-full py-2 pl-3 pr-10 text-lg font-medium bg-transparent appearance-none cursor-pointer focus:outline-none']) }}>
        {{ $slot }}
    </select>
    <div class="absolute top-0 right-0 flex items-center justify-end h-full pr-2 pointer-events-none">
        <x-icon name="chevron-down" class="flex-shrink-0 -mb-px" />
    </div>
</div>
