{{-- `pages/newsletter/unsubscribe/lead.js`, `newsletterData.unsubscribe`. --}}
<x-framed-card class="bg-white text-steel-700">
    <x-newsletter-logo />
    <x-text as="h1" variant="textLgSemi" class="mt-10 mb-5 sm:mt-12 sm:text-center">Newsletter&shy;abmeldung</x-text>
    <x-text variant="textSmMedium" class="mb-4">Bitte trage die E-Mail-Adresse ein, die Du abmelden möchtest:</x-text>
    <div class="w-full">
        <form wire:submit="unsubscribe" data-test="form_newsletter-unsubscribe">
            <x-input wire:model="email" name="email" type="email" autocomplete="off" data-test="input_newsletter-unsubscribe-email" aria-label="E-Mail-Adresse" placeholder="E-Mail-Adresse" />
            <x-input-error :message="$errors->first('email')" />
            <div class="mt-6 text-center">
                <x-button
                    type="submit"
                    class="bg-steel-100"
                    data-test="btn_newsletter-unsubscribe"
                    wire:loading.attr="disabled"
                    wire:target="unsubscribe"
                    x-on:click="nzToast('loading', 'Deine Abmeldung wird bearbeitet.')"
                >Abmelden</x-button>
            </div>
        </form>
    </div>
</x-framed-card>
