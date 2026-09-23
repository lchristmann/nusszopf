{{--
    auth-password `pages/index.js` + `PasswordForm.js` (docs/authentication/README.md §4.2): the big logo, then
    title, description, one password field and "Speichern" in a `FramedCard`.
--}}
<x-framed-card class="bg-white">
    <x-newsletter-logo />
    <div class="w-full mt-10 sm:mt-12 text-steel-700">
        <x-text as="h1" variant="textLgSemi" class="mb-5 text-center">Neues Passwort erstellen</x-text>
        <x-text variant="textSmMedium" class="mb-4">
            Mit deinem neuen Passwort kannst Du dich wie gewohnt einloggen.
        </x-text>
        <form wire:submit="save">
            <x-password-field
                wire:model="password"
                name="password"
                data-test="input_new-password"
                aria-label="Passwort"
                placeholder="Passwort"
                autocomplete="off"
            />
            <x-input-error for="password" :message="$errors->first('password')" />
            <div class="mt-6 text-center">
                <x-button type="submit" class="bg-steel-100" data-test="btn_save-new-password" wire:loading.attr="disabled" wire:target="save">Speichern</x-button>
            </div>
        </form>
    </div>
</x-framed-card>
