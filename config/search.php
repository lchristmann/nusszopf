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

];
