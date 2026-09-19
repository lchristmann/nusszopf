@props(['error' => null])

<x-field-title {{ $attributes }} info="Worum geht es bei dem Projekt? Wie kam es zu dem Projekt? Welche/s Problem/e sollen mit dem Projekt gelöst werden? Was steht schon fest für den Projektprozess?">Projektbeschreibung*</x-field-title>
<x-rich-text-editor
    data-test="input_project-description"
    property="description"
    label="Projektbeschreibung"
    placeholder="Was muss man über das Projekt wissen?"
/>
<x-input-error :message="$error" />
