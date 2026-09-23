@props(['project', 'open', 'close'])

{{--
    ContactDialog.js — the "Über Nusszopf" contact form, shown only when
    `Project::hasPersonalContact()` is false. Bound to the host Livewire
    component's `contactEmail`/`contactMsg`/`submitContact()`
    (App\Livewire\Projects\ProjectDetail) rather than a separate nested
    component, the same pattern `App\Livewire\Concerns\ManagesRequestDialog`
    already uses for the request create/edit dialog. Dismissal asks the
    native `confirm()` only when the form is dirty, matching the historical
    Formik `dirty` check (`onDismiss={undefined}` — no overlay/Escape close).
--}}
<x-dialog
    data-test="contact-dialog"
    label="Kontaktieren"
    class="relative text-lilac-800 bg-lilac-200"
    x-show="{{ $open }}"
    x-cloak
    x-trap.noscroll="{{ $open }}"
>
    <form
        wire:submit="submitContact"
        novalidate
        x-data="{
            baseline: null,
            fields() { return JSON.stringify([$wire.contactEmail, $wire.contactMsg]); },
            init() { this.baseline = this.fields(); },
            dismiss() {
                if (this.fields() !== this.baseline && ! confirm('Willst Du wirklich abbrechen? Deine Nachricht wird nicht gespeichert.')) {
                    return;
                }
                {{ $close }}
            },
        }"
        x-on:contact-sent.window="{{ $close }}"
    >
        <x-button
            variant="clean"
            size="baseClean"
            class="absolute top-0 right-0 p-1 m-3"
            aria-label="Schließen"
            x-on:click="dismiss()"
        ><x-icon name="x" /></x-button>

        <x-text variant="textLg">{{ $project->title }}</x-text>
        <x-text variant="textSm">Schreibe dem Projekt eine Nachricht. Diese wird für dich über den Nusszopf per E-Mail versendet.</x-text>

        <x-field-title class="mt-6" info="Gib eine E-Mail-Adresse ein, unter welcher Du von dem Projekt kontaktiert werden kannst.">Deine E-Mail-Adresse*</x-field-title>
        <x-input
            data-test="input_contact-email"
            color="lilac"
            type="email"
            aria-label="Deine E-Mail-Adresse*"
            maxlength="100"
            wire:model="contactEmail"
            placeholder="beispiel@mail.de"
        />
        <x-input-error for="contactEmail" :message="$errors->first('contactEmail')" class="!text-warning-750" />

        <x-text class="mt-6 mb-3">Deine Nachricht*</x-text>
        <x-input
            as="textarea"
            data-test="input_contact-msg"
            aria-label="Deine Nachricht*"
            class="min-h-48"
            color="lilac"
            maxlength="2000"
            wire:model="contactMsg"
            placeholder="..."
        />
        {{-- BUG-042: the dialog's lilac-200 needs the slightly darker warning-750 (4.6:1). --}}
        <x-input-error for="contactMsg" :message="$errors->first('contactMsg')" class="!text-warning-750" />

        <div class="flex justify-center mt-12 space-x-4">
            <x-button
                data-test="btn_send_contact-dialog"
                type="submit"
                class="bg-lilac-300"
                color="lilac"
                wire:loading.attr="disabled"
                wire:target="submitContact"
                x-on:click="nzToast('loading', 'Nachricht wird versendet...')"
            >Senden</x-button>
            <x-button color="lilac" x-on:click="dismiss()">Abbrechen</x-button>
        </div>
    </form>
</x-dialog>
