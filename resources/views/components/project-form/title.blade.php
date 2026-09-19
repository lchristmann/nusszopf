@props(['error' => null])

<x-field-title info="Gib deinem Projekt einen Titel.">Projekttitel*</x-field-title>
<x-input
    data-test="input_project-title"
    name="title"
    color="lilac"
    aria-label="Projekttitel"
    maxlength="40"
    wire:model="title"
    wire:blur="blurred('title')"
    placeholder="Wie heißt das Projekt?"
/>
<x-input-error :message="$error" />
