<label class="flex items-start gap-3 cursor-pointer">
    <input
        type="checkbox"
        {{ $attributes->class(['w-5 h-5 mt-0.5 shrink-0 border-2 border-steel-700 text-lilac-800 rounded-none focus:ring-2 focus:ring-lilac-800/25']) }}
    />
    <x-text as="span" variant="textSm">{{ $slot }}</x-text>
</label>
