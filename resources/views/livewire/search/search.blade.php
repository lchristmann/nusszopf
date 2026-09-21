<div wire:init="load">
    {{--
        pages/search.js: <Frame size="large" className="py-6 md:pt-12 md:pb-10 bg-moss-300
        text-moss-800"> holding the title and SearchInput.
    --}}
    <x-frame size="large" class="py-6 md:pt-12 md:pb-10 bg-moss-300 text-moss-800">
        <div class="max-w-3xl mx-auto">
            <x-text as="h1" variant="titleMd" class="mb-6">Ideen und Projekte aus dem Nusswerk</x-text>

            {{--
                SearchInput.js + FilterPopover.js. A query runs on an explicit submit (Enter, or the
                search icon), never while typing; the popover's checkboxes are picked first and
                applied by that same submit — until then the icon shows "refresh".
            --}}
            <form
                x-data="{
                    pending: @js($filter),
                    open: false,
                    get dirty() { return [...this.pending].sort().join() !== [...$wire.filter].sort().join(); },
                }"
                x-on:submit.prevent="$wire.search(pending)"
            >
                <div class="relative rounded-lg text-moss-800">
                    <x-input
                        x-ref="input"
                        wire:model="query"
                        type="search"
                        name="query"
                        data-test="input_search-input"
                        maxlength="{{ \App\Livewire\Search\Search::MAX_QUERY_LENGTH }}"
                        aria-label="Suchen & Finden"
                        placeholder="Suchen & Finden"
                        color="moss"
                        size="large"
                        :displayRing="false"
                        class="pr-32 [&::-webkit-search-cancel-button]:hidden"
                    />
                    <div class="absolute top-0 right-0 flex items-center h-full px-3">
                        <button
                            type="button"
                            aria-label="Suchfeld leeren"
                            x-show="$wire.query.length > 0"
                            x-cloak
                            x-on:click.prevent="$wire.query = ''; $refs.input.focus()"
                            class="p-1 mr-3 transition duration-100 ease-out rounded-full outline-none cursor-pointer hover:bg-moss-450"
                        ><x-icon name="x" /></button>
                        <button
                            type="submit"
                            data-test="btn_search_search-input"
                            aria-label="Suchen"
                            class="flex items-center justify-center w-16 h-16 -mr-3 border-t-2 border-b-2 border-r-2 outline-none cursor-pointer rounded-r-md border-moss-800 bg-moss-450"
                        >
                            <span wire:loading.remove wire:target="search">
                                <span x-show="! dirty"><x-icon name="search" :size="27" /></span>
                                <span x-show="dirty" x-cloak data-test="icon_refresh_search-input"><x-icon name="refresh-cw" :stroke-width="2.2" /></span>
                            </span>
                            <span wire:loading.flex wire:target="search" class="hidden" data-test="icon_loading_search-input"><x-icon name="loader" class="animate-spin" :stroke-width="2.2" /></span>
                        </button>
                    </div>
                </div>

                <div class="relative float-right mt-3 text-moss-800" x-on:keydown.escape="open = false" x-on:click.outside="open = false">
                    <button
                        type="button"
                        data-test="btn_disclosure_filter-popover"
                        aria-haspopup="dialog"
                        x-bind:aria-expanded="open"
                        x-on:click="open = ! open"
                        class="flex font-semibold focus:outline-none"
                    >
                        <div class="flex px-4 py-1 rounded-full bg-moss-450">
                            <x-text as="span" variant="textXs">Gesuche filtern</x-text>
                            <x-icon name="chevron-down" :size="20" :stroke-width="2.5" class="mt-1 ml-1 -mr-1" />
                        </div>
                    </button>
                    <div
                        x-show="open"
                        x-cloak
                        x-transition:enter="transition ease-out duration-100"
                        x-transition:enter-start="opacity-0 scale-[0.85]"
                        x-transition:enter-end="opacity-100 scale-100"
                        role="dialog"
                        aria-label="Gesuche filtern"
                        class="absolute right-0 z-10 mt-2 origin-top-right focus:outline-none"
                    >
                        <div class="px-4 py-2 text-sm font-medium border-2 rounded-md shadow-md w-52 bg-moss-300 border-moss-800">
                            @foreach (\App\Services\Search\ProjectSearch::CATEGORIES as $category)
                                <div class="my-2">
                                    <x-checkbox
                                        name="{{ $category }}"
                                        value="{{ $category }}"
                                        x-model="pending"
                                        data-test="checkbox_{{ $category }}_filter-popover"
                                        aria-label="{{ \App\Models\ProjectRequest::CATEGORY_LABELS[$category] ?? 'Keine Gesuche' }}"
                                    >{{ \App\Models\ProjectRequest::CATEGORY_LABELS[$category] ?? 'Keine Gesuche' }}</x-checkbox>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="clear-both"></div>
            </form>
        </div>
    </x-frame>

    {{-- pages/search.js — <Frame className="flex-1 h-full my-8 break-all" size="large">. --}}
    <x-frame size="large" class="flex-1 h-full my-8 break-all">
        @if (! $ready)
            {{-- SkeletonHits.js: three columns, the 2nd from sm and the 3rd from lg up. --}}
            <div class="flex flex-col sm:flex-row" data-test="skeleton_hits">
                <div class="flex-1 sm:mr-2.5">
                    <div aria-label="loading" class="animate-pulse box-content w-full rounded-lg bg-lilac-200 h-36"></div>
                    <div aria-label="loading" class="animate-pulse box-content w-full rounded-lg mt-5 bg-lilac-200 h-44"></div>
                    <div aria-label="loading" class="animate-pulse box-content w-full rounded-lg h-64 mt-5 bg-lilac-200"></div>
                </div>
                <div class="flex-1 hidden mx-2.5 sm:block">
                    <div aria-label="loading" class="animate-pulse box-content w-full rounded-lg h-64 bg-lilac-200"></div>
                    <div aria-label="loading" class="animate-pulse box-content w-full rounded-lg mt-5 bg-lilac-200 h-36"></div>
                    <div aria-label="loading" class="animate-pulse box-content w-full rounded-lg mt-5 bg-lilac-200 h-44"></div>
                </div>
                <div class="flex-1 hidden ml-2.5 lg:block">
                    <div aria-label="loading" class="animate-pulse box-content w-full rounded-lg bg-lilac-200 h-44"></div>
                    <div aria-label="loading" class="animate-pulse box-content w-full rounded-lg h-64 mt-5 bg-lilac-200"></div>
                    <div aria-label="loading" class="animate-pulse box-content w-full rounded-lg mt-5 bg-lilac-200 h-36"></div>
                </div>
            </div>
        @elseif (count($this->results->hits) > 0)
            {{-- Masonry: 3 columns, 2 below 1024px, 1 below 640px (`breakpointCols`), gap 5. --}}
            <div class="columns-1 sm:columns-2 lg:columns-3 gap-5" data-test="search-results">
                @foreach ($this->results->hits as $hit)
                    {{-- HitCard.js --}}
                    <a
                        wire:key="hit-{{ $hit->project->id }}"
                        href="{{ route('projects.show', $hit->project) }}"
                        data-test="route_hitcard"
                        class="block break-inside-avoid mb-5"
                    >
                        <div class="border p-4 md:p-5 border-lilac-300 text-lilac-800 transition-shadow duration-150 ease-in-out rounded-lg bg-lilac-200 ring-1 ring-transparent hover:ring-lilac-300 cursor-pointer">
                            <div class="flex justify-between mb-2">
                                <div>
                                    <x-text variant="textSm" data-test="route_title_hitcard" class="mb-1.5 font-semibold leading-6">{!! $hit->titleHtml !!}</x-text>
                                    <x-text variant="textXs">{!! $hit->goalHtml !!}</x-text>
                                </div>
                                <div><x-icon name="chevron-right" :size="28" class="-mr-2" /></div>
                            </div>
                            <x-text variant="textXs">{!! $hit->infoHtml !!}</x-text>
                            @foreach ($hit->requests as $request)
                                <x-request-card variant="hit" class="mt-3" :category="$request->category" :titleHtml="$request->titleHtml" :descriptionHtml="$request->descriptionHtml" />
                            @endforeach
                        </div>
                    </a>
                @endforeach
            </div>
        @else
            {{-- NoHitsSection.js --}}
            <div class="max-w-3xl mx-auto break-normal mt-4" data-test="no-hits">
                <div class="px-6 py-8 rounded-lg sm:px-8 lg:p-12 bg-livid-300 text-livid-800">
                    <x-text class="-mt-1.5">Verzopft, wir konnten leider nichts zu deiner Suche finden!</x-text>
                    <x-text variant="textSm" class="mt-3">Versuch es noch einmal mit anderen oder weniger Begriffen oder erstelle dein Traumprojekt in ein paar Schritten einfach selbst.</x-text>
                    <div class="mt-6 text-center lg:mt-8">
                        <x-button as="a" href="{{ route('projects.create') }}" aria-label="Projekt starten" size="large" color="lilac" class="bg-lilac-200">
                            <x-slot:iconLeft><x-icon name="plus-circle" class="hidden mr-2 -ml-1 sm:inline-block" /></x-slot:iconLeft>
                            Projekt starten
                        </x-button>
                    </div>
                </div>
            </div>
        @endif
    </x-frame>

    @if ($ready && $this->results->hasMore)
        <x-frame class="my-10 text-center">
            <x-button type="button" color="moss" class="bg-moss-300" wire:click="loadMore" data-test="btn_load-more">
                <x-slot:iconLeft>
                    <span wire:loading.remove wire:target="loadMore"><x-icon name="arrow-down-circle" class="mr-1.5" /></span>
                    <span wire:loading wire:target="loadMore"><x-icon name="loader" :size="22" class="mr-2 animate-spin" /></span>
                </x-slot:iconLeft>
                Mehr laden
            </x-button>
        </x-frame>
    @endif

    <x-button
        type="button"
        size="circle"
        aria-label="Nach oben scrollen"
        data-test="btn_scroll-top"
        class="fixed bottom-0 right-0 m-6 shadow-lg-dark bg-steel-300"
        x-data
        x-on:click="window.scrollTo({ top: 0, behavior: 'smooth' })"
    ><x-icon name="chevron-up" /></x-button>
</div>
