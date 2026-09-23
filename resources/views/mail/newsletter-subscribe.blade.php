{{--
    `sendgrid/newsletter/subscribe.mjml`, reproduced verbatim (docs/email/README.md
    item 6) except "Bestätigte" → "Bestätige" (BUG-006).
--}}
<x-mail.layout :title="'Nussiger Newsletter – Anmeldebestätigung'">
    <p style="margin:0 0 10px 0; color:#37474F; font-size:24px; font-weight:700;">Der News&shy;letter ist zum Grei&shy;fen nah!</p>
    <p style="margin:0 0 40px 0; color:#37474F; font-size:16px; font-weight:500; line-height:22px;">
        Bestätige deine E-Mail-Adresse durch einen Klick auf den Button und schon bist Du zum Newsletter angemeldet!
    </p>
    <x-mail.button :href="$url">E-Mail-Adresse bestätigen</x-mail.button>
</x-mail.layout>
