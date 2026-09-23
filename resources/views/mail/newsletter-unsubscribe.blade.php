{{--
    `sendgrid/newsletter/unsubscribe.mjml`, reproduced verbatim (docs/email/README.md
    item 7) except "Bestätigte" → "Bestätige" (BUG-006).
--}}
<x-mail.layout :title="'Nussiger Newsletter – Abmeldebestätigung'">
    <p style="margin:0 0 10px 0; color:#37474F; font-size:24px; font-weight:700;">Der Nusszopf liebt dich sowieso!</p>
    <p style="margin:0 0 40px 0; color:#37474F; font-size:16px; font-weight:500; line-height:22px;">
        Bestätige deine Abmeldung von dem Newsletter, indem Du auf den Button klickst. Wenn Du möchtest, kannst Du dich natürlich jederzeit wieder anmelden.
    </p>
    <x-mail.button :href="$url">Abmelden bestätigen</x-mail.button>
</x-mail.layout>
