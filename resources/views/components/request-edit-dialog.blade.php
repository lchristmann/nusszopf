@props(['editing' => false, 'loadingToast' => null])

{{--
    EditRequestDialog.js + RequestForm/{Title,Category,Description}Field.js —
    the create/edit dialog shared by the wizard's step 3 and the edit screen's
    "Gesuche" view. A separate form (never nested in the wizard's), bound to the
    host's `request*` properties (App\Livewire\Concerns\ManagesRequestDialog).
    Historically it had no overlay/Escape dismissal (`onDismiss={undefined}`):
    only the X and "Abbrechen", which ask the native `confirm()` when the form
    is dirty (Formik `dirty`). Decision A-7 requires every dialog to close on
    Escape, so Escape now does exactly what "Abbrechen" does, confirm included
    (docs/testing/accessibility.md); an overlay click still does nothing.
--}}
<x-dialog
    data-test="edit-request-dialog"
    label="Gesuch bearbeiten"
    class="relative text-stone-800 bg-stone-200"
    x-trap.noscroll="true"
>
    <form
        wire:submit="saveRequest"
        novalidate
        x-on:keydown.escape.prevent="dismiss()"
        x-data="{
            cat: $wire.requestCategory,
            baseline: null,
            fields() {
                return JSON.stringify([$wire.requestTitle, $wire.requestCategory, $wire.requestDescription]);
            },
            init() { this.baseline = this.fields(); },
            dismiss() {
                if (this.fields() !== this.baseline && ! confirm(@js($editing ? 'Willst Du wirklich abbrechen? Deine Änderung wird nicht gespeichert.' : 'Willst Du wirklich abbrechen? Dein Gesuch wird nicht gespeichert.'))) {
                    return;
                }
                $wire.closeRequestDialog();
            },
        }"
    >
        <x-button
            variant="clean"
            size="baseClean"
            class="absolute top-0 right-0 p-1 m-3"
            aria-label="Schließen"
            x-on:click="dismiss()"
        ><x-icon name="x" /></x-button>

        <x-field-title info="Wie soll das Gesuch heißen?">Titel*</x-field-title>
        <x-input
            data-test="input_request-title"
            color="stone"
            aria-label="Wie soll das Gesuch heißen?"
            name="title"
            maxlength="30"
            wire:model="requestTitle"
            wire:blur="blurredRequest('requestTitle')"
            placeholder="Wer oder was wird gesucht?"
        />
        <x-input-error for="requestTitle" :message="$errors->first('requestTitle')" />

        <x-field-title class="mt-6" info="Wähle eine passende Kategorie für das Gesuch aus!">Kategorie*</x-field-title>
        <x-select
            color="stone"
            data-test="select_request-category"
            name="category"
            aria-label="Kategorie*"
            wire:model="requestCategory"
            wire:blur="blurredRequest('requestCategory')"
            x-model="cat"
            :wrapper-class="'{
                \'bg-red-200\': cat === \'companions\',
                \'bg-yellow-200\': cat === \'rooms\',
                \'bg-turquoise-200\': cat === \'materials\',
                \'bg-blue-200\': cat === \'financials\',
                \'bg-pink-200\': cat === \'others\',
                \'bg-stone-400\': ! [\'companions\', \'rooms\', \'materials\', \'financials\', \'others\'].includes(cat),
            }'"
        >
            <option value="">-</option>
            @foreach (\App\Models\ProjectRequest::CATEGORY_LABELS as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </x-select>
        <x-input-error for="requestCategory" :message="$errors->first('requestCategory')" />

        <x-field-title class="mt-6" info="Beschreibe das Gesuch: Was wird gesucht und wozu? Wann wird es gebraucht?">Beschreibung*</x-field-title>
        <x-rich-text-editor
            data-test="input_request-description"
            property="requestDescription"
            label="Beschreibung"
            color="stone"
            blur-action="blurredRequest"
            placeholder="Was muss man über das Gesuch wissen?"
        />
        <x-input-error for="requestDescription" :message="$errors->first('requestDescription')" />

        <div class="flex justify-center mt-10 space-x-4">
            <x-button
                data-test="btn_create-or-save_edit-request-dialog"
                class="bg-stone-400"
                color="stone"
                type="submit"
                wire:loading.attr="disabled"
                wire:target="saveRequest"
                :x-on:click="$loadingToast ? 'nzToast(\'loading\', '.json_encode($loadingToast, JSON_UNESCAPED_UNICODE).')' : null"
            >{{ $editing ? 'Speichern' : 'Erstellen' }}</x-button>
            <x-button color="stone" x-on:click="dismiss()">Abbrechen</x-button>
        </div>
    </form>
</x-dialog>
