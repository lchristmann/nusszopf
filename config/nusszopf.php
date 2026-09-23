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

];
