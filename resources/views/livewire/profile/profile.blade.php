<div x-data="{ avatarDialogOpen: false }" x-on:keydown.escape.window="avatarDialogOpen = false">
    <x-frame>
        <x-framed-grid-card class="lg:mb-20 lg:mt-12">
            <x-frame class="bg-steel-200 lg:bg-steel-100 rounded-t-lg">
                <x-framed-grid-card.header class="bg-steel-200 lg:bg-steel-100">
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
            <x-frame class="bg-white lg:bg-steel-100 rounded-b-lg">
                <x-framed-grid-card.body>
                    <x-framed-grid-card.body-col variant="twoCols" class="lg:col-start-2">
                        {{--
                            Newsletter subsection: intentional scaffolding
                            (docs/rewrite/master-roadmap.md, "Slice 8"). The
                            `Lead` model, double opt-in and consent record
                            don't exist until slice 9 (decision A-1) — the
                            historical copy/layout is reproduced, but the
                            control is inert rather than pretending to
                            subscribe anyone.
                        --}}
                        <div id="newsletter">
                            <x-text variant="textMd" class="mb-2">Newsletter</x-text>
                            <x-text variant="textSm" class="mb-2">Wir versorgen Dich mit backfrischen Nusszopf­neuigkeiten, inspirierenden Projekten und allem, was uns sonst noch so einfällt.</x-text>
                            <x-checkbox disabled aria-label="Datenschutzerklärung" class="whitespace-normal">
                                Ich stimme den <a href="{{ route('privacy') }}?back=1" title="Zum Datenschutz" aria-label="Zum Datenschutz" class="italic underline">Datenschutzbedingungen</a> zu
                            </x-checkbox>
                            <x-button disabled class="block mx-auto mt-6 sm:mt-4 bg-steel-100 sm:ml-0">Anmelden</x-button>
                            <x-text variant="textSm" class="mt-2 italic">Folgt in Kürze.</x-text>
                        </div>

                        {{--
                            Sponsoring: the historical Steady funding page
                            stays the literal historical URL for now, the
                            same "self-hosting operational default, not yet
                            an operator setting" gap already recorded for
                            NUSSZOPF_CONTACT/the Instagram link
                            (resources/views/components/mail/layout.blade.php).
                        --}}
                        <div id="sponsoring" class="mt-12 text-center sm:text-left">
                            <x-text variant="textMd" class="mb-2 text-left">Fördermitgliedschaft</x-text>
                            <x-text variant="textSm" class="text-left">Passe deine Mitgliedschaft auf unserer Steady-Förderungswebseite an.</x-text>
                            <x-button
                                as="a"
                                href="https://steadyhq.com/de/nusszopf"
                                target="_blank"
                                rel="noopener"
                                title="Zur Steady-Förderungswebseite"
                                aria-label="Zur Steady-Förderungswebseite"
                                class="block mt-6 sm:mt-4 bg-steel-100"
                            >Steady öffnen</x-button>
                        </div>
                    </x-framed-grid-card.body-col>

                    <x-framed-grid-card.body-col variant="twoCols">
                        <div id="delete" class="mt-10 text-warning-700 lg:ml-16 lg:mt-0">
                            <x-text variant="textMd" class="mb-2">Account löschen</x-text>
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

                        {{--
                            The historical "Kontakt speichern" vCard InfoCard
                            is not reproduced — same reasoning as the mail
                            footer's own omission of it
                            (resources/views/components/mail/layout.blade.php):
                            it hardcodes the *original* nusszopf.org's own
                            contact identity, which would misattribute a
                            stranger's operator identity from every
                            self-hosted instance.
                        --}}
                        <x-info-card class="mt-5 text-gray-700 bg-gray-200 lg:ml-16">
                            Bei Fragen kannst Du dich immer unter
                            <a href="mailto:{{ \App\Models\Project::NUSSZOPF_CONTACT }}" title="E-Mail an Nusszopf senden" aria-label="E-Mail an Nusszopf senden" class="underline">{{ \App\Models\Project::NUSSZOPF_CONTACT }}</a>
                            bei uns melden!
                        </x-info-card>
                    </x-framed-grid-card.body-col>
                </x-framed-grid-card.body>
            </x-frame>
        </x-framed-grid-card>
    </x-frame>

    <x-avatar-dialog open="avatarDialogOpen" close="avatarDialogOpen = false" />
</div>
