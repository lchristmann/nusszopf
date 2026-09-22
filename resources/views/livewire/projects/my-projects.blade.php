{{--
    pages/user/projects.js. `wire:init="load"` mirrors the Search screen
    (`app/Livewire/Search/Search.php`): the shell renders first, then a
    follow-up request fetches the grid, so `ProjectsSkeleton` is observable —
    a full-page Livewire component otherwise renders its data synchronously
    on the very first request.
--}}
<div wire:init="load">
    <x-framed-grid-card>
        {{-- pages/user/projects.js: headerColor="bg-steel-200 lg:bg-steel-100", not lilac. --}}
        <x-frame class="bg-steel-200 rounded-t-lg">
            <x-framed-grid-card.header>
                <div class="flex flex-col lg:flex-row sm:justify-between lg:items-center">
                    <x-text as="h1" variant="titleMd">Meine Projekte</x-text>
                    {{-- The button component always sets `inline-flex` itself, which would otherwise beat a plain `hidden` utility on source order — wrapped instead. --}}
                    <div class="hidden lg:block">
                        <x-button
                            as="a"
                            href="{{ route('projects.create') }}"
                            color="lilac"
                            class="bg-lilac-200"
                            data-test="route_create-project_projects-page"
                        >
                            <x-slot:iconLeft><x-icon name="plus-circle" class="mr-2 -ml-1" /></x-slot:iconLeft>
                            Projekt starten
                        </x-button>
                    </div>
                </div>
            </x-framed-grid-card.header>
        </x-frame>

        <x-frame class="bg-white rounded-b-lg">
            <x-framed-grid-card.body gap="medium">
                <x-framed-grid-card.body-col variant="oneCol" class="text-center lg:hidden">
                    <x-button
                        as="a"
                        href="{{ route('projects.create') }}"
                        color="lilac"
                        size="large"
                        class="mb-8 md:mb-10 bg-lilac-200"
                        data-test="route_create-project_projects-page"
                    >
                        <x-slot:iconLeft><x-icon name="plus-circle" class="mr-2 -ml-1" /></x-slot:iconLeft>
                        Projekt starten
                    </x-button>
                </x-framed-grid-card.body-col>

                <x-framed-grid-card.body-col variant="oneCol">
                    @if (! $ready)
                        {{-- ProjectsSkeleton.js --}}
                        <div class="flex flex-col lg:flex-row" data-test="skeleton_projects">
                            <div class="flex-1 lg:mr-2.5">
                                <div aria-label="loading" class="animate-pulse rounded-lg bg-lilac-200 h-36"></div>
                                <div aria-label="loading" class="animate-pulse rounded-lg mt-5 bg-lilac-200 h-44"></div>
                            </div>
                            <div class="flex-1 hidden ml-2.5 lg:block">
                                <div aria-label="loading" class="animate-pulse rounded-lg h-64 bg-lilac-200"></div>
                            </div>
                        </div>
                    @elseif ($projects->isEmpty())
                        {{-- WelcomeCard.js — literal CMS copy (projects.data.js `welcome`). --}}
                        <div
                            class="flex flex-col w-full items-center justify-end p-6 rounded-lg md:p-8 md:px-12 lg:px-16 md:flex-row-reverse bg-livid-300 text-livid-800"
                            data-test="welcome-card"
                        >
                            <div class="md:max-w-xl">
                                <x-text>Willkommen in Deinem Nusszopfbereich!</x-text>
                                <x-text variant="textSm" class="mt-3">Hier kannst Du Projekte mit Gesuchen erstellen und verwalten. Am besten legst Du gleich los und backst dir die Welt, wie sie dir gefällt!</x-text>
                                <x-text variant="textSm" class="mt-2">Viel Spaß im nussigsten Netzwerk aller Zeiten!</x-text>
                            </div>
                            <div class="flex items-center justify-center mt-10 mb-5 md:m-0 md:mr-12 lg:mr-16">
                                <x-icon name="nuss" :stroke-width="6" class="flex-shrink-0 w-auto h-18" />
                            </div>
                        </div>
                    @else
                        {{--
                            Masonry.js's own default `breakpointCols`
                            ({ default: 2, 1023: 1 }), since `pages/user/projects.js`
                            passes none of its own — 2 columns from `lg`, 1 below,
                            unlike Search's explicit 3/2/1 override. CSS columns
                            (`search.blade.php`'s own pattern: `gap-*` on the
                            container, `break-inside-avoid(-column) mb-*` per
                            card), not a literal translation of the historical
                            flexbox gap object (`wrap: '-ml-4', col: 'pl-4'`) —
                            that pairing doesn't apply to `columns-*` and was
                            previously copied in by mistake, overflowing the
                            left column 16px past the frame (verification pass,
                            2026-09-22).
                        --}}
                        <div class="columns-1 lg:columns-2 gap-4">
                            @foreach ($projects as $project)
                                @php
                                    $menuItems = [
                                        ['text' => 'Ansehen', 'click' => 'location.href = \''.route('projects.show', $project).'\''],
                                        ['text' => 'Bearbeiten', 'click' => 'location.href = \''.route('projects.edit', $project).'\''],
                                        [
                                            'text' => $project->visibility === 'public' ? 'Verbergen' : 'Veröffentlichen',
                                            'click' => 'nzToast(\'loading\', \'Änderungen speichern...\'); $wire.toggleVisibility(\''.$project->id.'\')',
                                        ],
                                        [
                                            'text' => 'Löschen',
                                            'click' => 'if (confirm(\'Möchtest Du das Projekt wirklich löschen?\')) { nzToast(\'loading\', \'Wird gelöscht...\'); $wire.deleteProject(\''.$project->id.'\'); }',
                                        ],
                                    ];
                                @endphp
                                <div
                                    wire:key="project-{{ $project->id }}"
                                    class="w-full mb-4 break-inside-avoid-column relative flex border border-lilac-300 text-lilac-800 rounded-lg cursor-pointer bg-lilac-200 ring-1 ring-transparent hover:ring-lilac-300"
                                    data-test="route_edit-project_projects-page"
                                >
                                    <a href="{{ route('projects.edit', $project) }}" class="flex-1 p-4 text-left md:p-5 focus:outline-none">
                                        <x-text class="mr-10">
                                            <x-icon :name="$project->visibility === 'public' ? 'eye' : 'eye-off'" :size="21" class="inline mr-1 -mt-1" />
                                            <span data-test="text_title_project-edit-card">{{ $project->title }}</span>
                                        </x-text>
                                        <x-text variant="textSm" class="mt-2">{{ \Illuminate\Support\Str::limit($project->goal, 90) }}</x-text>
                                        <div class="flex flex-col mt-4">
                                            @foreach ($project->requests as $request)
                                                <x-request-card
                                                    variant="preview"
                                                    wire:key="request-preview-{{ $request->id }}"
                                                    :title="$request->title"
                                                    :category="$request->category"
                                                    :created-at="$request->created_at->format('j.n.Y')"
                                                    :class="$loop->index > 0 ? 'mt-2' : ''"
                                                />
                                            @endforeach
                                        </div>
                                    </a>
                                    <div class="absolute top-0 right-0">
                                        <x-menu
                                            data-test="menu_edit-project-card"
                                            aria-label="Projekt Menü"
                                            label-class="mx-5 my-1"
                                            inner-class="py-2 mr-4"
                                            color="lilac"
                                            :items="$menuItems"
                                        ><x-icon name="more-horizontal" /></x-menu>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </x-framed-grid-card.body-col>
            </x-framed-grid-card.body>
        </x-frame>
    </x-framed-grid-card>
</div>
