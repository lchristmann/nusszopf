<div>
    <x-frame class="bg-moss-300 py-8">
        <x-text as="h1" variant="titleMd">Suche</x-text>
        <div class="mt-4">
            <x-input
                wire:model.live.debounce.500ms="query"
                name="query"
                color="moss"
                aria-label="Projekte durchsuchen"
                placeholder="Projekte durchsuchen"
            />
        </div>
    </x-frame>

    <x-frame class="py-10" fluid>
        @if ($hits->isEmpty())
            <div class="bg-livid-300 rounded-lg p-8 max-w-xl mx-auto text-center">
                <x-text as="p" variant="textMd">Keine Ergebnisse gefunden.</x-text>
                <x-button as="a" href="{{ route('projects.create') }}" color="lilac" class="mt-4">
                    Eigenes Projekt erstellen
                </x-button>
            </div>
        @else
            {{-- Masonry via CSS columns, not a JS library — docs/architecture/mapping.md
                 flags this as achievable without a client-side dependency. --}}
            <div class="columns-1 sm:columns-2 lg:columns-3 gap-4 [column-fill:_balance]">
                @foreach ($hits as $hit)
                    <a
                        href="{{ route('projects.show', $hit) }}"
                        class="block break-inside-avoid mb-4 border-2 border-lilac-300 rounded-lg p-5 hover:ring-2 hover:ring-lilac-300"
                    >
                        <x-text as="h2" variant="textLgSemi">{{ $hit->title }}</x-text>
                        <x-text as="p" variant="textSm" class="mt-2 text-steel-600">{{ $hit->goal }}</x-text>
                    </a>
                @endforeach
            </div>
        @endif
    </x-frame>
</div>
