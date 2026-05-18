<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// OWE-Update-Maintenance: solange .maintenance existiert, antworten wir
// mit 503 ohne Laravel zu booten — der Code wird vom Updater gerade
// ausgetauscht.
if (file_exists(__DIR__.'/../.maintenance')) {
    http_response_code(503);
    header('Retry-After: 60');
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><title>Update laeuft</title>'.
         '<style>body{font-family:system-ui,sans-serif;display:grid;place-items:center;min-height:100vh;background:#f8fafc;color:#0f172a}</style>'.
         '<div><h1>System-Update laeuft</h1><p>Bitte gleich noch einmal versuchen.</p></div>';
    exit;
}

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
(require_once __DIR__.'/../bootstrap/app.php')
    ->handleRequest(Request::capture());
