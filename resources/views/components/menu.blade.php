@props([
    'ariaLabel' => 'Menü',
    'labelClass' => null,
    'items' => [],
    'color' => 'lilac',
    'innerClass' => null,
])

{{--
    Menu.organism.js (reakit `Menu`, placement `bottom-end`): a `focus:outline-none
    my-3` trigger button holding the slot (wrapped in a div with `labelClass`), and a
    rounded, shadowed panel in the color's `Menu.theme.js` look with one
    `textSmMedium` item per entry. The panel scales in over 100ms; an item runs
    its Alpine `click` expression and closes the menu. Escape and an outside
    click close it; the arrow keys move between items.
--}}
@php
    $menuColor = [
        'lilac' => ['text-lilac-800 bg-lilac-200 border border-lilac-500', 'hover:bg-lilac-100 focus:bg-lilac-100'],
        'red' => ['text-stone-800 bg-red-200 border border-red-500', 'hover:bg-red-100 focus:bg-red-100'],
        'yellow' => ['text-stone-800 bg-yellow-200 border border-yellow-500', 'hover:bg-yellow-100 focus:bg-yellow-100'],
        'pink' => ['text-stone-800 bg-pink-200 border border-pink-500', 'hover:bg-pink-100 focus:bg-pink-100'],
        'blue' => ['text-stone-800 bg-blue-200 border border-blue-500', 'hover:bg-blue-100 focus:bg-blue-100'],
        'turquoise' => ['text-stone-800 bg-turquoise-200 border border-turquoise-500', 'hover:bg-turquoise-100 focus:bg-turquoise-100'],
    ][$color];
@endphp
<div
    x-data="{
        open: false,
        toggle() {
            this.open = ! this.open;
            if (this.open) this.$nextTick(() => this.$refs.menu.querySelector('[role=menuitem]')?.focus());
        },
        move(step) {
            const items = [...this.$refs.menu.querySelectorAll('[role=menuitem]')];
            items[(items.indexOf(document.activeElement) + step + items.length) % items.length]?.focus();
        },
    }"
    x-on:keydown.escape="open = false"
    x-on:click.outside="open = false"
    x-on:keydown.arrow-down.prevent="open && move(1)"
    x-on:keydown.arrow-up.prevent="open && move(-1)"
    class="relative"
>
    <button
        type="button"
        aria-haspopup="menu"
        aria-label="{{ $ariaLabel }}"
        x-bind:aria-expanded="open"
        x-on:click="toggle()"
        {{ $attributes->class(['my-3 focus:outline-none']) }}
    >
        <div @class([$labelClass])>{{ $slot }}</div>
    </button>
    <div
        x-ref="menu"
        x-show="open"
        x-cloak
        role="menu"
        aria-label="{{ $ariaLabel }}"
        class="absolute right-0 z-10 focus:outline-none"
    >
        <div
            x-show="open"
            x-transition:enter="transition ease-out duration-100"
            x-transition:enter-start="opacity-0 scale-[0.85]"
            x-transition:enter-end="opacity-100 scale-100"
            @class(['rounded-md shadow-md origin-top', $menuColor[0], $innerClass])
        >
            @foreach ($items as $index => $item)
                <button
                    type="button"
                    role="menuitem"
                    data-test="menuitem-{{ $index }}"
                    x-on:click="open = false; {{ $item['click'] }}"
                    @class([$menuColor[1], 'whitespace-nowrap w-full py-1 px-4 text-left focus:outline-none'])
                ><x-text as="span" variant="textSmMedium">{{ $item['text'] }}</x-text></button>
            @endforeach
        </div>
    </div>
</div>
