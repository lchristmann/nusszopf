{{--
    New copy, no historical template (docs/rewrite/decisions-register.md, A-3).
--}}
<x-mail.layout :title="'Nusszopf – Bestätige deine E-Mail-Adresse'">
    <p style="margin:0 0 20px 0; color:#37474F; font-size:16px; font-weight:500; line-height:22px;">
        Bitte bestätige deine E-Mail-Adresse, damit Du sie als Kontaktmöglichkeit für deine Projekte
        veröffentlichen und den Nusszopf-Newsletter abonnieren kannst. Für alles andere musst Du nichts tun –
        Du kannst den Nusszopf schon jetzt ganz normal nutzen.
    </p>
    <x-mail.button :href="$url">E-Mail-Adresse bestätigen</x-mail.button>
    <p style="margin:24px 0 0 0; padding:12px; background-color:#FFF3E0; color:#37474F; font-size:14px; font-weight:500; line-height:20px;">
        Falls Du diese Anfrage nicht gestellt hast, kontaktiere uns bitte via
        <a href="mailto:{{ config('nusszopf.contact_email') }}" style="color:#37474F; text-decoration:underline;">{{ config('nusszopf.contact_email') }}</a>.
    </p>
</x-mail.layout>
