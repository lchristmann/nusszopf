<div x-data="{ avatarDialogOpen: false }" x-on:keydown.escape.window="avatarDialogOpen = false">
    <x-framed-grid-card class="lg:mb-20 lg:mt-12">
        {{-- profile.js: headerColor/bodyColor on the frames, `bg-steel-200` / `bg-white` on the card itself. --}}
        <x-frame class="bg-steel-200 lg:bg-steel-100">
            <x-framed-grid-card.header class="bg-steel-200">
                <div class="flex flex-col sm:flex-row sm:justify-between sm:flex-row-reverse sm:items-center">
                    <x-text as="h1" variant="textLg" class="-mt-2 sm:ml-6 sm:mt-0">Einstellungen</x-text>
                    <x-avatar
                        :user="auth()->user()"
                        variant="settings"
                        edit="avatarDialogOpen = true"
                        class="mt-4 sm:mt-0"
                    />
                </div>
            </x-framed-grid-card.header>
        </x-frame>
        <x-frame class="bg-white lg:bg-steel-100">
            <x-framed-grid-card.body class="bg-white">
                <x-framed-grid-card.body-col variant="twoCols" class="lg:col-start-2">
                    {{--
                        Newsletter (`profile.js`): the subscribe form until
                        the lead is confirmed (`!lead.hasConfirmed`), then
                        the unsubscribe button. Subscribing requests a
                        pending lead and mails the confirmation link
                        (decision A-1, BUG-011).
                    --}}
                    <div id="newsletter">
                        <x-text as="h2" variant="textMd" class="mb-2">Newsletter</x-text>
                        @if ($newsletterConfirmed)
                            <x-text variant="textSm" class="mb-2">Du möchtest dich vom nussigsten Newsletter aller Zeiten abmelden?</x-text>
                            <x-button
                                data-test="btn_newsletter-unsubscribe_settings-page"
                                class="block mx-auto mt-6 sm:mt-4 bg-steel-100 sm:ml-0"
                                wire:loading.attr="disabled"
                                wire:target="unsubscribeNewsletter"
                                x-on:click="if (confirm('Willst Du dich wirklich vom Newsletter abmelden?')) { nzToast('loading', 'Du wirst abgemeldet.'); $wire.unsubscribeNewsletter(); }"
                            >Abmelden</x-button>
                        @else
                            <form wire:submit="subscribeNewsletter">
                                <x-text variant="textSm" class="mb-2">Wir versorgen Dich mit backfrischen Nusszopf­neuigkeiten, inspirierenden Projekten und allem, was uns sonst noch so einfällt.</x-text>
                                <x-checkbox wire:model="newsletterPrivacy" name="newsletterPrivacy" data-test="checkbox_newsletter_settings-page" class="whitespace-normal">
                                    Ich stimme den <a href="{{ route('privacy', ['back' => 'history']) }}" title="Zum Datenschutz" class="italic underline">Datenschutzbedingungen</a> zu
                                </x-checkbox>
                                <x-input-error for="newsletterPrivacy" :message="$errors->first('newsletterPrivacy')" class="!mt-1 mb-3 !ml-6" />
                                <x-button
                                    type="submit"
                                    data-test="btn_newsletter-subscribe_settings-page"
                                    class="block mx-auto mt-6 sm:mt-4 bg-steel-100 sm:ml-0"
                                    wire:loading.attr="disabled"
                                    wire:target="subscribeNewsletter"
                                    x-on:click="nzToast('loading', 'Du wirst angemeldet.')"
                                >Anmelden</x-button>
                            </form>
                        @endif
                    </div>

                    {{--
                        Sponsoring: the historical Steady funding page
                        stays the literal historical brand URL, like the
                        Instagram link and Home's own "Werde
                        Fördermitglied!" button (decision A-5).
                    --}}
                    <div id="sponsoring" class="mt-12 text-center sm:text-left">
                        <x-text as="h2" variant="textMd" class="mb-2 text-left">Fördermitgliedschaft</x-text>
                        <x-text variant="textSm" class="text-left">Passe deine Mitgliedschaft auf unserer Steady-Förderungswebseite an.</x-text>
                        <x-button
                            as="a"
                            href="https://steadyhq.com/de/nusszopf"
                            target="_blank"
                            rel="noopener"
                            title="Zur Steady-Förderungswebseite"
                            class="block mt-6 sm:mt-4 bg-steel-100"
                        >Steady öffnen</x-button>
                    </div>
                </x-framed-grid-card.body-col>

                <x-framed-grid-card.body-col variant="twoCols">
                    <div id="delete" class="mt-10 text-warning-700 lg:ml-16 lg:mt-0">
                        <x-text as="h2" variant="textMd" class="mb-2">Account löschen</x-text>
                        <x-text variant="textSm">Nach dem Löschen können deine Daten nicht wieder hergestellt werden.</x-text>
                        <x-button
                            data-test="btn_delete-account_settings-page"
                            variant="outline"
                            color="warning"
                            class="block mx-auto mt-6 sm:mt-4 sm:ml-0"
                            wire:loading.attr="disabled"
                            wire:target="deleteAccount"
                            x-on:click="if (confirm('Willst du deinen Account wirklich löschen?')) { nzToast('loading', 'Dein Account wird gelöscht.'); $wire.deleteAccount(); }"
                        >Löschen</x-button>
                    </div>

                    {{-- `profile.data.js` `info.contact`/`info.support`: two `InfoCard`s with `livid` links. --}}
                    <x-info-card class="text-gray-700 bg-gray-200 mt-14 lg:ml-16">
                        Füge den Nusszopf zu deinen Kontakten hinzu, damit unsere E-Mails dich sicher erreichen:
                        <a href="{{ route('contact.vcard') }}" data-test="link_vcard_settings-page" title="Nusszopf als Kontakt speichern" aria-label="Nusszopf als Kontakt speichern" class="border-b-2 cursor-pointer nz-text-sm nz-link-livid">Kontakt speichern</a>
                    </x-info-card>
                    <x-info-card class="mt-5 text-gray-700 bg-gray-200 lg:ml-16">
                        Bei Fragen kannst Du dich immer unter
                        <a href="mailto:{{ config('nusszopf.contact_email') }}" title="E-Mail an Nusszopf senden" class="border-b-2 cursor-pointer nz-text-sm nz-link-livid">{{ config('nusszopf.contact_email') }}</a>
                        bei uns melden!
                    </x-info-card>
                </x-framed-grid-card.body-col>
            </x-framed-grid-card.body>
        </x-frame>
    </x-framed-grid-card>

    <x-avatar-dialog open="avatarDialogOpen" close="avatarDialogOpen = false" />
</div>
