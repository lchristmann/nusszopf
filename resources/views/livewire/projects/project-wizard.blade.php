{{--
    pages/user/project/create.js. Header: progress bar + the project title (or
    "Neues Projekt") as it is typed; body: the current step's two columns; a
    Zurück/Weiter (Erstellen on the last step) navigation. Only the current
    step is in the DOM, as with the historical Stepper.
--}}
<div>
    <x-framed-grid-card class="lg:mb-20 lg:mt-12">
        <x-frame class="bg-lilac-300 lg:bg-steel-100">
            <x-framed-grid-card.header class="bg-lilac-300">
                <x-progressbar :label="$this::STEPS[$this->currentStep()]" :progress="($this->currentStep() + 1) / count($this::STEPS) * 100" />
                <x-text
                    as="h1"
                    variant="textLg"
                    class="block mt-3 hyphens-auto"
                    data-test="title_project-wizard"
                    x-text="$wire.title.length > 0 ? $wire.title : 'Neues Projekt'"
                >{{ $title !== '' ? $title : 'Neues Projekt' }}</x-text>
            </x-framed-grid-card.header>
        </x-frame>

        <form wire:submit="next" novalidate>
            <x-frame class="bg-white lg:bg-steel-100">
                <x-framed-grid-card.body gap="medium" class="grid-flow-row bg-white">
                    @if ($this->currentStep() === 0)
                        <x-framed-grid-card.body-col variant="twoCols" class="lg:pr-4 lg:col-start-2">
                            <x-project-form.title :error="$errors->first('title')" />
                            <x-project-form.goal class="mt-8" :error="$errors->first('goal')" />
                            <x-project-form.description class="mt-7" :error="$errors->first('description')" />
                        </x-framed-grid-card.body-col>
                        <x-framed-grid-card.body-col variant="twoCols" class="lg:pl-4">
                            <x-project-form.location
                                class="mt-5 lg:mt-0"
                                :remote="$location['remote']"
                                :search-term="$location['searchTerm']"
                                :options="$locationOptions"
                                :search-term-error="$errors->first('location.searchTerm')"
                                :data-error="$errors->first('location.data')"
                            />
                            <x-project-form.period
                                class="mt-7"
                                :flexible="$period['flexible']"
                                :from-error="$errors->first('period.from')"
                                :to-error="$errors->first('period.to')"
                            />
                        </x-framed-grid-card.body-col>
                    @elseif ($this->currentStep() === 1)
                        <x-framed-grid-card.body-col variant="twoCols" class="lg:pr-4 lg:col-start-2">
                            <x-project-form.team :error="$errors->first('team')" />
                        </x-framed-grid-card.body-col>
                        <x-framed-grid-card.body-col variant="twoCols" class="lg:pl-4">
                            <x-project-form.motto class="mt-5 lg:mt-0" :error="$errors->first('motto')" />
                        </x-framed-grid-card.body-col>
                    @elseif ($this->currentStep() === 2)
                        {{--
                            RequestsStep.js. Intentional scaffolding (docs/rewrite/second-slice.md,
                            "Gesuche"): ProjectRequests are a later slice, so the "Gesuch erstellen"
                            dialog does not exist yet and the button is inert. Zero requests is the
                            historical default state and passes through unvalidated.
                        --}}
                        <x-framed-grid-card.body-col variant="twoCols" class="text-center lg:text-left lg:pr-4 lg:col-start-2">
                            <x-text class="mb-2 text-left">Projektgesuche</x-text>
                            <x-text variant="textSm" class="text-left">Gesuche in dem Projekt zeigen anderen Nusszopfer:innen, was für die Projektumsetzung noch alles benötigt wird.</x-text>
                            <x-button data-test="btn_create_requests-step" color="stone" size="large" class="mt-8 bg-stone-300" disabled title="Gesuche können in Kürze erstellt werden">
                                <x-slot:iconLeft><x-icon name="plus-circle" class="mr-2 -ml-2" /></x-slot:iconLeft>
                                Gesuch erstellen
                            </x-button>
                        </x-framed-grid-card.body-col>
                        <x-framed-grid-card.body-col variant="twoCols" class="lg:pl-4">
                            <x-info-card class="mt-8 bg-livid-200 text-livid-700 lg:mt-0">Gesuche für das Projekt kannst Du entweder jetzt oder später erstellen.</x-info-card>
                        </x-framed-grid-card.body-col>
                    @else
                        <x-framed-grid-card.body-col variant="twoCols" class="lg:pr-4 lg:col-start-2">
                            <x-project-form.visibility />
                        </x-framed-grid-card.body-col>
                        <x-framed-grid-card.body-col variant="twoCols" class="lg:pl-4">
                            <x-project-form.contact class="mt-6 lg:mt-0" :email="$email" :contact="$contact" />
                        </x-framed-grid-card.body-col>
                    @endif

                    <x-framed-grid-card.body-col variant="oneCol" class="mt-12 mb-4 md:mb-0 lg:col-start-2">
                        <div class="flex items-center justify-center flex-shrink-0 mx-auto">
                            @if ($this->currentStep() > 0)
                                <x-button data-test="btn_go-back_navigation" color="lilac" class="inline-block mr-5" wire:click="back">Zurück</x-button>
                            @endif
                            <x-button
                                type="submit"
                                data-test="btn_create-or-next_navigation"
                                size="large"
                                color="lilac"
                                class="bg-lilac-200"
                                wire:loading.attr="disabled"
                                wire:target="next"
                                x-on:click="if ($wire.step == {{ count($this::STEPS) - 1 }}) nzToast('loading', 'Projekt erstellen...')"
                            >{{ $this->currentStep() === count($this::STEPS) - 1 ? 'Erstellen' : 'Weiter' }}</x-button>
                        </div>
                    </x-framed-grid-card.body-col>
                </x-framed-grid-card.body>
            </x-frame>
        </form>
    </x-framed-grid-card>
</div>
