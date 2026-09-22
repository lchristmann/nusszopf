{{-- `PasswordForm.js` (docs/authentication/README.md §4.2): title, description, one password field, "Speichern". --}}
<x-frame fluid class="mt-12 mb-12 sm:mt-16">
    <div class="flex flex-col items-center w-full max-w-sm sm:max-w-md mx-auto bg-white rounded-lg sm:px-12 sm:py-16 px-6 py-10">
        <x-text as="h1" variant="textLgSemi" class="mb-5 text-center">Neues Passwort erstellen</x-text>
        <x-text variant="textSmMedium" class="mb-4">
            Mit deinem neuen Passwort kannst Du dich wie gewohnt einloggen.
        </x-text>
        <form wire:submit="save" class="space-y-4 w-full">
            <div>
                <x-password-field
                    wire:model="password"
                    name="password"
                    data-test="input_new-password"
                    aria-label="Passwort"
                    placeholder="Passwort"
                    autocomplete="off"
                />
                <x-input-error :message="$errors->first('password')" />
            </div>
            <div class="pt-2 text-center">
                <x-button type="submit" class="bg-steel-100" data-test="btn_save-new-password">Speichern</x-button>
            </div>
        </form>
    </div>
</x-frame>
