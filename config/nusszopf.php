<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Version
    |--------------------------------------------------------------------------
    |
    | The release the running image was built from. The release workflow bakes
    | the Git tag in as a build argument (NUSSZOPF_VERSION); a working copy or a
    | locally built image reports "dev". Shown by `php artisan nusszopf:health`,
    | `php artisan about`, and — with the health token — by /health.
    |
    */

    'version' => env('NUSSZOPF_VERSION', 'dev'),

    /*
    |--------------------------------------------------------------------------
    | Health details
    |--------------------------------------------------------------------------
    |
    | /health always answers 200 "ok" or 503 "degraded" without details. With
    | HEALTH_TOKEN set, a request carrying it (`Authorization: Bearer …`) also
    | gets each check's result and the version. Unset, the details are only
    | available through `php artisan nusszopf:health`.
    |
    */

    'health_token' => env('HEALTH_TOKEN'),

    /*
    | How long a scheduler or queue-worker heartbeat may be old before the
    | check fails. The scheduler writes one every minute.
    */
    'heartbeat_max_age' => 180,

    /*
    |--------------------------------------------------------------------------
    | Newsletter consent version
    |--------------------------------------------------------------------------
    |
    | Stored with every newsletter subscription as part of its consent record
    | (decision A-1): which version of the privacy text the person agreed to.
    | The operator owns that text (decision A-4), so the operator names the
    | version — change it whenever the Datenschutz text changes.
    |
    */

    'newsletter_consent_version' => env('NEWSLETTER_CONSENT_VERSION', '1'),

    /*
    |--------------------------------------------------------------------------
    | Operator contact address
    |--------------------------------------------------------------------------
    |
    | The mailbox shown wherever the app says "write to us" — the error page,
    | Home, the newsletter and Profile pages, the e-mails, the "report project"
    | link and the downloadable contact card. Historically the literal
    | mail@nusszopf.org; it belongs to whoever runs this instance (decision
    | A-5), so it is configuration and falls back to MAIL_FROM_ADDRESS.
    |
    | Not to be confused with `Project::NUSSZOPF_CONTACT`, the stored marker
    | for "first contact runs through Nusszopf", which never changes.
    |
    */

    'contact_email' => env('NUSSZOPF_CONTACT_EMAIL') ?: env('MAIL_FROM_ADDRESS', 'hello@example.com'),

    /*
    |--------------------------------------------------------------------------
    | Legal pages
    |--------------------------------------------------------------------------
    |
    | The folder holding this instance's own Impressum, Rechtliches and
    | Datenschutz texts as Markdown: legal-notice.md, legal-policy.md and
    | privacy.md (decision A-4 — the project ships no legal text). A missing
    | file shows a "not configured" notice on its page. The operator stack
    | mounts ./legal next to docker-compose.yaml here.
    |
    */

    'legal_path' => env('NUSSZOPF_LEGAL_PATH') ?: base_path('legal'),

];
