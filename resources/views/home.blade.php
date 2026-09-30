{{--
    Home — `pages/index.js` and `containers/home/*` with the CMS copy in
    `assets/data/{header,home,contest,fellows,newsletter}.data.js`, structure
    and design reproduced (decision A-5; docs/rewrite/tenth-slice.md). No
    `NavHeader`, `classy` footer. `CarouselSection` is commented out
    historically and is not built; there is no "create project" CTA on Home.

    The copy is no longer verbatim: the 2021 "Wir sind am Kneten" rework
    announcement, the sponsor row and the Fördermitglied/Partner:in options
    described a state of the project that no longer exists, so they are gone,
    and the demo, an audience section and the newsletter wording are new
    (maintainer instruction 2026-09-29; docs/rewrite/intentional-changes.md,
    "Home describes the current Nusszopf").
--}}
@php
    $contact = \App\Support\Operator::contactEmail();
    $steps = [
        ['Idee!', 'Du hast eine Idee, die dich begeistert und willst diese gemeinsam mit tollen Menschen umsetzen.'],
        ['Projekt', 'Erstelle ein Projekt oder finde ähnliche bestehende Projekte und mache bei ihnen mit.'],
        ['Gesuche', 'Lege Gesuche an für alles, was benötigt wird zur Projektumsetzung und lass dir vom Netzwerk helfen.'],
        ['Umsetzung', 'Tu dich mit passenden Menschen aus dem Netzwerk zusammen, gemeinsam könnt ihr alles schaffen!'],
    ];
    $about = [
        ['Die Nussvision', 'Wir wollen, dass viel mehr tolle Ideen gemeinschaftlich verwirklicht werden können und die Welt durch jedes Projekt ein bisschen besser wird.'],
        ['Unsere Werte', 'Der Nusszopf ist offen für alle und soll von allen gemeinsam gestaltet werden. Wir stehen für einen respektvollen und inklusiven Umgang miteinander.'],
        ['Über uns', 'Gestartet wurde das Nusszopfprojekt im August 2019 von Meli und Micha, mit dem Wunsch, mehr von ihren eigenen Ideen umsetzen zu können.'],
    ];
    $demo = \App\Support\Demo::enabled();
    $source = rtrim((string) config('nusszopf.source_url'), '/');
    $docs = rtrim((string) config('nusszopf.docs_url'), '/');
    // Who the software is for, drawn only from what the application does (docs/domain, docs/deployment).
    $audiences = [
        ['Communities & Initiativen', 'Ein schwarzes Brett für Eure Mitglieder: Projekte vorstellen, benennen, was noch fehlt – Mitstreiter:innen, Räume, Materialien oder Finanzielles – und über die Suche gefunden werden.'],
        ['Bildungseinrichtungen', 'Schulen, Hochschulen und Bildungsträger machen Projektideen ihrer Lernenden sichtbar. Entwürfe bleiben privat, bis sie veröffentlicht werden.'],
        ['Unternehmen & Teams', 'Ideen und Bedarfe im Haus sammeln und zusammenbringen: Wer braucht Unterstützung, wer hat Zeit, Raum oder Material? Betrieben auf Eurer eigenen Infrastruktur.'],
        ['Vereine & Organisationen', 'Mitglieder bringen Projektideen ein und finden im eigenen Umfeld Mitstreiter:innen, Räume und Material.'],
    ];
    $options = [
        ['Betreibe Deinen eigenen Nusszopf!', 'Mit Docker Compose bringst Du den Nusszopf auf Deinen Server – für Deine Community, Schule, Firma oder Organi&shy;sation.', 'Installationsanleitung', $docs.'/installation.html', 'Zur Installationsanleitung', 'url'],
        ['Mach mit!', 'Der Nusszopf ist freie Software unter der GPL. Melde Fehler, schlage Verbesserungen vor oder entwickle mit.', 'Mitwirken', $source.'/blob/main/CONTRIBUTING.md', 'Zur Mitwirkungsanleitung auf GitHub', 'url'],
        ['Gib uns Feedback!', 'Teile deine Ideen und Wünsche mit uns, damit wir den Nusszopf weiter ver&shy;bessern und an deine Bedürfnisse anpassen können.', 'Feedback senden', 'mailto:'.$contact.'?subject=Nussiges Feedback', 'E-Mail an Nusszopf schreiben', 'mail'],
    ];
    // The three-column rows' per-index gutters (`pages/index.js`).
    $column = fn (int $index) => match ($index) {
        0 => 'lg:pr-6 xl:pr-10 mb-12 lg:mb-0',
        1 => 'lg:pl-3 lg:pr-3 xl:pr-5 xl:pl-5 mb-12 lg:mb-0',
        default => 'lg:pl-6 xl:pl-10',
    };
