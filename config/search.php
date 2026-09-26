<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Search page size
    |--------------------------------------------------------------------------
    |
    | How many index documents ("Mehr laden" adds another page of them). 50 is
    | the historical value (`OFFSET` in search.service.js) and what a real
    | installation runs with; the variable exists so the browser tests can reach
    | the end of the results without creating dozens of projects.
    |
    */

    'page_size' => (int) env('SEARCH_PAGE_SIZE', 50),

    /*
    |--------------------------------------------------------------------------
    | Waiting for the index settings
    |--------------------------------------------------------------------------
    |
    | Meilisearch applies settings asynchronously. `search:reindex` checks them
    | every 0.25 s, this many times (30 s), before it reports them missing.
    |
    */

    'settings_wait_attempts' => 120,

    /*
    |--------------------------------------------------------------------------
    | Waiting for the queue worker
    |--------------------------------------------------------------------------
    |
    | With the queue in use, the queue worker writes the documents of a
    | `search:reindex`. The command checks the queue every 0.25 s, this many
    | times (30 s), and warns when jobs are still waiting after that.
    |
    */

    'queue_wait_attempts' => 120,

];
