@props(['href'])

{{--
    The historical MJML pill button (`mj-button`, e.g. `change-password.mjml`,
    `blocked-account.mjml`) — reproduced as an inline-styled anchor, the same
    "hand-authored table/inline-style HTML" decision as the mail layout itself
    (docs/rewrite/sixth-slice.md, decision 1). Colors match the app's own
    `steel` outline button (`nz-btn-steel`, resources/css/app.css): border and
    text `#37474F` (steel-700), fill `#ECEFF1` (steel-100).
--}}
<table role="presentation" cellpadding="0" cellspacing="0" style="margin:8px 0 0 0;">
    <tr>
        <td style="border-radius:9999px; border:2px solid #37474F; background-color:#ECEFF1;">
            <a href="{{ $href }}" style="display:inline-block; padding:10px 28px; font-family:'Barlow',Arial,sans-serif; font-weight:700; font-size:16px; color:#37474F; text-decoration:none;">{{ $slot }}</a>
        </td>
    </tr>
</table>
