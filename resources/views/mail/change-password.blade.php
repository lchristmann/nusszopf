{{--
    `auth0/change-password.mjml`, reproduced verbatim (docs/email/README.md
    item 2).
--}}
<x-mail.layout :title="'Nusszopf – Neues Passwort erstellen'">
    <p style="margin:0 0 20px 0; color:#37474F; font-size:16px; font-weight:500; line-height:22px;">
        Hier ist der Link, mit welchem Du dein Passwort ändern kannst. Klicke dazu einfach auf den Button.
    </p>
    <x-mail.button :href="$url">Neues Passwort erstellen</x-mail.button>
    <p style="margin:24px 0 0 0; padding:12px; background-color:#FFF3E0; color:#37474F; font-size:14px; font-weight:500; line-height:20px;">
        Falls Du diese Anfrage nicht gestellt hast, kontaktiere uns bitte via
        <a href="mailto:{{ config('nusszopf.contact_email') }}" style="color:#37474F; text-decoration:underline;">{{ config('nusszopf.contact_email') }}</a>.
    </p>
</x-mail.layout>
