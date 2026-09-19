@props(['property', 'label', 'placeholder' => ''])

{{--
    RichTextEditor.organism.js (color `lilac`): a bordered box with the
    six-tool toolbar row on `bg-lilac-300` and the editable area below.
    Owned by resources/js/rich-text-editor.js; `wire:ignore` keeps Livewire's
    morphing away from the editor DOM. The aria-labels are the historical
    ones with the swapped list labels corrected (BUG-024).
--}}
<div
    wire:ignore
    x-data="nzRichText({ property: @js($property), label: @js($label), placeholder: @js($placeholder) })"
    {{ $attributes->class(['overflow-hidden border-2 rounded-md border-lilac-800 ring-2 ring-transparent hover:ring-lilac-800/25']) }}
>
    <div class="flex items-center px-1 py-1 bg-lilac-300" role="toolbar" aria-label="Textformatierung">
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
                x-bind:class="{ 'bg-lilac-100': isActive('{{ $command }}') }"
                class="{{ $spacing }} rounded-full transition-colors duration-150 ease-out text-lilac-800 hover:bg-lilac-100"
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
            x-bind:class="{ 'bg-lilac-100': isActive('link') }"
            class="mx-2 p-2 rounded-full transition-colors duration-150 ease-out text-lilac-800 hover:bg-lilac-100"
        >
            <x-icon name="link" :size="18" :stroke-width="3" />
        </button>
    </div>
    <div x-ref="editor" data-test="input_editor"></div>
</div>
