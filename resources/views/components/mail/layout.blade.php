@props(['title' => null])

{{--
    Shared mail template anatomy (docs/email/README.md, "Shared template
    anatomy"): every historical MJML template compiles to this same shell —
    body background #ECEFF1, a white content card, Barlow 500/700, and a
    #CFD8DC footer band with the three icon links, a "Nusszopf als Kontakt
    speichern" vCard link and a "Datenschutzerklärung" link. Hand-authored
    table/inline-style HTML rather than run an MJML build step (register B-5
    names that as the recommended default; this slice's own decision is
    recorded in docs/rewrite/sixth-slice.md) — MJML is an authoring tool for
    the same table-based output every mail client already needs, not a
    product-fidelity requirement in itself, the same relationship Tailwind has
    to the historical UI (CLAUDE.md).

    Typography: Barlow is served by this instance itself (public/build/fonts/,
    emitted by vite.config.js under fixed names), never by Google Fonts, so
    opening a mail contacts no third party (decision C6). Clients that ignore
    web fonts (Gmail, Outlook) fall back to Arial, and so does any client that
    blocks remote content.

    Footer links follow this instance's identity (decision A-5, slice 10):
    the mail icon uses `config('nusszopf.contact_email')` and "Nusszopf als
    Kontakt speichern" downloads the vCard generated from it
    (`App\Support\Operator::vcard()`). Instagram stays the literal historical
    brand URL.
    "Zum Nusszopf" and "Datenschutzerklärung" link to this instance's own URLs
    (`url('/')` / `route('privacy')`), not the historical nusszopf.org — the
    historical target is the wrong destination for a self-hosted copy of the
    app, the same "dependency dropped, self-hosted replacement" category as
    the ui-avatars.com avatar fallback (docs/rewrite/intentional-changes.md).
--}}
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="color-scheme" content="light">
<title>{{ $title ?? config('app.name') }}</title>
<style>
@font-face { font-family: 'Barlow'; font-style: normal; font-weight: 500; src: url('{{ asset('build/fonts/barlow-latin-500.woff2') }}') format('woff2'); }
@font-face { font-family: 'Barlow'; font-style: normal; font-weight: 700; src: url('{{ asset('build/fonts/barlow-latin-700.woff2') }}') format('woff2'); }
</style>
</head>
<body style="margin:0; padding:0; background-color:#ECEFF1;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#ECEFF1;">
    <tr>
        <td align="center" style="padding:0;">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%; max-width:600px; background-color:#ffffff;">
                <tr>
                    <td style="padding:32px 24px 0 24px;">
                        <x-icon name="nusszopf-header-logo" :size="72" style="color:#37474F; width:72px; height:72px; display:block;" />
                    </td>
                </tr>
                <tr>
                    <td style="padding:24px 24px 60px 24px; font-family:'Barlow',Arial,sans-serif;">
                        {{ $slot }}
                    </td>
                </tr>
            </table>
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%; max-width:600px; background-color:#CFD8DC;">
                <tr>
                    <td align="center" style="padding:24px 24px 8px 24px;">
                        <a href="https://www.instagram.com/nuss.zopf/" title="Zu Instagram" target="_blank" style="text-decoration:none; display:inline-block; margin:0 8px;">
                            <x-icon name="instagram" :size="22" style="color:#263238;" />
                        </a>
                        <a href="mailto:{{ config('nusszopf.contact_email') }}" title="E-Mail schreiben" style="text-decoration:none; display:inline-block; margin:0 8px;">
                            <x-icon name="mail" :size="22" style="color:#263238;" />
                        </a>
                        <a href="{{ url('/') }}" title="Zum Nusszopf" target="_blank" style="text-decoration:none; display:inline-block; margin:0 8px;">
                            <x-icon name="link" :size="22" style="color:#263238;" />
                        </a>
                    </td>
                </tr>
                <tr>
                    <td align="center" style="padding:0 24px 4px 24px; font-family:'Barlow',Arial,sans-serif; font-weight:500; font-size:12px; line-height:18px;">
                        <a href="{{ route('contact.vcard') }}" target="_blank" style="color:#263238; text-decoration:underline;">Nusszopf als Kontakt speichern</a>
                    </td>
                </tr>
                <tr>
                    <td align="center" style="padding:0 24px 4px 24px; font-family:'Barlow',Arial,sans-serif; font-weight:500; font-size:12px; line-height:18px;">
                        <a href="{{ route('privacy') }}" style="color:#263238; text-decoration:underline;">Datenschutzerklärung</a>
                    </td>
                </tr>
                <tr><td style="padding-bottom:24px;"></td></tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
