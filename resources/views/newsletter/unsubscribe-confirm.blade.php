{{-- `pages/newsletter/unsubscribe/[token].js`, `newsletterData.unsubscribeConfirm`. --}}
<x-layout title="Nusszopf" main-class="bg-white sm:bg-steel-100" footer-bg="bg-white sm:bg-steel-100" noindex>
    <x-framed-card class="bg-white text-steel-700" data-test="newsletter-unsubscribe-confirm">
        <x-newsletter-logo />
        <x-text as="h1" variant="textLgSemi" class="mt-10 mb-5 sm:mt-12">
            Schade Marmelade<span class="hidden sm:inline">...</span>
        </x-text>
        <x-text variant="textSmMedium">
            <span class="italic font-semibold">{{ $email }}</span> wurde vom Newsletter abgemeldet. Wir freuen uns über dein Feedback was wir am Newsletter verbessern können.
        </x-text>
        <x-button
            as="a"
            href="mailto:{{ \App\Models\Project::NUSSZOPF_CONTACT }}?subject={{ rawurlencode('Sponsorship | Partnerschaft | Feedback') }}"
            title="E-Mail an Nusszopf schreiben"
            aria-label="E-Mail an Nusszopf schreiben"
            class="inline-block mt-6 bg-steel-100"
        >Feedback senden</x-button>
    </x-framed-card>
</x-layout>
