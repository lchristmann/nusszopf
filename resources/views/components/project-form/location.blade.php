@props(['remote', 'searchTerm', 'options' => [], 'searchTermError' => null, 'dataError' => null])

{{--
    LocationField.js + the Combobox organism (@reach/combobox). Ortsunabhängig /
    Ortsgebunden radios, then a place-search combobox: typing clears any
    selected place and (debounced 500ms) fetches suggestions; a suggestion
    must be picked for the field to validate; Enter never submits the form;
    the right-hand element is a Search icon, or a clear (X) button once there is
    text. Suggestions come from LocationSearch (LocationIQ, server-side).
--}}
<x-field-title {{ $attributes }} info="Ist das Projekt an einen bestimmten Ort gebunden?">Projektort*</x-field-title>
<div role="radiogroup" aria-label="Projektort">
    <x-radiobox data-test="radio_remote_project-location" name="location.remote" value="1" :checked="$remote" wire:click="$set('location.remote', true)">
        <x-text variant="textSmMedium">Ortsunabhängig</x-text>
    </x-radiobox>
    <x-radiobox data-test="radio_fixed_project-location" name="location.remote" value="0" :checked="! $remote" wire:click="$set('location.remote', false)" class="mt-4">
        <x-text variant="textSmMedium">Ortsgebunden</x-text>
        <x-text variant="textSm" @class(['opacity-50' => $remote])>Wähle hierzu einen Ort aus der Suche aus:</x-text>
    </x-radiobox>
</div>
<div
    class="mt-2 ml-8 text-lilac-800"
    x-data="{ active: -1, count() { return this.$refs.list ? this.$refs.list.children.length : 0 } }"
>
    <div class="relative">
        <x-input
            x-ref="input"
            id="postalcode"
            tabindex="0"
            data-test="combobox_project-location"
            name="location.searchTerm"
            role="combobox"
            aria-label="Projektort"
            aria-autocomplete="list"
            aria-expanded="{{ count($options) > 0 ? 'true' : 'false' }}"
            autocomplete="off"
            color="lilac"
            placeholder="Ort"
            wire:model.live.debounce.500ms="location.searchTerm"
            wire:blur="blurred('location.searchTerm')"
            :disabled="$remote"
            class="pr-12"
            x-on:keydown.enter.prevent="if (active >= 0) $refs.list.children[active]?.click()"
            x-on:keydown.arrow-down.prevent="if (count()) active = (active + 1) % count()"
            x-on:keydown.arrow-up.prevent="if (count()) active = (active - 1 + count()) % count()"
            x-on:keydown.escape="$wire.set('locationOptions', [], false); active = -1"
        />
        @if (strlen($searchTerm) > 0)
            <button
                type="button"
                aria-label="Eingabe löschen"
                wire:click="clearLocation"
                x-on:click="$refs.input.focus()"
                @disabled($remote)
                class="absolute top-0 right-0 flex items-center h-full px-3 outline-none cursor-pointer focus:outline-none disabled:cursor-default disabled:pointer-events-none disabled:opacity-50"
            >
                <x-icon name="x" />
            </button>
        @else
            <div class="absolute top-0 right-0 flex items-center h-full px-3 {{ $remote ? 'opacity-50' : '' }}"><x-icon name="search" /></div>
        @endif

        @if (count($options) > 0 && ! $remote)
            <div class="absolute left-0 right-0 z-10 py-0.5">
                <div class="text-sm bg-white border-2 rounded-lg shadow-md border-lilac-800">
                    <ul x-ref="list" role="listbox" class="p-0 m-0 list-none select-none">
                        @foreach ($options as $index => $option)
                            <li
                                role="option"
                                data-test="option_project-location"
                                aria-selected="false"
                                x-bind:aria-selected="active === {{ $index }}"
                                x-bind:class="{ 'bg-lilac-100': active === {{ $index }} }"
                                x-on:mousedown.prevent
                                x-on:mouseenter="active = {{ $index }}"
                                wire:click="selectLocation(@js($option['key']))"
                                wire:key="location-option-{{ $option['key'] }}"
                                class="px-3 py-2 m-0 bg-white rounded-lg cursor-pointer hover:bg-lilac-100 focus:outline-none focus:bg-lilac-100"
                            >{{ $option['value'] }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif
    </div>
    <x-input-error :message="$searchTermError" />
    <x-input-error :message="$dataError" />
</div>
