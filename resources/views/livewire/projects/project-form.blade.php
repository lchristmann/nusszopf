<x-framed-grid-card>
    <x-frame class="bg-lilac-300 rounded-t-lg">
        <x-framed-grid-card.header>
            <x-text as="h1" variant="titleMd">
                {{ $project ? 'Projekt bearbeiten' : 'Projekt erstellen' }}
            </x-text>
        </x-framed-grid-card.header>
    </x-frame>

    <x-frame class="bg-white rounded-b-lg">
        <x-framed-grid-card.body gap="large">
            <x-framed-grid-card.body-col variant="oneCol">
                <form wire:submit="save" class="space-y-8">
                    <div>
                        <x-field-title info="Gib deinem Projekt einen Titel.">Projekttitel*</x-field-title>
                        <x-input
                            wire:model="title"
                            name="title"
                            data-test="input_project-title"
                            color="lilac"
                            maxlength="40"
                            aria-label="Projekttitel"
                            placeholder="Wie heißt das Projekt?"
                            class="mt-2"
                        />
                        <x-input-error :message="$errors->first('title')" />
                    </div>

                    <div>
                        <x-field-title info="Was soll mit dem Projekt erreicht werden?">Projektziel*</x-field-title>
                        <x-input
                            as="textarea"
                            wire:model="goal"
                            name="goal"
                            data-test="input_project-goal"
                            color="lilac"
                            maxlength="150"
                            rows="2"
                            aria-label="Projektziel"
                            placeholder="Wie lässt sich das Ziel des Projektes in einem Satz beschreiben?"
                            class="mt-2"
                        />
                        <x-input-error :message="$errors->first('goal')" />
                    </div>

                    <div>
                        <x-field-title info="Worum geht es bei dem Projekt? Wie kam es zu dem Projekt?">Projektbeschreibung*</x-field-title>
                        {{--
                            Plain textarea, not the historical rich-text
                            editor — deliberately temporary
                            (docs/rewrite/architecture-decisions.md, "Rich-
                            text editor replacement for Slate" is Adopted
                            for the second slice's wizard, not this form).
                        --}}
                        <x-input
                            as="textarea"
                            wire:model="description"
                            name="description"
                            data-test="input_project-description"
                            color="lilac"
                            rows="6"
                            aria-label="Projektbeschreibung"
                            placeholder="Was muss man über das Projekt wissen?"
                            class="mt-2"
                        />
                        <x-input-error :message="$errors->first('description')" />
                    </div>

                    <div>
                        <x-field-title info="Soll das Projekt allgemein oder nur für bestimmte Personen sichtbar sein?">Sichtbarkeit</x-field-title>
                        <div class="mt-2 space-y-2">
                            <label class="flex items-start gap-3 cursor-pointer">
                                <input type="radio" wire:model="visibility" name="visibility" value="private" data-test="radio_visibility-private" class="mt-1 border-2 border-steel-700" />
                                <span>
                                    <x-text as="span" variant="textSmMedium" class="block">Privat</x-text>
                                    <x-text as="span" variant="textXs" class="block text-steel-500">Projekt ist nur zugänglich für Personen, die den Projektlink kennen</x-text>
                                </span>
                            </label>
                            <label class="flex items-start gap-3 cursor-pointer">
                                <input type="radio" wire:model="visibility" name="visibility" value="public" data-test="radio_visibility-public" class="mt-1 border-2 border-steel-700" />
                                <span>
                                    <x-text as="span" variant="textSmMedium" class="block">Öffentlich</x-text>
                                    <x-text as="span" variant="textXs" class="block text-steel-500">Projekt kann über Nusszopf und Suchmaschinen gefunden werden</x-text>
                                </span>
                            </label>
                        </div>
                        <x-input-error :message="$errors->first('visibility')" />
                    </div>

                    <div class="text-center">
                        <x-button type="submit" color="lilac" data-test="btn_save_project-form">
                            {{ $project ? 'Speichern' : 'Erstellen' }}
                        </x-button>
                    </div>
                </form>
            </x-framed-grid-card.body-col>
        </x-framed-grid-card.body>
    </x-frame>
</x-framed-grid-card>
