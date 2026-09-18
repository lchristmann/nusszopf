@props([
    'title' => 'Nusszopf',
    'hideNavHeader' => false,
    'goBackUri' => null,
])

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="robots" content="{{ app()->isProduction() ? 'index,follow' : 'noindex,nofollow' }}" />
    <title>{{ $title }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="antialiased bg-white text-steel-800 font-sans">
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

    <main>
        {{ $slot }}
    </main>

    <x-footer />

    <x-toast-container />

    @livewireScripts
</body>
</html>
