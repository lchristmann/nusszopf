@props(['flexible', 'fromError' => null, 'toError' => null])

{{-- PeriodField.js: Flexibel / Festgelegt + two `dd.mm.yyyy` inputs, disabled (and their labels dimmed) while flexible. --}}
<x-field-title {{ $attributes }} info="Gibt es einen definierten Zeitraum, in welchem das Projekt stattfindet?">Projektzeitraum*</x-field-title>
<div role="radiogroup" aria-label="Sichtbarkeit">
    <x-radiobox data-test="radio_flexible_project-period" name="period.flexible" value="1" :checked="$flexible" wire:click="$set('period.flexible', true)">
        <x-text variant="textSmMedium">Flexibel</x-text>
    </x-radiobox>
    <x-radiobox data-test="radio_fixed_project-period" name="period.flexible" value="0" :checked="! $flexible" wire:click="$set('period.flexible', false)" class="mt-4">
        <x-text variant="textSmMedium">Festgelegt:</x-text>
    </x-radiobox>
</div>
<div class="mt-4 space-y-4">
    @foreach ([['from', 'Von', $fromError], ['to', 'Bis', $toError]] as [$field, $label, $error])
        <div class="flex">
            <x-text variant="textXs" @class(['w-12 mt-3 uppercase', 'opacity-50' => $flexible])>{{ $label }}</x-text>
            <div class="w-full lg:max-w-xs">
                <x-input
                    data-test="input_{{ $field }}_project-period"
                    name="period.{{ $field }}"
                    aria-label="{{ $label }}"
                    color="lilac"
                    wire:model="period.{{ $field }}"
                    wire:blur="blurred('period.{{ $field }}')"
                    maxlength="10"
                    placeholder="dd.mm.yyyy"
                    type="text"
                    :disabled="$flexible"
                />
                <x-input-error :message="$error" />
            </div>
        </div>
    @endforeach
</div>
