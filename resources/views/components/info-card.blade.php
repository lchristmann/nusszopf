{{-- InfoCard.molecule.js --}}
<div {{ $attributes->class(['flex p-5 rounded-lg bg-livid-300 text-livid-800']) }}>
    <x-icon name="info" :size="25" class="flex-shrink-0 mr-2" />
    <x-text as="span" variant="textSm" class="leading-snug text-left">{{ $slot }}</x-text>
</div>