@endphp
<x-layout hide-nav-header main-class="text-steel-700" footer-bg="bg-steel-200" footer-variant="classy">
    {{-- Header --}}
    <x-frame as="header" class="bg-steel-50" data-test="home-header">
        <div class="flex flex-col pt-12 pb-12 sm:pt-20 sm:pb-20 lg:flex-row xl:pt-32 xl:pb-32">
            <div class="lg:w-1/2 lg:pr-8 lg:self-center">
                <x-logo name="nusszopf-logo-big" class="w-3/4 mx-auto lg:w-full xl:w-4/5" title="<3 Nusszopf" aria-label="Nusszopf" />
            </div>
            <div class="mt-8 sm:mt-16 lg:mt-0 lg:pl-8 lg:w-1/2 lg:self-center">
                <x-text as="h1" variant="titleLg" class="max-w-md">Netzwerk für gemeinsame Ideen und Projekte</x-text>
                <x-text as="h2" variant="textLg" class="mt-5">Hast Du auch ständig tolle Ideen, die Du verwirklichen möchtest? Hier findest Du die perfekten Zutaten für zopfige Ideenumsetzungen!</x-text>
            </div>
        </div>
        @if ($demo && (! auth()->check() || \App\Support\Demo::isDemoUser(auth()->user())))
            <div class="pb-16 sm:pb-18 xl:pb-20">
                <div class="max-w-3xl mx-auto break-normal">
                    <div class="px-6 py-8 rounded-lg sm:px-8 lg:p-12 bg-livid-300 text-livid-800" data-test="home-demo">
                        <x-text as="h2" variant="textMd" class="-mt-1.5">Probier den Nusszopf aus!</x-text>
                        <x-text variant="textSm" class="mt-3">Die Demo ist der echte Nusszopf mit erfundenen Beispielprojekten – ohne Anmeldung und ohne Passwort. Schau Dich frei um, lege Projekte und Gesuche an oder lass Dich in einer kurzen Tour durch die wichtigsten Stellen führen. Alle Änderungen werden stündlich zurückgesetzt.</x-text>
                        <div class="flex flex-wrap items-center gap-4 mt-6">
                            <form method="POST" action="{{ route('demo.login') }}">
                                @csrf
                                <x-button type="submit" size="large" class="nz-btn-rainbow" data-test="btn_demo-login_home">Demo ausprobieren</x-button>
                            </form>
                            <form method="POST" action="{{ route('demo.login', ['tour' => 1]) }}">
                                @csrf
                                <x-button type="submit" size="large" class="bg-white" data-test="btn_demo-tour_home">Geführte Tour starten</x-button>
                            </form>
                        </div>
                        <x-text variant="textSm" class="pt-5 mt-8 border-t border-livid-500" data-test="home-demo-handbook">Du willst den Nusszopf selbst betreiben oder mitentwickeln? <a href="{{ $docs }}/index.html" target="_blank" rel="noopener noreferrer" class="border-b-2 cursor-pointer nz-link-current" data-test="link_handbook_home">Zum Handbuch</a></x-text>
                    </div>
                </div>
            </div>
        @else
            <div class="pb-12 sm:pb-14 xl:pb-16"></div>
        @endif
    </x-frame>

    {{-- HowToSection --}}
    <x-frame class="pt-12 pb-16 bg-yellow-250 sm:pt-16 sm:pb-18" data-test="home-how-to">
        <x-text as="h3" variant="titleMd" class="mb-8 sm:max-w-sm xl:max-w-full xl:mb-10">How To Nusszopf</x-text>
        <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
            @foreach ($steps as $index => [$title, $description])
                <div class="flex items-center p-5 border-2 rounded-lg border-steel-700">
                    <div class="mr-5">
                        <div class="flex items-center justify-center w-12 h-12 border-2 rounded-full border-steel-700">
                            <x-text variant="textLgThin">{{ $index + 1 }}</x-text>
                        </div>
                    </div>
                    <div>
                        <x-text variant="titleSm" class="mb-2 uppercase">{{ $title }}</x-text>
                        <x-text variant="textSm">{{ $description }}</x-text>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="flex justify-center mt-8">
            <x-button as="a" href="{{ route('search') }}" size="large" class="inline-block bg-yellow-400" data-test="route_search-page" aria-label="Projekte entdecken">
                <x-slot:iconLeft><x-icon name="search" class="mr-2 -ml-1" /></x-slot:iconLeft>
                Projekte entdecken
            </x-button>
        </div>
    </x-frame>

    {{-- Audiences: what the software is useful for --}}
    <x-frame class="pt-12 pb-16 bg-white sm:pt-16 sm:pb-18" data-test="home-audiences">
        <x-text as="h3" variant="titleMd" class="mb-4 sm:max-w-sm xl:max-w-full">Wofür ist der Nusszopf gut?</x-text>
        <x-text variant="textMd" class="mb-8 xl:mb-10">Der Nusszopf ist eine Plattform, auf der Menschen Projekte mit Gesuchen einstellen und andere finden, die mitmachen. Weil er freie Software ist, kann ihn jede Gruppe für sich selbst betreiben:</x-text>
        <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
            @foreach ($audiences as [$title, $description])
                <div class="p-5 border-2 rounded-lg border-steel-700">
                    <x-text as="h4" variant="titleSm" class="mb-2 uppercase">{{ $title }}</x-text>
                    <x-text variant="textSm">{{ $description }}</x-text>
                </div>
            @endforeach
        </div>
        <x-text variant="textSm" class="mt-6">
            Alles läuft auf Deinem eigenen Server.
            <a href="{{ $docs }}/installation.html" target="_blank" rel="noopener noreferrer" class="border-b-2 cursor-pointer nz-text-sm nz-link-current">So geht die Installation</a>
        </x-text>
    </x-frame>

    {{-- About --}}
    <x-frame class="pt-12 pb-16 bg-turquoise-300 sm:pt-16 sm:pb-18 xl:pt-18 xl:pb-20" data-test="home-about">
        <x-text as="h3" variant="titleMd" class="mb-8 sm:max-w-sm xl:max-w-full xl:mb-10">Über den Nusszopf</x-text>
        <div class="flex flex-wrap">
            @foreach ($about as $index => [$title, $description])
                <div class="flex flex-col justify-between lg:w-1/3 {{ $column($index) }}">
                    <div>
                        <x-text as="h4" variant="titleSm" class="mb-2">{{ $title }}</x-text>
                        <x-text variant="textMd">{{ $description }}</x-text>
                    </div>
                </div>
            @endforeach
        </div>
    </x-frame>

    {{-- Contest --}}
    <x-frame class="pt-12 pb-16 bg-red-300 sm:pt-16 sm:pb-18 xl:pt-18 xl:pb-20" data-test="home-contest">
        <div class="lg:flex">
            <div class="lg:w-2/3 xl:w-7/12">
                <x-text as="h3" variant="titleMd" class="mb-8 xl:mb-10">Augsburger Zukunftspreis 2021</x-text>
                <x-text variant="textMd" class="mb-4 sm:mb-5">Ausgezeichnet: Die SchülerInnenjury hat den Nusszopf – das „Netzwerk für gemeinsame Ideen und Projekte“ – mit einem Zukunftspreis der Stadt Augsburg geehrt. Die Preisverleihung fand am Montag, den 16. Mai 2022, statt.</x-text>
                <x-text variant="textMd">
                    Mehr Informationen:
                    <a href="https://www.augsburg.de/aktuelles-aus-der-stadt/detail/augsburger-zukunftspreise-2021-verliehen" target="_blank" rel="noopener noreferrer" title="Pressemitteilung der Stadt Augsburg zur Preisverleihung" class="border-b-2 cursor-pointer nz-text-md nz-link-red">Mitteilung der Stadt Augsburg</a>
                    ·
                    <a href="https://www.hallo-augsburg.de/zukunftspreis-klimacamp-augsburg-stadt-augsburg-zeichnet-klimacamp-mit-dem-zukunftspreis-aus_DhJ" target="_blank" rel="noopener noreferrer" title="Bericht über die Preisverleihung" class="border-b-2 cursor-pointer nz-text-md nz-link-red">Bericht</a>
                    ·
                    <a href="https://www.nachhaltigkeit.augsburg.de/zukunftspreis" target="_blank" rel="noopener noreferrer" title="Augsburger Zukunftspreis 2021" class="border-b-2 cursor-pointer nz-text-md nz-link-red">augsburg.de/zukunftspreis</a>
                </x-text>
            </div>
            <div class="mt-12 sm:mt-16 lg:ml-4 xl:ml-0 lg:mt-4 lg:self-center lg:w-1/3 xl:w-5/12">
                <a href="https://www.nachhaltigkeit.augsburg.de/zukunftspreis" target="_blank" rel="noopener noreferrer" title="Zum Augsburger Zukunftspreis" aria-label="Zum Augsburger Zukunftspreis" class="block w-48 mx-auto cursor-pointer sm:w-56 lg:mr-0 xl:mr-auto xl:w-64">
                    <x-logo name="az-logo" class="fill-current" />
                </a>
            </div>
        </div>
    </x-frame>

    {{-- Fellows --}}
    <x-frame class="pt-12 pb-16 bg-pink-200 sm:pt-16 sm:pb-18 xl:pt-18 xl:pb-20" data-test="home-fellows">
        <x-text as="h3" variant="titleMd" class="mb-6">Zopfstarke Mitstreiter:innen</x-text>
        <x-text variant="textMd" class="mb-7">Der Nusszopf lebt vom Mitmachen – so kannst Du dabei sein:</x-text>
        <div class="flex flex-wrap">
            @foreach ($options as $index => [$title, $description, $action, $href, $meta, $type])
                <div class="flex flex-col justify-between lg:w-1/3 {{ $column($index) }}">
                    <div>
                        <x-text as="h4" variant="titleSm" class="mb-2">{{ $title }}</x-text>
                        <x-text variant="textMd">{!! $description !!}</x-text>
                    </div>
                    <div class="w-full mt-8 text-center lg:text-left">
                        <x-button
                            as="a"
                            :href="$href"
                            :target="$type === 'url' ? '_blank' : null"
                            :rel="$type === 'url' ? 'noopener noreferrer' : null"
                            class="inline-block bg-pink-300"
                            :title="$meta"
                        >{{ $action }}</x-button>
                    </div>
                </div>
            @endforeach
        </div>
    </x-frame>

    {{--
        NewsletterSection. The newsletter is a feature of the software that each installation's operator runs for their
        own community — the Nusszopf project itself sends none. On the public demo the sign-up is replaced by that
        explanation, so the demo never collects real addresses.
    --}}
    <x-frame id="newsletter" class="pt-12 pb-16 bg-blue-300 sm:pt-16 sm:pb-18 xl:pt-18 xl:pb-20" data-test="home-newsletter">
        <x-text as="h3" variant="titleMd" class="mb-8 xl:mb-10">Newsletter für Deine Community</x-text>
        @if ($demo)
            <div class="max-w-3xl" data-test="home-newsletter-demo">
                <x-text variant="textLg">Der Nusszopf bringt eine Newsletter-Funktion mit: Wer eine Installation betreibt, kann Besucher:innen einladen, sich per E-Mail anzumelden (mit Bestätigungs-Mail), und die bestätigten Abonnent:innen exportieren.</x-text>
                <x-text variant="textSm" class="mt-4">Das Nusszopf-Projekt selbst betreibt keinen Newsletter und plant auch keinen – deshalb ist die Anmeldung in dieser Demo abgeschaltet. Auf Deiner eigenen Installation ist sie Deine.</x-text>
            </div>
        @else
            <div class="h-full lg:flex">
                <div class="mb-10 lg:w-1/2 lg:mb-0">
                    <x-text variant="textLg">Bleib auf dem Laufenden über Neuigkeiten und inspirierende Projekte aus dieser Community – bequem per E-Mail.</x-text>
                    <x-text variant="textSm" class="mt-4">
                        Füge den Absender zu deinen Kontakten hinzu, damit die E-Mails dich sicher erreichen:
                        <a href="{{ route('contact.vcard') }}" title="Nusszopf als Kontakt speichern" aria-label="Nusszopf als Kontakt speichern" class="border-b-2 cursor-pointer nz-text-sm nz-link-current">Kontakt speichern</a>
                    </x-text>
                </div>
                <div class="lg:mt-0 lg:w-1/2 lg:ml-16">
                    <livewire:newsletter.subscribe-form />
                </div>
            </div>
        @endif
    </x-frame>
</x-layout>
