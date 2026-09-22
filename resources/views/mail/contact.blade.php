{{--
    docs/email/README.md item 5 (`sendgrid/contact.mjml`), reproduced verbatim.
    Every variable uses `{{ }}` (never `{!! !!}`), so Blade's default escaping
    applies to the visitor-supplied fields — closes BUG-010's output-safety
    half; the historical `private_msg`/`contact_email` were similarly rendered
    as plain text by Handlebars, not the triple-brace unescaped form.
--}}
<x-mail.layout :title="'Nusszopf – Kontaktanfrage'">
    <p style="margin:0 0 10px 0; color:#37474F; font-size:24px; font-weight:700;">Nussige Nachricht</p>
    <p style="margin:0 0 24px 0; color:#37474F; font-size:16px; font-weight:500; line-height:22px;">
        Eine nussige Nachricht wurde an das Projekt
        <strong>{{ $requestTitle !== null && $requestTitle !== '' ? $projectTitle.' / '.$requestTitle : $projectTitle }}</strong>
        geschickt!
    </p>
    <p style="margin:0 0 24px 0; padding:12px; background-color:#EEF5F7; color:#213E45; border-radius:8px; font-size:16px; font-weight:500; line-height:22px;">{{ $visitorMessage }}</p>
    <p style="margin:0; color:#37474F; font-size:16px; font-weight:500; line-height:22px;">
        Du kannst die Person unter folgender E-Mail-Adresse erreichen: <strong>{{ $visitorEmail }}</strong>.
    </p>
</x-mail.layout>
