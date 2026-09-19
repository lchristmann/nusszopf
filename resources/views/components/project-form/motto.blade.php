@props(['error' => null])

<x-field-title {{ $attributes }} info="Wie lässt sich eure Arbeitsweise beschreiben?">Projektmotto</x-field-title>
<x-input
    as="textarea"
    data-test="input_project-motto"
    aria-label="Projektmotto"
    color="lilac"
    name="motto"
    maxlength="200"
    wire:model="motto"
    wire:blur="blurred('motto')"
    placeholder="Was ist euer Projektmotto?"
></x-input>
<x-input-error :message="$error" />
