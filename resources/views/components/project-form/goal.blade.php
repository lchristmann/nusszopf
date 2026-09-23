@props(['error' => null])

<x-field-title {{ $attributes }} info="Was soll mit dem Projekt erreicht werden?">Projektziel*</x-field-title>
<x-input
    as="textarea"
    data-test="input_project-goal"
    color="lilac"
    aria-label="Projektziel"
    name="goal"
    maxlength="150"
    wire:model="goal"
    wire:blur="blurred('goal')"
    placeholder="Wie lässt sich das Ziel des Projektes in einem Satz beschreiben?"
></x-input>
<x-input-error for="goal" :message="$error" />
