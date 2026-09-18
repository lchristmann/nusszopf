<x-framed-grid-card>
    <x-frame class="bg-lilac-300 rounded-t-lg">
        <x-framed-grid-card.header>
            <div class="flex items-center justify-between">
                <x-text as="h1" variant="titleMd">Meine Projekte</x-text>
                <x-button as="a" href="{{ route('projects.create') }}" color="lilac">Projekt erstellen</x-button>
            </div>
        </x-framed-grid-card.header>
    </x-frame>

    <x-frame class="bg-white rounded-b-lg">
        <x-framed-grid-card.body>
            <x-framed-grid-card.body-col variant="oneCol">
                @if ($projects->isEmpty())
                    <div class="bg-lilac-100 rounded-lg p-8 text-center">
                        <x-text as="p" variant="textMd">Du hast noch keine Projekte.</x-text>
                        <x-button as="a" href="{{ route('projects.create') }}" color="lilac" class="mt-4">Erstes Projekt erstellen</x-button>
                    </div>
                @else
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach ($projects as $project)
                            <div class="border-2 border-lilac-300 rounded-lg p-5">
                                <x-text as="h2" variant="textLgSemi">{{ $project->title }}</x-text>
                                <x-text as="p" variant="textXs" class="mt-1 text-steel-600">
                                    {{ $project->visibility === 'public' ? 'Öffentlich' : 'Privat' }}
                                </x-text>
                                <div class="mt-4 flex gap-3">
                                    <a href="{{ route('projects.show', $project) }}" class="underline">
                                        <x-text as="span" variant="textSm">Ansehen</x-text>
                                    </a>
                                    <a href="{{ route('projects.edit', $project) }}" class="underline">
                                        <x-text as="span" variant="textSm">Bearbeiten</x-text>
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-framed-grid-card.body-col>
        </x-framed-grid-card.body>
    </x-frame>
</x-framed-grid-card>
