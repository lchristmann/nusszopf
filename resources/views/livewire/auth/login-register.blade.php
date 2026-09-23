{{--
    auth-login/src/pages/index.js: `FramedCard className="bg-white"` with the big logo above the `Tab`
    switcher (docs/rewrite/golden-master-review.md, "Nusszopf logo on the login/register card" — the logo
    exists since slice 9). Buttons/inputs use the library default `steel` colour; the submit buttons are
    `bg-steel-100`. The Auth0 app's page is `bg-white sm:bg-steel-100` (`LoginRegister`'s layout).
--}}
<x-framed-card class="bg-white">
    <x-newsletter-logo />
    {{-- Tab.organism.js: a sliding pill toggle, `mt-12` under the logo. --}}
    <div class="relative w-full h-12 mt-12 rounded-full border-2 border-steel-700 ring-2 ring-transparent hover:ring-steel-700/25 transition duration-200">
        <div
            @class([
                'absolute inset-y-0 w-1/2 bg-steel-700 transition-transform duration-200 ease-in-out',
                'translate-x-0 rounded-l-full' => $tab === 'login',
                'translate-x-full rounded-r-full' => $tab === 'register',
            ])
            aria-hidden="true"
        ></div>
        <div class="relative flex w-full h-full">
            <button
                type="button"
                data-test="tab_login"
                wire:click="$set('tab', 'login')"
                @class(['w-1/2 text-lg font-medium', 'text-white' => $tab === 'login', 'text-steel-700' => $tab !== 'login'])
            >
                Einloggen
            </button>
            <button
                type="button"
                data-test="tab_register"
                wire:click="$set('tab', 'register')"
                @class(['w-1/2 text-lg font-medium', 'text-white' => $tab === 'register', 'text-steel-700' => $tab !== 'register'])
            >
                Registrieren
            </button>
        </div>
    </div>

    @if ($tab === 'login')
        <form wire:submit="login" class="w-full mt-5 text-steel-700">
            <div>
                <x-input
                    wire:model="emailOrName"
                    name="emailOrName"
                    data-test="input_email-or-name"
                    aria-label="E-Mail-Adresse / Username"
                    placeholder="E-Mail-Adresse / Username"
                />
                <x-input-error :message="$errors->first('emailOrName')" />
            </div>
            <div class="mt-4">
                <x-password-field
                    wire:model="loginPassword"
                    name="loginPassword"
                    data-test="input_login-password"
                    aria-label="Passwort"
                    placeholder="Passwort"
                />
                <x-input-error :message="$errors->first('loginPassword')" />
            </div>
            <div class="mt-5 text-center">
                <x-button type="submit" data-test="btn_login" class="mx-1.5 mb-4 sm:mx-2 bg-steel-100">Einloggen</x-button>
                <x-button :as="'a'" href="{{ route('password.request') }}" data-test="btn_forgot-password" class="mx-1.5 mb-4 sm:mx-2">Passwort vergessen</x-button>
            </div>
        </form>
        @if ($googleConfigured)
            <div>
                <div class="flex items-center justify-center mt-2">
                    <div class="w-10 h-px mr-3 bg-steel-700 sm:w-20"></div>
                    <x-text variant="textSm" class="text-center">Oder einloggen mit</x-text>
                    <div class="w-10 h-px ml-3 bg-steel-700 sm:w-20"></div>
                </div>
                <div class="mt-6 text-center">
                    {{-- LoginForm.js: the disabled Apple button beside it is not reproduced (BUG-012). --}}
                    <x-button :as="'a'" href="{{ route('google.redirect') }}" data-test="btn_login-google" class="bg-steel-100">
                        <x-slot:iconLeft><x-icon name="google" :size="20" class="w-auto h-5 mr-2" /></x-slot:iconLeft>
                        Google
                    </x-button>
                </div>
            </div>
        @endif
    @else
        <form wire:submit="register" class="w-full mt-5 text-steel-700">
            <div>
                <x-input
                    wire:model="username"
                    name="username"
                    data-test="input_username"
                    aria-label="Öffentlicher Username"
                    placeholder="Öffentlicher Username"
                    maxlength="15"
                />
                <x-input-error :message="$errors->first('username')" />
            </div>
            <div class="mt-4">
                <x-input
                    wire:model="email"
                    type="email"
                    name="email"
                    data-test="input_email"
                    aria-label="E-Mail-Adresse"
                    placeholder="E-Mail-Adresse"
                />
                <x-input-error :message="$errors->first('email')" />
            </div>
            <div class="mt-4">
                <x-password-field
                    wire:model="registerPassword"
                    name="registerPassword"
                    data-test="input_register-password"
                    aria-label="Passwort"
                    placeholder="Passwort"
                />
                <x-input-error :message="$errors->first('registerPassword')" />
            </div>
            <div class="mt-4">
                <x-checkbox wire:model="privacy" name="privacy" data-test="checkbox_privacy" aria-label="Datenschutzerklärung">
                    Ich stimme den
                    <a href="{{ route('privacy') }}" class="underline">Datenschutzbedingungen</a>
                    zu
                </x-checkbox>
                <x-input-error :message="$errors->first('privacy')" class="!ml-8" />
            </div>
            <div class="mt-2">
                {{--
                    Inventory item 27: checked, registration requests a
                    pending newsletter subscription and sends the
                    double-opt-in mail (decision A-1, BUG-011).
                --}}
                <x-checkbox wire:model="newsletter" name="newsletter" data-test="checkbox_newsletter" aria-label="Nussigen Newsletter abonnieren" class="whitespace-normal">
                    Nussigen Newsletter abonnieren
                </x-checkbox>
            </div>
            <div class="mt-6 text-center">
                <x-button type="submit" data-test="btn_register" class="bg-steel-100">Registrieren</x-button>
            </div>
        </form>
    @endif
</x-framed-card>
