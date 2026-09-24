@props(['property', 'label', 'placeholder' => '', 'color' => 'lilac', 'blurAction' => 'blurred'])

{{--
    RichTextEditor.organism.js (`RichTextEditor.theme.js`, colors `lilac` and
    `stone`): a bordered box with the six-tool toolbar row (`bg-lilac-300` /
    `bg-stone-400`) and the editable area below.
    Owned by resources/js/rich-text-editor.js; `wire:ignore` keeps Livewire's
    morphing away from the editor DOM. The aria-labels are the historical
    ones with the swapped list labels corrected (BUG-024).
--}}
@php
    // ThemeColor (RichTextEditor.theme.js): `text`, `hover`, `active` per color.
    [$text, $hover, $active] = $color === 'stone'
        ? ['text-stone-800', 'hover:bg-stone-300', 'bg-stone-300']
        : ['text-lilac-800', 'hover:bg-lilac-100', 'bg-lilac-100'];
@endphp
<div
    wire:ignore
    x-data="nzRichText({ property: @js($property), label: @js($label), placeholder: @js($placeholder), blurAction: @js($blurAction) })"
    {{ $attributes->class([
        'overflow-hidden border-2 rounded-md ring-2 ring-transparent',
        'border-lilac-800 hover:ring-lilac-800/25' => $color === 'lilac',
        'nz-rte-stone border-stone-800 hover:ring-stone-800/25' => $color === 'stone',
    ]) }}
>
    {{-- P-6, PERF-01: TipTap is its own chunk; start fetching it with the page instead of when Alpine starts. --}}
    <link rel="modulepreload" href="{{ \Illuminate\Support\Facades\Vite::asset('resources/js/lazy/tiptap.js') }}">
    <div @class(['flex items-center px-1 py-1', 'bg-lilac-300' => $color === 'lilac', 'bg-stone-400' => $color === 'stone']) role="toolbar" aria-label="Textformatierung">
        @foreach ([
            ['bold', 'Schrift dick', 'bold', 'p-2 mx-1'],
            ['italic', 'Schrift kursiv', 'italic', 'p-2 mx-1'],
            ['underline', 'Schrift unterstrich', 'underline', 'p-2 mx-1'],
            ['bulletList', 'Liste ungeordnet', 'list', 'mx-2 p-2'],
            ['orderedList', 'Liste geordnet', 'list-ordered', 'mx-2 p-2'],
        ] as [$command, $aria, $icon, $spacing])
            <button
                type="button"
                aria-label="{{ $aria }}"
                title="{{ $aria }}"
                x-on:mousedown.prevent="toggle('{{ $command }}')"
                x-on:keydown.enter.prevent="toggle('{{ $command }}')"
                x-on:keydown.space.prevent="toggle('{{ $command }}')"
                x-bind:class="{ '{{ $active }}': isActive('{{ $command }}') }"
                class="{{ $spacing }} rounded-full transition-colors duration-150 ease-out {{ $text }} {{ $hover }}"
            >
                <x-icon :name="$icon" :size="18" :stroke-width="3" />
            </button>
        @endforeach
        <button
            type="button"
            aria-label="Verlinkung"
            title="Verlinkung"
            x-on:mousedown.prevent="promptLink()"
            x-on:keydown.enter.prevent="promptLink()"
            x-on:keydown.space.prevent="promptLink()"
            x-bind:class="{ '{{ $active }}': isActive('link') }"
            class="mx-2 p-2 rounded-full transition-colors duration-150 ease-out {{ $text }} {{ $hover }}"
        >
            <x-icon name="link" :size="18" :stroke-width="3" />
        </button>
    </div>
    <div x-ref="editor" data-test="input_editor"></div>
</div>
