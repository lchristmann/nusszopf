<x-frame class="mt-12 mb-16">
    <div class="max-w-md mx-auto bg-white rounded-lg shadow-md p-8">
        <div class="flex gap-6 mb-8 border-b-2 border-steel-200">
            <button
                type="button"
                wire:click="$set('tab', 'login')"
                @class([
                    'pb-3 -mb-0.5 border-b-2',
                    'border-lilac-800' => $tab === 'login',
                    'border-transparent' => $tab !== 'login',
                ])
            >
                <x-text as="span" variant="textMd">Einloggen</x-text>
            </button>
            <button
                type="button"
                wire:click="$set('tab', 'register')"
                @class([
                    'pb-3 -mb-0.5 border-b-2',
                    'border-lilac-800' => $tab === 'register',
                    'border-transparent' => $tab !== 'register',
                ])
            >
                <x-text as="span" variant="textMd">Registrieren</x-text>
            </button>
        </div>

        @if ($tab === 'login')
            <form wire:submit="login" class="space-y-4">
                <div>
                    <x-input
                        wire:model="emailOrName"
                        name="emailOrName"
                        aria-label="E-Mail-Adresse / Username"
                        placeholder="E-Mail-Adresse / Username"
                        color="lilac"
                    />
                    <x-input-error :message="$errors->first('emailOrName')" />
                </div>
                <div>
                    <x-input
                        wire:model="loginPassword"
                        type="password"
                        name="loginPassword"
                        aria-label="Passwort"
                        placeholder="Passwort"
                        color="lilac"
                    />
                    <x-input-error :message="$errors->first('loginPassword')" />
                </div>
                <div class="text-center pt-2">
                    <x-button type="submit" color="lilac">Einloggen</x-button>
                </div>
            </form>
        @else
            <form wire:submit="register" class="space-y-4">
                <div>
                    <x-input
                        wire:model="username"
                        name="username"
                        aria-label="Öffentlicher Username"
                        placeholder="Öffentlicher Username"
                        color="lilac"
                        maxlength="15"
                    />
                    <x-input-error :message="$errors->first('username')" />
                </div>
                <div>
                    <x-input
                        wire:model="email"
                        type="email"
                        name="email"
                        aria-label="E-Mail-Adresse"
                        placeholder="E-Mail-Adresse"
                        color="lilac"
                    />
                    <x-input-error :message="$errors->first('email')" />
                </div>
                <div>
                    <x-input
                        wire:model="registerPassword"
                        type="password"
                        name="registerPassword"
                        aria-label="Passwort"
                        placeholder="Passwort"
                        color="lilac"
                    />
                    <x-input-error :message="$errors->first('registerPassword')" />
                </div>
                <div>
                    <x-checkbox wire:model="privacy" name="privacy" aria-label="Datenschutzerklärung">
                        Ich stimme den
                        <a href="{{ route('privacy') }}" class="underline">Datenschutzbedingungen</a>
                        zu
                    </x-checkbox>
                    <x-input-error :message="$errors->first('privacy')" class="!ml-8" />
                </div>
                <div class="text-center pt-2">
                    <x-button type="submit" color="lilac">Registrieren</x-button>
                </div>
            </form>
        @endif
    </div>
</x-frame>
