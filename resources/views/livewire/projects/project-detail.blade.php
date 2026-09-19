{{--
    docs/rewrite/bugs.md is silent on this (not a historical defect) — this
    reproduces the historical owner-only `Banner` container
    (`web-nusszopf/projects/webapp/src/containers/projects/Banner/Banner.js`,
    `assets/data/banner.data.js`), verbatim copy, both visibility variants,
    dismiss control, and edit link — rendered above the FramedGridCard,
    matching `pages/projects/[id].js:144`. Dismiss is per-view local state
    (a plain onclick toggle, matching CLAUDE.md's "smallest client-side
    solution" — the historical dismiss was itself only React component
    state, not persisted either).
--}}
<div>
    {{-- Livewire requires exactly one root element — the banner and the
         card below share this wrapping div, not two Blade-root siblings. --}}
    @if (auth()->id() === $project->user_id)
        <div id="nz-project-banner" class="py-4 bg-livid-300">
            <x-frame class="text-livid-800">
                <div class="relative flex items-center">
                    <x-text as="p" variant="textSm" class="pr-18">
                        {{ $project->visibility === 'public'
                            ? 'So sieht das Projekt für andere Nusszopfer:innen aus.'
                            : 'Das Projekt ist gerade nur für dich sichtbar!' }}
                        <a
                            href="{{ route('projects.edit', $project) }}"
                            title="Projekt bearbeiten"
                            aria-label="Projekt bearbeiten"
                            class="underline"
                        >Klicke hier</a>, wenn Du Projekt und Gesuche bearbeiten willst.
                    </x-text>
                    <button
                        type="button"
                        aria-label="Banner schließen"
                        onclick="document.getElementById('nz-project-banner').classList.add('hidden')"
                        class="absolute top-0 right-0 text-xl leading-none"
                    >&times;</button>
                </div>
            </x-frame>
        </div>
    @endif

    <x-framed-grid-card>
    <x-frame class="bg-lilac-300 rounded-t-lg">
        <x-framed-grid-card.header>
            <x-text as="h1" variant="titleMd">{{ $project->title }}</x-text>
            <x-text as="p" variant="textLgThin" class="mt-2">{{ $project->goal }}</x-text>
        </x-framed-grid-card.header>
    </x-frame>

    <x-frame class="bg-white rounded-b-lg">
        {{-- pages/projects/[id].js: <FramedGridCard.Body gap="medium">, not large. --}}
        <x-framed-grid-card.body gap="medium">
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
