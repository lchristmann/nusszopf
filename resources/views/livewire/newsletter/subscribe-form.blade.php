{{--
    `NewsletterForm.js` (Home `NewsletterSection`, placed there in slice 10):
    name and e-mail side by side from `lg`, the privacy checkbox, and the
    large "Anmelden" button with the ad hoc `bg-blue-400` fill (BUG-014).
--}}
<form wire:submit="subscribe" data-test="form_newsletter-subscribe">
    <div class="w-full lg:flex">
        <div class="lg:w-1/2 lg:mr-2">
            <x-input wire:model="name" name="name" type="text" autocomplete="off" data-test="input_newsletter-name" aria-label="Name" placeholder="Name" />
            <x-input-error for="name" :message="$errors->first('name')" />
        </div>
        <div class="lg:w-1/2 lg:ml-2">
            <x-input wire:model="email" name="email" type="email" autocomplete="off" class="mt-4 lg:mt-0" data-test="input_newsletter-email" aria-label="E-Mail-Adresse" placeholder="E-Mail-Adresse" />
            <x-input-error for="email" :message="$errors->first('email')" />
        </div>
    </div>
    <div class="mt-5">
        <x-checkbox wire:model="privacy" name="privacy" data-test="checkbox_newsletter-privacy" class="whitespace-normal">
            Ich stimme den <a href="{{ route('privacy') }}" title="Zum Datenschutz" class="nz-text-sm cursor-pointer text-current border-b-2 active:border-current hover:border-current">Datenschutzbestimmungen</a> zu
        </x-checkbox>
    </div>
    <x-input-error for="privacy" :message="$errors->first('privacy')" class="!ml-8" />
    <div class="flex justify-center">
        <x-button
            type="submit"
            size="large"
            class="mt-10 bg-blue-400 sm:mt-12"
            data-test="btn_newsletter-subscribe"
            wire:loading.attr="disabled"
            wire:target="subscribe"
            x-on:click="nzToast('loading', 'Deine Anmeldung wird bearbeitet.')"
        >Anmelden</x-button>
    </div>
</form>
