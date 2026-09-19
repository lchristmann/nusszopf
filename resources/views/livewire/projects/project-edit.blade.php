{{--
    pages/user/project/[id]/edit.js and containers/user/EditProjectViews/*.
    `x-data` tracks whether the form differs from what was loaded/last saved
    (Formik's `dirty`), so switching views can ask the historical native
    `confirm()` before discarding edits.
--}}
<div
    x-data="{
        baseline: null,
        fields() {
            return JSON.stringify([$wire.title, $wire.goal, $wire.description, $wire.location, $wire.period, $wire.team, $wire.motto, $wire.visibility, $wire.contact]);
        },
        init() { this.baseline = this.fields(); },
        dirty() { return this.fields() !== this.baseline; },
        selectView(event) {
            if (this.dirty() && ! confirm('Möchtest Du die Seite wirklich verlassen? Deine Änderungen gehen dann verloren.')) {
                event.target.value = $wire.view;
                return;
            }
            $wire.selectView(event.target.value);
        },
    }"
    x-on:form-saved.window="$nextTick(() => baseline = fields())"
>
    <x-framed-grid-card class="lg:mb-20 lg:mt-12">
        <x-frame class="bg-lilac-300 lg:bg-steel-100">
            <x-framed-grid-card.header class="bg-lilac-300">
                <div class="flex flex-col justify-between lg:items-center lg:flex-row">
                    <x-text as="h1" variant="textLg" class="mb-4 hyphens-auto lg:mb-0">{{ $project->title }}</x-text>
                    <x-select
                        color="lilac"
                        class="flex-shrink-0 w-56 mb-2 lg:ml-12 lg:mb-0"
                        data-test="select_view_edit-project-page"
                        aria-label="Projekt Bereich auswählen"
                        x-on:change="selectView($event)"
                    >
                        @foreach ($this::VIEWS as $option)
                            <option value="{{ $option }}" @selected($option === $view)>{{ $option }}</option>
                        @endforeach
                    </x-select>
                </div>
            </x-framed-grid-card.header>
        </x-frame>

        <x-frame class="bg-white lg:bg-steel-100">
            @if ($view === 'Beschreibung')
                <form wire:submit="saveProject" novalidate wire:key="view-project">
                    <x-framed-grid-card.body gap="medium" class="grid-flow-row bg-white text-lilac-800">
                        <x-framed-grid-card.body-col variant="twoCols" class="lg:pr-4 lg:col-start-2">
                            <x-project-form.title :error="$errors->first('title')" />
                            <x-project-form.goal class="mt-8" :error="$errors->first('goal')" />
                            <x-project-form.description class="mt-7" :error="$errors->first('description')" />
                            <x-project-form.motto class="mt-8" :error="$errors->first('motto')" />
                        </x-framed-grid-card.body-col>
                        <x-framed-grid-card.body-col variant="twoCols" class="lg:pl-4">
                            <x-project-form.location
                                class="mt-3 lg:mt-0"
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
                            <x-project-form.team class="mt-7" :error="$errors->first('team')" />
                        </x-framed-grid-card.body-col>
                        <x-framed-grid-card.body-col variant="oneCol" class="flex justify-center mt-12 mb-4 md:mb-0 lg:col-start-2">
                            <x-button
                                data-test="btn_save_project-view"
                                class="bg-lilac-200"
                                type="submit"
                                color="lilac"
                                size="large"
                                wire:loading.attr="disabled"
                                wire:target="saveProject"
                                x-on:click="nzToast('loading', 'Änderungen speichern...')"
                            >Speichern</x-button>
                        </x-framed-grid-card.body-col>
                    </x-framed-grid-card.body>
                </form>
            @elseif ($view === 'Gesuche')
                {{-- RequestsView.js — intentional scaffolding until ProjectRequests exist (docs/rewrite/second-slice.md). --}}
                <x-framed-grid-card.body gap="medium" class="grid-flow-row bg-white" wire:key="view-requests">
                    <x-framed-grid-card.body-col variant="twoCols" class="text-center lg:text-left lg:pr-4 lg:col-start-2">
                        <x-text class="mb-2 text-left">Projektgesuche</x-text>
                        <x-text variant="textSm" class="text-left">Gesuche in dem Projekt zeigen anderen Nusszopfer:innen, was für die Projektumsetzung noch alles benötigt wird.</x-text>
                        <x-button data-test="btn_create_requests-view" color="stone" size="large" class="mt-8 bg-stone-300" disabled title="Gesuche können in Kürze erstellt werden">
                            <x-slot:iconLeft><x-icon name="plus-circle" class="mr-2 -ml-2" /></x-slot:iconLeft>
                            Gesuch erstellen
                        </x-button>
                    </x-framed-grid-card.body-col>
                    <x-framed-grid-card.body-col variant="twoCols" class="lg:pl-4">
                        <x-text class="mt-8 mb-4 lg:mt-0">Aktuelle Gesuche</x-text>
                        <x-info-card class="bg-livid-200 text-livid-700">Alles zopfig! Derzeit gibt es keine Gesuche.</x-info-card>
                    </x-framed-grid-card.body-col>
                </x-framed-grid-card.body>
            @else
                <form wire:submit="saveSettings" novalidate wire:key="view-settings">
                    <x-framed-grid-card.body gap="medium" class="grid-flow-row bg-white">
                        <x-framed-grid-card.body-col variant="twoCols" class="lg:pr-4 lg:col-start-2">
                            <x-project-form.visibility />
                        </x-framed-grid-card.body-col>
                        <x-framed-grid-card.body-col variant="twoCols" class="lg:pl-4">
                            <x-project-form.contact class="mt-4 lg:mt-0" :email="$email" :contact="$contact" />
                        </x-framed-grid-card.body-col>
                        <x-framed-grid-card.body-col variant="oneCol">
                            <div class="mt-5 text-center sm:text-left lg:text-center">
                                <x-button
                                    data-test="btn_save_settings-view"
                                    type="submit"
                                    class="bg-lilac-200"
                                    color="lilac"
                                    wire:loading.attr="disabled"
                                    wire:target="saveSettings"
                                    x-on:click="nzToast('loading', 'Änderungen speichern...')"
                                >Speichern</x-button>
                            </div>
                        </x-framed-grid-card.body-col>
                        <x-framed-grid-card.body-col variant="twoCols" class="lg:pr-4 lg:col-start-2 text-warning-700">
                            <x-text class="mt-10 mb-2 text-left lg:mt-8">Projekt löschen</x-text>
                            <x-text variant="textSm" class="text-left">Nach dem Löschen können die Daten nicht wieder hergestellt werden.</x-text>
                            <div class="mt-4 text-center sm:text-left">
                                <x-button
                                    data-test="btn_delete_settings-view"
                                    color="warning"
                                    wire:loading.attr="disabled"
                                    wire:target="deleteProject"
                                    x-on:click="if (confirm('Möchtest Du das Projekt wirklich löschen?')) { nzToast('loading', 'Wird gelöscht...'); $wire.deleteProject(); }"
                                >Löschen</x-button>
                            </div>
                        </x-framed-grid-card.body-col>
                    </x-framed-grid-card.body>
                </form>
            @endif
        </x-frame>
    </x-framed-grid-card>
</div>
