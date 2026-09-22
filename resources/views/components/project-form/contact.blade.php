@props(['email', 'contact'])

{{-- ContactField.js. The historical radio group carried the *visibility* aria-label (copy/paste slip); it is labelled for what it is here. --}}
<x-field-title {{ $attributes }} info="Kann man dich über deine E-Mail-Adresse kontaktieren oder soll die Kommunikation erst einmal über den Nusszopf laufen?  Bedenke, dass es hierbei  auch um deinen Datenschutz geht.">Kontaktmöglichkeit</x-field-title>
<div role="radiogroup" aria-label="Kontaktmöglichkeit">
    <x-radiobox data-test="radio_direct_project-contact" name="contact" value="1" :checked="$contact" wire:click="$set('contact', true)">
        <x-text variant="textSmMedium">Persönlich</x-text>
        <x-text variant="textSm">Meine E-Mail-Adresse ist öffentlich einsehbar. Kontaktmöglichkeit: <span class="underline">{{ \Illuminate\Support\Str::limit($email, 22, '...') }}</span></x-text>
    </x-radiobox>
    <x-radiobox data-test="radio_nusszopf_project-contact" name="contact" value="0" :checked="! $contact" wire:click="$set('contact', false)" class="mt-4">
        <x-text variant="textSmMedium">Über Nusszopf</x-text>
        <x-text variant="textSm">Meine E-Mail-Adresse wird nicht angezeigt, der Erstkontakt läuft über den Nusszopf</x-text>
    </x-radiobox>
</div>
{{--
    Decision A-3 (docs/rewrite/decisions-register.md): "Persönlich" needs a
    verified e-mail address — new gate, no historical error copy to mirror.
--}}
<x-input-error :message="$errors->first('contact')" />
@if ($errors->has('contact'))
    <button type="button" wire:click="resendVerificationEmail" data-test="btn_resend-verification" class="mt-1 ml-4 text-sm underline text-steel-700">
        Bestätigungs-E-Mail erneut senden
    </button>
@endif
