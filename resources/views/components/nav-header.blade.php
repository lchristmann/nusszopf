@props(['goBackUri' => null])

{{--
    docs/design/navigation.md: a single top bar + hamburger-triggered dropdown
    at every breakpoint, no separate desktop nav-links row. The dropdown uses
    a native <details>/<summary> disclosure — no JavaScript needed for a
    plain show/hide menu (CLAUDE.md's "smallest client-side solution").
--}}

<x-frame as="nav" class="bg-steel-400 text-steel-800 sticky top-0 left-0 right-0 z-20">
    <div class="flex items-center w-full h-10 lg:h-12 justify-between relative">
        <div class="flex items-center">
            <a href="{{ route('home') }}" aria-label="Zum Nusszopf" class="focus:outline-none font-bold text-lg">
                Nusszopf
            </a>
            @if ($goBackUri)
                <a href="{{ $goBackUri }}" aria-label="Zurück" class="ml-6 focus:outline-none">&larr;</a>
            @endif
        </div>

        <div class="flex items-center gap-6 sm:gap-8">
            <a href="{{ route('search') }}" aria-label="Suche" class="focus:outline-none">
                <x-text as="span" variant="textSmMedium">Suche</x-text>
            </a>

            @auth
                <a href="{{ route('projects.mine') }}" aria-label="Meine Projekte" class="focus:outline-none">
                    <x-text as="span" variant="textSmMedium">Meine Projekte</x-text>
                </a>
            @endauth

            <details class="relative">
                <summary class="list-none cursor-pointer focus:outline-none" aria-label="Menü">
                    <span aria-hidden="true">&#9776;</span>
                </summary>

                <div class="absolute right-0 z-20 py-4 mt-2 text-sm font-medium rounded-md shadow-md text-steel-800 bg-steel-400 w-56">
                    <a href="{{ route('search') }}" class="block px-4 py-2 hover:bg-steel-300">
                        <x-text as="span" variant="textSmMedium">Suche</x-text>
                    </a>
                    <a href="{{ route('projects.create') }}" class="block px-4 py-2 hover:bg-steel-300">
                        <x-text as="span" variant="textSmMedium">Projekt erstellen</x-text>
                    </a>
                    @auth
                        <a href="{{ route('projects.mine') }}" class="block px-4 py-2 hover:bg-steel-300">
                            <x-text as="span" variant="textSmMedium">Meine Projekte</x-text>
                        </a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="block w-full text-left px-4 py-2 hover:bg-steel-300">
                                <x-text as="span" variant="textSmMedium" class="text-warning-700">Ausloggen</x-text>
                            </button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="block px-4 py-2 hover:bg-steel-300">
                            <x-text as="span" variant="textSmMedium">Einloggen / Registrieren</x-text>
                        </a>
                    @endauth
                </div>
            </details>
        </div>
    </div>
</x-frame>
