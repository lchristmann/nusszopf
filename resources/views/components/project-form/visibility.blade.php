<x-field-title {{ $attributes }} info="Soll das Projekt allgemein oder nur für bestimmte Personen sichtbar sein? Bedenke, dass sobald du das Projekt veröffentlicht hast, diese Informationen frei über das Internet verfügbar sind.">Sichtbarkeit</x-field-title>
<div role="radiogroup" aria-label="Sichtbarkeit">
    <x-radiobox data-test="radio_public_project-visibility" name="visibility" value="public" wire:model="visibility">
        <x-text variant="textSmMedium">Öffentlich</x-text>
        <x-text variant="textSm">Projekt kann über Nusszopf und Suchmaschinen gefunden werden</x-text>
    </x-radiobox>
    <x-radiobox data-test="radio_private_project-visibility" name="visibility" value="private" wire:model="visibility" class="mt-4">
        <x-text variant="textSmMedium">Privat</x-text>
        <x-text variant="textSm">Projekt ist nur zugänglich für Personen, die den Projektlink kennen</x-text>
    </x-radiobox>
</div>
