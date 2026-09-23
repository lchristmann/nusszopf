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
<div
    x-data="{ openRequest: null, contactOpen: false }"
    x-on:keydown.escape.window="openRequest = null; contactOpen = false"
>
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
                            aria-label="Klicke hier, um das Projekt zu bearbeiten"
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

    {{-- pages/projects/[id].js. --}}
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
                        {{--
                            `handleContact` (`pages/projects/[id].js`): a personal contact is a plain
                            `mailto:` to the owner's own address, no app involvement; "Über Nusszopf"
                            opens the contact form instead (`ContactDialog.js`), the slice-2 mailto
                            scaffold it replaces.
                        --}}
                        @if ($project->hasPersonalContact())
                            <x-button as="a" href="{{ $mailto }}" data-test="btn_contact_project-detail" color="lilac" size="small" class="mr-5 lg:mr-0 lg:mb-3">
                                <x-slot:iconLeft><x-icon name="send" :size="21" class="mt-px mr-2 -ml-1" /></x-slot:iconLeft>
                                Kontaktieren
                            </x-button>
                        @else
                            <x-button type="button" wire:click="openContact" x-on:click="contactOpen = true; openRequest = null" data-test="btn_contact_project-detail" color="lilac" size="small" class="mr-5 lg:mr-0 lg:mb-3">
                                <x-slot:iconLeft><x-icon name="send" :size="21" class="mt-px mr-2 -ml-1" /></x-slot:iconLeft>
                                Kontaktieren
                            </x-button>
                        @endif
                        <x-button data-test="btn_share_project-detail" size="small" color="lilac" data-share-title="{{ $shareTitle }}" x-on:click="nzShare($el.dataset.shareTitle)">
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
                        <x-text as="h2" class="mb-3" variant="textLg">Projektbeschreibung</x-text>
                        <div class="text-lg">{!! $descriptionHtml !!}</div>
                    </div>
                    @if ($project->team)
                        <div class="mt-10" data-test="team_project-detail">
                            <x-text as="h2" class="mb-3" variant="textLg">Projektteam</x-text>
                            <div class="text-lg">{!! $teamHtml !!}</div>
                        </div>
                    @endif
                    @if ($project->motto)
                        <div class="mt-10" data-test="motto_project-detail">
                            <x-text as="h2" class="mb-3" variant="textLg">Projektmotto</x-text>
                            <x-text variant="textSm">{{ $project->motto }}</x-text>
                        </div>
                    @endif

                    {{--
                        VisitorCounter.js: four zero-padded digit boxes ("+9999"
                        past that cap), server-counted only (BUG-001's fix).
                    --}}
                    @php
                        $digits = $views === null
                            ? ['0', '0', '0', '0']
                            : ($views > 9999 ? ['+', '9', '9', '9', '9'] : str_split(str_pad((string) $views, 4, '0', STR_PAD_LEFT)));
                    @endphp
                    {{-- Decision A-7: one name for the counter; its digit boxes are decoration. --}}
                    <div class="inline-flex items-center py-2 px-2.5 bg-lilac-150 rounded-md mt-12 mb-3 md:mb-0" data-test="visitor-counter_project-detail" role="img" aria-label="{{ ltrim(implode('', $digits), '0') ?: '0' }} Aufrufe">
                        <x-icon name="eye" :size="22" class="mr-2" />
                        @foreach ($digits as $digit)
                            <div class="flex items-center justify-center w-6 h-6 mx-0.5 rounded-md bg-lilac-300">
                                <x-text variant="textXs" class="font-medium">{{ $digit }}</x-text>
                            </div>
                        @endforeach
                    </div>
                </x-framed-grid-card.body-col>

                <x-framed-grid-card.body-col variant="twoCols" class="row-start-1 lg:row-start-auto lg:pl-4 text-stone-800">
                    <x-text as="h2" class="mb-4" variant="textLg">Projektgesuche</x-text>
                    {{-- Each request is a card opening its dialog; `openRequest` is the id of the one shown
                         (state lives on the root element, shared with the contact dialog below). --}}
                    <div>
                        @forelse ($requests as $request)
                            <x-request-card
                                variant="view"
                                :title="$request->title"
                                :category="$request->category"
                                :created-at="$request->created_at->format('j.n.Y')"
                                x-on:click="openRequest = '{{ $request->id }}'"
                                :class="$loop->index > 0 ? 'mt-4 lg:mt-3' : ''"
                            />
                            <x-request-view-dialog
                                :request="$request"
                                :created-at="$request->created_at->format('j.n.Y')"
                                :contact-href="$mailto"
                                :has-personal-contact="$project->hasPersonalContact()"
                                :open="'openRequest === \''.$request->id.'\''"
                                close="openRequest = null"
                            />
                        @empty
                            <x-info-card class="mt-2">Alles zopfig! Derzeit gibt es keine Gesuche.</x-info-card>
                        @endforelse
                    </div>

                    @unless ($project->hasPersonalContact())
                        <x-contact-dialog :project="$project" open="contactOpen" close="contactOpen = false" />
                    @endunless

                    <x-avatar variant="project" :user="$project->user" :project="$project" class="mt-16 lg:mt-14" />
                </x-framed-grid-card.body-col>
            </x-framed-grid-card.body>
        </x-frame>
    </x-framed-grid-card>

    {{-- pages/projects/[id].js: report link, `mailto:` with the project id appended to the subject. --}}
    <x-frame class="pt-4 pb-16 text-center lg:pb-20 lg:text-right bg-steel-100">
        <a
            href="{{ $reportMailto }}"
            title="Projekt {{ config('nusszopf.contact_email') }} melden"
            data-test="link_report_project-detail"
            {{-- `Link variant="button"` renders a clean base-size Button: `py-2 px-4` around a centred row. --}}
            class="inline-block font-medium text-lg py-2 px-4 underline cursor-pointer outline-none focus:outline-none lg:pr-2.5"
        >
            <div class="flex items-center justify-center">
                <x-icon name="alert-triangle" :size="21" class="mr-2" />
                <span><x-text as="span" variant="textSmMedium">Projekt melden</x-text></span>
            </div>
        </a>
    </x-frame>
</div>
