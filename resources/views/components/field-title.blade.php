@props(['info' => null])

{{--
    FieldTitle (webapp/src/components/FieldTitle/FieldTitle.js): the field
    label as `Text` (default `textMd`) plus, when there is `info`, the Popover
    organism — an Info icon disclosure (`text-livid-500`, 21px) that opens a
    `bg-livid-300` card above it (`textXs`, italic, `text-livid-800`). Click
    toggles, click-outside/Escape close, as Reakit's popover did.
--}}
<div {{ $attributes->class(['flex mb-3 space-x-2']) }}>
    <x-text variant="textMd">{{ $slot }}</x-text>
    @if ($info)
        <div
            x-data="{ open: false }"
            x-on:keydown.escape.window="open = false"
            x-on:click.outside="open = false"
            class="relative"
        >
            <button
                type="button"
                aria-label="Mehr Informationen"
                x-bind:aria-expanded="open"
                x-on:click="open = ! open"
                class="focus:outline-none text-livid-500"
            >
                <x-icon name="info" :size="21" />
            </button>
            <div
                x-show="open"
                x-cloak
                x-transition.opacity.duration.150ms
                role="dialog"
                aria-label="Info"
                class="absolute z-10 mb-3 -translate-x-1/2 bottom-full left-1/2"
            >
                <div class="relative w-max max-w-xs px-4 py-3 border-2 rounded-md shadow-md text-livid-300 bg-livid-300 border-livid-300">
                    <x-text variant="textXs" class="italic text-livid-800">{{ $info }}</x-text>
                    <span aria-hidden="true" class="absolute w-3 h-3 rotate-45 -translate-x-1/2 bg-livid-300 -bottom-1.5 left-1/2"></span>
                </div>
            </div>
        </div>
    @endif
</div>
