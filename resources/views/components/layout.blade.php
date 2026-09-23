@props([
    'title' => 'Nusszopf',
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
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="robots" content="{{ app()->isProduction() && ! $noindex ? 'index,follow' : 'noindex,nofollow' }}" />
    <title>{{ $title }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="flex flex-col min-h-screen antialiased bg-white text-steel-800 font-sans">
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

    @livewireScripts
</body>
</html>
