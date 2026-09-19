<div>
    {{--
        pages/search.js: <Frame size="large" className="... bg-moss-300
        text-moss-800">, not the default frame size/no text color
        (docs/rewrite/golden-master-review.md, "Search screen structure").
    --}}
    <x-frame size="large" class="bg-moss-300 text-moss-800 py-8">
        <div class="max-w-3xl mx-auto">
            <x-text as="h1" variant="titleMd">Suche</x-text>
            {{--
                Historical mechanism (verified against SearchInput.js in this
                verification pass): search fires on explicit submit (Enter,
                or the search-icon click), never live-as-you-type — corrected
                from an earlier wire:model.live.debounce implementation that
                queried on every keystroke instead.
            --}}
            <form wire:submit="search" class="mt-4 flex gap-2">
                <x-input
                    wire:model="query"
                    name="query"
                    data-test="input_search"
                    color="moss"
                    aria-label="Projekte durchsuchen"
                    placeholder="Projekte durchsuchen"
                />
                <x-button type="submit" color="moss" data-test="btn_search" aria-label="Suchen">
                    &#128269;
                </x-button>
            </form>
        </div>
    </x-frame>

    {{-- pages/search.js:28 — <Frame className="flex-1 h-full my-8 break-all" size="large">, capped width, not full-bleed. --}}
    <x-frame size="large" class="flex-1 h-full my-8 break-all">
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
            <div class="columns-1 sm:columns-2 lg:columns-3 gap-4 [column-fill:_balance]" data-test="search-results">
                @foreach ($hits as $hit)
                    <a
                        href="{{ route('projects.show', $hit) }}"
                        data-test="card_search-hit"
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
