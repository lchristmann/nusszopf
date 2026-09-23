@props(['goBackUri' => null])

{{--
    docs/design/navigation.md: a single top bar + hamburger-triggered dropdown
    at every breakpoint, no separate desktop nav-links row. The dropdown uses
    a native <details>/<summary> disclosure — no JavaScript needed for a
    plain show/hide menu (CLAUDE.md's "smallest client-side solution").

    data-test names reuse the historical NavHeader selectors
    (docs/design/navigation.md) where they existed for the same action.
--}}

<x-frame as="nav" class="bg-steel-400 text-steel-800 sticky top-0 left-0 right-0 z-20">
    <div class="flex items-center w-full h-10 lg:h-12 justify-between relative">
        <div class="flex items-center">
            <a href="{{ route('home') }}" data-test="btn_logo_nav-header" aria-label="Zum Nusszopf" title="Zum Nusszopf" class="focus:outline-none">
                <x-icon name="nusszopf-header-logo" :size="25" class="text-steel-100 lg:hidden" />
                <x-icon name="nusszopf-header-logo" :size="30" class="hidden text-steel-100 lg:block" />
            </a>
            {{-- `goBackUri === 'back'` is `router.back()` (Privacy with `?back`). --}}
            @if ($goBackUri === 'back')
                <a href="{{ route('home') }}" onclick="history.back(); return false;" data-test="btn_go-back_nav-header" aria-label="Zurück" title="Zurück" class="ml-6 focus:outline-none">
                    <x-icon name="chevron-left" :size="28" :stroke-width="2" />
                </a>
            @elseif ($goBackUri)
                <a href="{{ $goBackUri }}" data-test="btn_go-back_nav-header" aria-label="Zurück" title="Zurück" class="ml-6 focus:outline-none">
                    <x-icon name="chevron-left" :size="28" :stroke-width="2" />
                </a>
            @endif
        </div>

        <div class="flex items-center gap-6 sm:gap-8">
            <a href="{{ route('search') }}" data-test="btn_search_nav-header" aria-label="Suche" title="Suche" class="focus:outline-none">
                <x-icon name="search" />
            </a>

            @auth
                <a href="{{ route('projects.mine') }}" data-test="btn_user-projects_nav-header" aria-label="Meine Projekte" title="Meine Projekte" class="focus:outline-none">
                    <x-icon name="nuss" :size="21.5" :stroke-width="8.5" />
                </a>
            @endauth

            {{-- Decision A-7: Escape closes the menu; while it is open, focus stays in it and returns to the button. --}}
            <details
                aria-label="Menü"
                class="relative"
                x-data="{ open: false }"
                x-on:toggle="open = $el.open; if (open) nzFocusInto($refs.panel, 'a, button')"
                x-on:keydown.escape="if ($el.open) { $el.open = false; $refs.summary.focus(); }"
                x-on:click.outside="$el.open = false"
            >
                <summary x-ref="summary" data-test="btn_burger_nav-header" class="list-none cursor-pointer focus:outline-none" aria-label="Menü">
                    <x-icon name="menu" />
                </summary>

                <div x-ref="panel" x-trap="open" class="absolute right-0 z-20 py-4 mt-2 text-sm font-medium rounded-md shadow-md text-steel-800 bg-steel-400 w-56">
                    <a href="{{ route('search') }}" class="block px-4 py-2 hover:bg-steel-300">
                        <span class="flex items-center">
                            <span class="w-6 mr-1"><x-icon name="search" class="-ml-2" /></span>
                            <x-text as="span" variant="textSmMedium">Suche</x-text>
                        </span>
                    </a>
                    <a href="{{ route('projects.create') }}" data-test="btn_create-project_nav-header" class="block px-4 py-2 hover:bg-steel-300">
                        <span class="flex items-center">
                            <span class="w-6 mr-1"><x-icon name="plus-circle" :size="22" class="-ml-2" /></span>
                            <x-text as="span" variant="textSmMedium">Projekt erstellen</x-text>
                        </span>
                    </a>
                    @auth
                        <a href="{{ route('projects.mine') }}" class="block px-4 py-2 hover:bg-steel-300">
                            <span class="flex items-center">
                                <span class="w-6 mr-1"><x-icon name="nuss" :size="21" :stroke-width="8.5" class="-ml-2" /></span>
                                <x-text as="span" variant="textSmMedium">Meine Projekte</x-text>
                            </span>
                        </a>
                        <a href="{{ route('profile') }}" data-test="btn_settings_nav-header" class="block px-4 py-2 hover:bg-steel-300">
                            <span class="flex items-center">
                                <span class="w-6 mr-1"><x-icon name="user" :size="21" class="-ml-2" /></span>
                                <x-text as="span" variant="textSmMedium">{{ \Illuminate\Support\Str::limit(auth()->user()->name, 12, '...') }}</x-text>
                            </span>
                        </a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" data-test="btn_logout_nav-header" class="block w-full text-left px-4 py-2 hover:bg-steel-300">
                                <x-text as="span" variant="textSmMedium" class="text-warning-700">Ausloggen</x-text>
                            </button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" data-test="btn_login_nav-header" class="block px-4 py-2 hover:bg-steel-300">
                            <span class="flex items-center">
                            <span class="w-6 mr-1"><x-icon name="log-in" :size="23" class="-ml-2" /></span>
                            <x-text as="span" variant="textSmMedium">Einloggen / Registrieren</x-text>
                        </span>
                        </a>
                    @endauth
                </div>
            </details>
        </div>
    </div>
</x-frame>
