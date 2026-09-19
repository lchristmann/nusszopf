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
                        aria-label="Information ausblenden"
                        onclick="document.getElementById('nz-project-banner').classList.add('hidden')"
                        class="focus:outline-none"
                    ><x-icon name="x" :size="21" class="absolute top-0 right-0" /></button>
                </div>
            </x-frame>
        </div>
    @endif

    {{-- pages/projects/[id].js. VisitorCounter (ProjectAnalytics) and the project-report link are out of scope for this slice. --}}
    <x-framed-grid-card class="lg:mt-12">
        <x-frame class="bg-lilac-300 lg:bg-steel-100">
            <x-framed-grid-card.header class="bg-lilac-300">
                <div class="flex flex-col flex-wrap lg:flex-row lg:justify-between">
                    <div class="lg:pr-12 lg:w-9/12">
                        <x-text as="h1" variant="textLg" class="mb-2 hyphens-auto">{{ $project->title }}</x-text>
                        <x-text variant="textSm" class="max-w-xl">{{ $project->goal }}</x-text>
                        <div class="flex flex-col w-full mt-4 lg:mt-3 sm:flex-row sm:items-center">
                            <div class="flex items-center sm:mr-8" data-test="location_project-detail">
                                <x-icon name="map-pin" :size="20" class="mr-2" />
                                @if ($location['link'])
                                    <a
                                        href="{{ $location['link'] }}"
                                        title="Zu OpenStreetMap"
                                        aria-label="Zu OpenStreetMap"
                                        rel="noopener noreferrer"
                                        target="_blank"
                                        class="nz-text-sm cursor-pointer border-b-2 nz-link-lilac"
                                    >{{ $location['city'] }}</a>
                                @else
                                    <x-text variant="textSm">{{ $location['city'] }}</x-text>
                                @endif
                            </div>
                            <div class="flex items-center mt-2 sm:mt-0" data-test="period_project-detail">
                                <x-icon name="calendar" :size="20" class="mr-2" />
                                <x-text variant="textSm">{{ $period }}</x-text>
                            </div>
                        </div>
                    </div>
                    <div class="flex mt-6 mb-2.5 lg:mt-0 lg:w-3/12 lg:mt-2 lg:items-end lg:flex-col lg:mb-0">
                        {{-- Personal contact: the owner's own mailto, as historically. Contact "über Nusszopf" historically opened a contact form (ContactDialog, out of scope here — docs/rewrite/second-slice.md); until then it is a mailto to the Nusszopf address. --}}
                        <x-button as="a" href="{{ $mailto }}" data-test="btn_contact_project-detail" color="lilac" size="small" class="mr-5 lg:mr-0 lg:mb-3">
                            <x-slot:iconLeft><x-icon name="send" :size="21" class="mt-px mr-2 -ml-1" /></x-slot:iconLeft>
                            Kontaktieren
                        </x-button>
                        <x-button data-test="btn_share_project-detail" size="small" color="lilac" x-on:click="nzShare(@js($shareTitle))">
                            <x-slot:iconLeft><x-icon name="share-2" :size="21" class="mt-px mr-2 -ml-1" /></x-slot:iconLeft>
                            Teilen
                        </x-button>
                    </div>
                </div>
            </x-framed-grid-card.header>
        </x-frame>

        <x-frame class="bg-white lg:bg-steel-100">
            <x-framed-grid-card.body gap="medium" class="grid-flow-row bg-white">
                <x-framed-grid-card.body-col variant="twoCols" class="lg:col-start-2 lg:pr-4">
                    <div class="mt-10 lg:mt-0" data-test="description_project-detail">
                        <x-text class="mb-3" variant="textLg">Projektbeschreibung</x-text>
                        <div class="text-lg">{!! $descriptionHtml !!}</div>
                    </div>
                    @if ($project->team)
                        <div class="mt-10" data-test="team_project-detail">
                            <x-text class="mb-3" variant="textLg">Projektteam</x-text>
                            <div class="text-lg">{!! $teamHtml !!}</div>
                        </div>
                    @endif
                    @if ($project->motto)
                        <div class="mt-10" data-test="motto_project-detail">
                            <x-text class="mb-3" variant="textLg">Projektmotto</x-text>
                            <x-text variant="textSm">{{ $project->motto }}</x-text>
                        </div>
                    @endif
                </x-framed-grid-card.body-col>

                <x-framed-grid-card.body-col variant="twoCols" class="row-start-1 lg:row-start-auto lg:pl-4 text-stone-800">
                    <x-text class="mb-4" variant="textLg">Projektgesuche</x-text>
                    {{-- ProjectRequests arrive in a later slice; until then every project has none. --}}
                    <x-info-card class="mt-2">Alles zopfig! Derzeit gibt es keine Gesuche.</x-info-card>

                    {{-- Avatar, `project` variant. The initial-on-grey circle replaces the historical ui-avatars.com image (an external service), same colors. --}}
                    <div class="flex items-center mt-16 lg:mt-14">
                        <div class="relative flex items-center justify-center flex-shrink-0 overflow-hidden text-2xl font-medium uppercase border-2 rounded-full w-14 h-14 border-steel-700" style="background-color: #cfd8dc; color: #37474f" aria-hidden="true">{{ mb_substr($project->user->name, 0, 1) }}</div>
                        <div class="ml-5">
                            <x-text data-test="username_avatar" variant="textSmMedium">{{ \Illuminate\Support\Str::limit($project->user->name, 33, '...') }}</x-text>
                            <x-text variant="textSm">Aktualisiert am {{ $project->updated_at->format('j.n.Y') }}</x-text>
                        </div>
                    </div>
                </x-framed-grid-card.body-col>
            </x-framed-grid-card.body>
        </x-frame>
    </x-framed-grid-card>
</div>
