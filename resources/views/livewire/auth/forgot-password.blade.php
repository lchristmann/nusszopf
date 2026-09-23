{{--
    `ChangePasswordForm.js` (docs/authentication/README.md §4.1): title, description, one e-mail field,
    "Senden"/"Abbrechen", in the login app's `FramedCard` under the big logo. Historically a view swap inside
    that card; here its own route (App\Livewire\Auth\ForgotPassword). Sending answers with toasts only.
--}}
<x-framed-card class="bg-white">
    <x-newsletter-logo />
    <div class="w-full mt-10 sm:mt-12 text-steel-700">
        <x-text as="h1" variant="textLgSemi" class="mb-5 text-center">Passwort vergessen</x-text>
        <x-text variant="textSmMedium" class="mb-4">
            Wir senden dir einen Link zu, mit dem Du ein neues Passwort erstellen kannst.
        </x-text>
        <form wire:submit="send">
            <x-input
                wire:model="email"
                type="email"
                name="email"
                data-test="input_forgot-password-email"
                aria-label="E-Mail-Adresse"
                placeholder="E-Mail-Adresse"
                autocomplete="off"
            />
            <x-input-error :message="$errors->first('email')" />
            <div class="mt-6 space-x-4 text-center">
                <x-button type="submit" class="bg-steel-100" data-test="btn_send-reset-link" wire:loading.attr="disabled" wire:target="send">Senden</x-button>
                <x-button :as="'a'" href="{{ route('login') }}" data-test="btn_cancel-reset">Abbrechen</x-button>
            </div>
        </form>
    </div>
</x-framed-card>
