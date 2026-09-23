{{-- `pages/newsletter/subscribe/[token].js`, `newsletterData.subscribeConfirm`. --}}
<x-layout title="Nusszopf" main-class="bg-white sm:bg-steel-100" footer-bg="bg-white sm:bg-steel-100" noindex>
    <x-framed-card class="bg-white text-steel-700" data-test="newsletter-subscribe-confirm">
        <x-newsletter-logo />
        <x-text as="h1" variant="textLgSemi" class="mt-10 mb-5 sm:mt-12">Juhuu! Nussige News!</x-text>
        <x-text variant="textSmMedium">
            <span class="italic font-semibold">{{ $email }}</span> wurde zum Newsletter angemeldet. Schön, dass Du mit dabei bist!
        </x-text>
        <x-button as="a" href="{{ route('home') }}" title="Zum Nusszopf" aria-label="Zum Nusszopf" class="mt-6 bg-steel-100">Zum Nusszopf</x-button>
    </x-framed-card>
</x-layout>
