{{--
    Home — `pages/index.js`, `containers/home/*` and the CMS copy in
    `assets/data/{header,home,contest,fellows,newsletter}.data.js`, reproduced
    verbatim (decision A-5; docs/rewrite/tenth-slice.md). No `NavHeader`,
    `classy` footer. `CarouselSection` is commented out historically and is
    not built; there is no "create project" CTA on Home.
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
    $fellows = [
        ['https://vercel.com?utm_source=nusszopf&utm_campaign=oss', 'Zu Vercel', 'vercel-logo'],
        ['https://auth0.com/', 'Zu Auth0', 'auth0-logo'],
        ['https://www.sanity.io/', 'Zu Sanity', 'sanity-logo'],
        ['https://locationiq.com/', 'Zu LocationIQ', 'locationiq-logo'],
    ];
    // `&shy;` soft hyphens are part of the historical copy.
    $options = [
        ['Werde Fördermitglied!', 'Der Nusszopf ist ein Non-Profit- Herzens&shy;projekt. Unterstütze ihn auf unserer Förderungs&shy;webseite, damit er dich unterstützen kann.', 'Mehr erfahren', 'https://steadyhq.com/de/nusszopf', 'Zur Förderungswebseite', 'url'],
        ['Werde Partner:in!', 'Zusammen mit passenden Vereinen, Unternehmen und anderen Organi&shy;sationen wollen wir ein Partner:innen&shy;netzwerk aufbauen.', 'Partner:in werden', 'mailto:'.$contact.'?subject=Nussige Partnerschaft', 'E-Mail an Nusszopf schreiben', 'mail'],
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
        <div class="pb-16 sm:pb-18 xl:pb-20">
            <div class="max-w-3xl mx-auto break-normal">
                <div class="px-6 py-8 rounded-lg sm:px-8 lg:p-12 bg-livid-300 text-livid-800">
                    <x-text variant="textMd" class="-mt-1.5">Wir sind am Kneten: Der Nusszopf wird grundlegend überarbeitet!</x-text>
                    <x-text variant="textSm" class="mt-3">Hier findet ihr aktuell den veralteten ersten Prototyp des Netzwerks. Wie bei jedem guten Hefeteig üblich, haben wir den Nusszopf ein wenig ruhen lassen und sind jetzt mit einem Testrezept für eine neue Nusszopfversion zurück – "Nusszopf für Communities":</x-text>
                    <div class="flex lg:justify-center">
                        <ol class="pl-3 list-decimal my-3 lg:w-4/5">
                            @foreach ([
                                'Den aktuellen Nusszopf sorgfältig mit Open Source Prinzipien zu einem offenen, mitgestaltbaren und dezentralen Konzept vermengen.',
                                'Das Konzept mit einer bereits bestehenden Community testen und nach deren Bedarfen verfeinern.',
                                'Testzopf backen. Genau analysieren und gegebenenfalls das Rezept anpassen.',
                                'Nach erfolgreicher Verköstigung: Rezept veröffentlichen und alle können den Nusszopf nach eigenem Geschmack und Bedarf nachbacken!',
                            ] as $item)
                                <li class="mt-3"><x-text as="span" variant="textSm">{{ $item }}</x-text></li>
                            @endforeach
                        </ol>
                    </div>
                    <x-text variant="textSm">Wie das alles funktionieren kann? Unsere Vision ist, ein öffentlich zugängliches und benutzbares Softwarepaket vom Nusszopf zu schnüren. Communities können so ein eigenes schwarzes Brett à la Nusszopf auf ihren Webseiten veröffentlichen und dessen Funktionen ganz für sich anpassen. So pflegt das Nusszopf Netzwerk das Softwarepaket und entwickelt es bedürfnisorientiert weiter.</x-text>
                </div>
            </div>
        </div>
    </x-frame>

    {{-- HowToSection --}}
    <x-frame class="pt-12 pb-16 bg-yellow-250 sm:pt-16 sm:pb-18" data-test="home-how-to">
        <x-text as="h3" variant="titleMd" class="mb-8 sm:max-w-sm xl:max-w-full xl:mb-10">How To Nusszopf (Alte Version)</x-text>
        <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
            @foreach ($steps as $index => [$title, $description])
                <div class="flex items-center p-5 border-2 rounded-lg border-steel-700">
                    <div class="mr-5">
                        @if ($index === 2)
                            <x-icon name="request" :size="48" />
                        @else
                            <div class="flex items-center justify-center w-12 h-12 border-2 rounded-full border-steel-700">
                                <x-text variant="textLgThin">{{ $index + 1 }}</x-text>
                            </div>
                        @endif
                    </div>
                    <div>
                        <x-text variant="titleSm" class="mb-2 uppercase">{{ $title }}</x-text>
                        <x-text variant="textSm">{{ $description }}</x-text>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="flex justify-center mt-8">
            <x-button as="a" href="{{ route('search') }}" size="large" class="inline-block bg-yellow-400" data-test="route_search-page" aria-label="Alte Version entdecken">
                <x-slot:iconLeft><x-icon name="search" class="mr-2 -ml-1" /></x-slot:iconLeft>
                Alte Version entdecken
            </x-button>
        </div>
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
                <x-text variant="textMd" class="mb-4 sm:mb-5">Auch ein Nusszopf muss seine Brötchen verdienen: Wir haben das Projekt bei dem Augsburger Zukunftspreis 2021 eingereicht. Die Preisverleihung findet statt am Montag, den 16.05.22, wir sind fest am Daumen drücken!</x-text>
                <x-text variant="textMd">
                    Mehr Informationen:
                    <a href="https://www.nachhaltigkeit.augsburg.de/zukunftspreis" target="_blank" rel="noopener noreferrer" title="Augsburger Zukunftspreis 2021" aria-label="Augsburger Zukunftspreis 2021" class="border-b-2 cursor-pointer nz-text-md nz-link-red">augsburg.de/zukunftspreis</a>
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
        <div class="flex flex-col mb-7 lg:items-center lg:flex-row">
            <x-text variant="textMd" class="lg:mr-8">Wir werden unterstützt von:</x-text>
            <div class="flex flex-wrap items-center -ml-4">
                @foreach ($fellows as $index => [$href, $meta, $logo])
                    <a href="{{ $href }}" target="_blank" rel="noopener noreferrer" title="{{ $meta }}" aria-label="{{ $meta }}" class="cursor-pointer">
                        <x-logo :name="$logo" @class(['p-4 fill-current', 'w-32' => $index !== 3, 'w-36' => $index === 3]) />
                    </a>
                @endforeach
            </div>
        </div>
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
                            :aria-label="$meta"
                        >{{ $action }}</x-button>
                    </div>
                </div>
            @endforeach
        </div>
    </x-frame>

    {{-- NewsletterSection --}}
    <x-frame id="newsletter" class="pt-12 pb-16 bg-blue-300 sm:pt-16 sm:pb-18 xl:pt-18 xl:pb-20" data-test="home-newsletter">
        <x-text as="h3" variant="titleMd" class="mb-8 xl:mb-10">Nussiger Newsletter</x-text>
        <div class="h-full lg:flex">
            <div class="mb-10 lg:w-1/2 lg:mb-0">
                <x-text variant="textLg">Wir versorgen euch mit backfrischen Nusszopf&shy;neuigkeiten, inspirierenden Projekten und allem, was uns sonst noch so einfällt.</x-text>
                <x-text variant="textSm" class="mt-4">
                    Füge den Nusszopf zu deinen Kontakten hinzu, damit unsere E-Mails dich sicher erreichen:
                    <a href="{{ route('contact.vcard') }}" title="Nusszopf als Kontakt speichern" aria-label="Nusszopf als Kontakt speichern" class="border-b-2 cursor-pointer nz-text-sm nz-link-current">Kontakt speichern</a>
                </x-text>
            </div>
            <div class="lg:mt-0 lg:w-1/2 lg:ml-16">
                <livewire:newsletter.subscribe-form />
            </div>
        </div>
    </x-frame>
</x-layout>
