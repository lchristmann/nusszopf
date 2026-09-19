{{--
    Historical footer variants (docs/design/navigation.md) carry Vercel/Auth0
    sponsor badges — non-product hosting-sponsorship branding the doc itself
    flags as not necessarily needing reproduction. Legal-page links
    (legalNotice/legalPolicy/privacy) are out of scope for this slice
    (docs/rewrite/first-slice.md) — not linked here yet rather than linking
    to routes that don't exist. This is deliberately minimal scaffolding.
--}}

@props(['bg' => 'bg-steel-200'])

<x-frame as="footer" class="{{ $bg }} py-8">
    <x-text as="p" variant="textXs" class="text-center text-steel-600">
        &copy; {{ now()->year }} Nusszopf
    </x-text>
</x-frame>
