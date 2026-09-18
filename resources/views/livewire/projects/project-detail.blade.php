<x-framed-grid-card>
    <x-frame class="bg-lilac-300 rounded-t-lg">
        <x-framed-grid-card.header>
            <x-text as="h1" variant="titleMd">{{ $project->title }}</x-text>
            <x-text as="p" variant="textLgThin" class="mt-2">{{ $project->goal }}</x-text>

            @if (auth()->id() === $project->user_id && $project->visibility === 'private')
                <x-text as="p" variant="textXs" class="mt-4 text-lilac-800">
                    Nur für dich sichtbar &mdash; noch nicht veröffentlicht.
                </x-text>
            @endif
        </x-framed-grid-card.header>
    </x-frame>

    <x-frame class="bg-white rounded-b-lg">
        <x-framed-grid-card.body gap="large">
            <x-framed-grid-card.body-col variant="twoCols">
                <x-text as="h2" variant="titleSm">Worum geht es?</x-text>
                <x-text as="p" variant="textSm" class="mt-4 whitespace-pre-line">{{ $project->description }}</x-text>
            </x-framed-grid-card.body-col>

            <x-framed-grid-card.body-col variant="twoCols">
                <x-text as="h2" variant="titleSm">Erstellt von</x-text>
                <x-text as="p" variant="textSm" class="mt-4">{{ $project->user->name }}</x-text>
            </x-framed-grid-card.body-col>
        </x-framed-grid-card.body>
    </x-frame>
</x-framed-grid-card>
