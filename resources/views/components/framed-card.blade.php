{{--
    FramedCard.template.js: one card, centered and width-capped, in a fluid
    Frame. `class` lands on the card (the historical `className`).
--}}
<x-frame fluid class="mt-12 mb-12 sm:mt-16">
    <div {{ $attributes->class(['flex flex-col items-center w-full max-w-sm sm:max-w-md mx-auto rounded-lg sm:px-12 sm:py-16']) }}>
        {{ $slot }}
    </div>
</x-frame>
