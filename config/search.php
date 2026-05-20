<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Such-Backend
    |--------------------------------------------------------------------------
    |
    | 'database'    Default. LIKE %q% ueber name/label/ocr_text.
    |               Skaliert bis ca. 50k Anhaenge. Keine externe Komponente.
    | 'meilisearch' Delegiert an einen MeiliSearch-Server. Erfordert
    |               einen laufenden meilisearch-Daemon und eine initiale
    |               Index-Befuellung via 'php artisan search:reindex'.
    |
    | Bei Fehler / Unreachable fallen wir stillschweigend auf 'database'
    | zurueck — Suche funktioniert weiter, ist halt langsamer.
    */
    'driver' => env('SEARCH_DRIVER', 'database'),

    'meilisearch' => [
        'host' => env('MEILISEARCH_HOST', 'http://127.0.0.1:7700'),
        'key' => env('MEILISEARCH_KEY', ''),
    ],

];
