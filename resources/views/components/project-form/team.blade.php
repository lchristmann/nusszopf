@props(['error' => null])

<x-field-title {{ $attributes }} info="Wer macht bei dem Projekt mit? Wie arbeitet ihr zusammen bzw. wollt ihr zusammen arbeiten? Wie organisiert ihr euch? Was gibt es über euch unbedingt zu wissen?">Projektteam</x-field-title>
<x-rich-text-editor
    data-test="input_project-team"
    property="team"
    label="Projektteam"
    placeholder="Was muss man über das Projektteam wissen?"
/>
<x-input-error :message="$error" />
