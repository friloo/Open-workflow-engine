<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application, which will be used when the
    | framework needs to place the application's name in a notification or
    | other UI elements where an application name needs to be displayed.
    |
    */

    'name' => env('APP_NAME', 'Laravel'),

    /*
    |--------------------------------------------------------------------------
    | Performance-Audit
    |--------------------------------------------------------------------------
    |
    | Konfiguriert die PerformanceAudit-Middleware. Wenn eine Route die
    | Threshold-Werte ueberschreitet, wandert ein Eintrag in den 'perf'-Log.
    */
    'perf_audit' => [
        'threshold_ms' => (int) env('PERF_THRESHOLD_MS', 500),
        'threshold_queries' => (int) env('PERF_THRESHOLD_QUERIES', 40),
        'send_header' => (bool) env('APP_PERF_HEADER', false),
    ],

    // Wenn true: OCR + Feld-Indexierung laufen als Queue-Job statt
    // synchron im Upload-Request. Sinnvoll bei vielen / grossen
    // Uploads — Uploads sind sofort fertig, OCR passiert im Hintergrund.
    // Voraussetzung: laufender 'php artisan queue:work' und QUEUE_CONNECTION
    // != 'sync' (database / redis).
    'queue_ocr' => (bool) env('QUEUE_OCR', false),

    // LibreOffice-Office-Vorschau. Schaltet sich automatisch ein, wenn
    // das libreoffice-Binary auf dem Server gefunden wird; per LIBREOFFICE_PREVIEW=false
    // explizit aus. LIBREOFFICE_BIN ueberschreibt die Auto-Suche im PATH.
    'libreoffice_preview' => (bool) env('LIBREOFFICE_PREVIEW', true),
    'libreoffice_bin' => env('LIBREOFFICE_BIN'),


    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | the application so that it's available within Artisan commands.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. The timezone
    | is set to "UTC" by default as it is suitable for most use cases.
    |
    */

    'timezone' => env('APP_TIMEZONE', 'UTC'),

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by Laravel's translation / localization methods. This option can be
    | set to any locale for which you plan to have translation strings.
    |
    */

    'locale' => env('APP_LOCALE', 'en'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is utilized by Laravel's encryption services and should be set
    | to a random, 32 character string to ensure that all encrypted values
    | are secure. You should do this prior to deploying the application.
    |
    */

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | These configuration options determine the driver used to determine and
    | manage Laravel's "maintenance mode" status. The "cache" driver will
    | allow maintenance mode to be controlled across multiple machines.
    |
    | Supported drivers: "file", "cache"
    |
    */

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

];
