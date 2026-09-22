{{--
    `ChangePasswordForm.js` (docs/authentication/README.md §4.1): title,
    description, one e-mail field, "Senden"/"Abbrechen". Historically this
    was an inline view swap inside the same `FramedCard`; here it's its own
    route (see App\Livewire\Auth\ForgotPassword's docblock).
--}}
<x-frame fluid class="mt-12 mb-12 sm:mt-16">
    <div class="flex flex-col items-center w-full max-w-sm sm:max-w-md mx-auto bg-white rounded-lg sm:px-12 sm:py-16 px-6 py-10">
        <x-text as="h1" variant="textLgSemi" class="mb-5 text-center">Passwort vergessen</x-text>

        @if ($sent)
            <x-text variant="textSmMedium" class="mb-4 text-center" data-test="text_reset-link-sent">
                E-Mail verschickt!
            </x-text>
            <div class="pt-2">
                <x-button :as="'a'" href="{{ route('login') }}" data-test="btn_back-to-login">Zurück zum Login</x-button>
            </div>
        @else
            <x-text variant="textSmMedium" class="mb-4">
                Wir senden dir einen Link zu, mit dem Du ein neues Passwort erstellen kannst.
            </x-text>
            <form wire:submit="send" class="space-y-4 w-full">
                <div>
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
                </div>
                <div class="pt-2 text-center space-x-4">
                    <x-button type="submit" class="bg-steel-100" data-test="btn_send-reset-link">Senden</x-button>
                    <x-button :as="'a'" href="{{ route('login') }}" data-test="btn_cancel-reset">Abbrechen</x-button>
                </div>
            </form>
        @endif
    </div>
</x-frame>
