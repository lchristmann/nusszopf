@props([
    // `Page.js` SEO props (`seo.data.js` defaults); project detail passes
    // its title and goal.
    'title' => 'Nusszopf – Netzwerk für gemeinsame Ideen und Projekte',
    'description' => 'Setze mehr Ideen mit passenden Mitstreiter:innen, Ressourcen und Wissen um. Mach mit bei spannenden Projekten und werde Teil der Nusszopfgemeinschaft!',
    'hideNavHeader' => false,
    'goBackUri' => null,
    // Historical `Page` props: `className` on <main> and `footer.className`
    // (docs/design/screen-specs.md); the project screens pass
    // `bg-white text-lilac-800 lg:bg-steel-100` / `bg-steel-100`.
    'mainClass' => '',
    'footerBg' => 'bg-steel-200',
    // `footer.variant`: `classy` on Home only (docs/design/navigation.md).
    'footerVariant' => 'default',
    // Historical `Page`'s `noindex` prop; the newsletter pages set it.
    'noindex' => false,
])

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8" />
    {{-- `pages/_app.js`, verbatim (P-5, DEV-02). --}}
    <meta name="viewport" content="width=device-width,minimum-scale=1,initial-scale=1" />
    @php
        // `Page.js`/next-seo: lodash `truncate` (60 / 150, "..." included),
        // canonical = domain + path (BUG-036: `og:url` is the same absolute
        // URL, not the bare path).
        $truncate = fn (string $text, int $length) => mb_strlen($text) > $length ? mb_substr($text, 0, $length - 3).'...' : $text;
        $domain = rtrim((string) config('app.url'), '/');
        $path = request()->getRequestUri();
        $canonical = $path === '/' ? $domain : $domain.$path;
    @endphp
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="theme-color" content="#ffffff" />
    <meta name="msapplication-TileColor" content="#000000" />
    <meta name="msapplication-config" content="/favicons/browserconfig.xml" />
    <link href="/favicons/apple-touch-icon.png" rel="apple-touch-icon" sizes="180x180" />
    <link href="/favicons/favicon-32x32.png" rel="icon" sizes="32x32" type="image/png" />
    <link href="/favicons/favicon-16x16.png" rel="icon" sizes="16x16" type="image/png" />
    <link href="/favicons/site.webmanifest" rel="manifest" />
    <link color="#000000" href="/favicons/safari-pinned-tab.svg" rel="mask-icon" />
    <link href="/favicons/favicon.ico" rel="shortcut icon" />
    <title>{{ $truncate($title, 60) }}</title>
    <meta name="robots" content="{{ app()->isProduction() && ! $noindex ? 'index,follow' : 'noindex,nofollow' }}" />
    <meta name="googlebot" content="{{ app()->isProduction() && ! $noindex ? 'index,follow' : 'noindex,nofollow' }}" />
    <meta name="description" content="{{ $truncate($description, 150) }}" />
    <link rel="canonical" href="{{ $canonical }}" />
    <meta property="og:title" content="{{ $title }}" />
    <meta property="og:description" content="{{ $description }}" />
    <meta property="og:url" content="{{ $canonical }}" />
    <meta property="og:type" content="website" />
    <meta property="og:locale" content="de_DE" />
    <meta property="og:image" content="{{ $domain }}/images/og-image.png" />
    <meta property="og:image:alt" content="{{ $description }}" />
    <meta property="og:image:width" content="1648" />
    <meta property="og:image:height" content="863" />
    <meta name="twitter:card" content="summary_large_image" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
@php
    // The guided tour belongs to the shared demo account only (docs/deployment/demo.md).
    $tour = \App\Support\Demo::enabled() && \App\Support\Demo::isDemoUser(auth()->user());
    $tourProject = $tour ? \App\Support\Demo::featuredProject() : null;
@endphp
<body
    class="flex flex-col min-h-screen antialiased bg-white text-steel-800 font-sans"
    @if ($tourProject)
        data-tour-enabled
        data-tour-project="{{ route('projects.show', $tourProject, false) }}"
        data-tour-install="{{ rtrim((string) config('nusszopf.source_url'), '/') }}/blob/main/docs/deployment/README.md"
    @endif
>
    {{--
        Route-change loading bar (docs/design/visual-language.md,
        "Animation & motion") — a fixed 2px rainbow gradient bar, shown only
        while a Livewire action/navigation is in flight.
    --}}
    <div
        wire:loading.delay.longer
        wire:loading.class.remove="hidden"
        class="hidden fixed top-0 left-0 right-0 h-0.5 z-[100]"
        style="background: repeating-linear-gradient(90deg, #f4f651, #fa7061, #b1eed7, #ffccd5, #8ab2f1, #f4f651); background-size: 200% 100%; animation: nz-rainbow 2s linear infinite;"
    ></div>

    @unless ($hideNavHeader)
        <x-nav-header :go-back-uri="$goBackUri" />
    @endunless

    <main class="flex flex-col flex-1 {{ $mainClass }}">
        {{ $slot }}
    </main>

    <x-footer :bg="$footerBg" :variant="$footerVariant" />

    <x-toast-container />

    @if ($tourProject)
        <button
            type="button"
            data-tour-start
            data-test="btn_tour-start"
            class="fixed z-40 bottom-4 left-4 nz-btn-lilac bg-lilac-200 cursor-pointer py-1 px-3 font-medium text-lg outline-none focus:outline-none"
        >Geführte Tour</button>
    @endif

    @livewireScripts
</body>
</html>
