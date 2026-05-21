<?php

/**
 * Updater-Routen. Registriert vom Bestands-routes/web.php per require
 * am Ende des auth-Stacks. Setzt voraus, dass Auth + Permission
 * 'system.settings' den Bestand-Middleware-Aliassen entsprechen.
 */

use Illuminate\Support\Facades\Route;
use Updater\UpdateController;

// Provisorischer Prefix '/admin/system-update' — der Bestand hat schon
// einen Updater auf '/admin/update'. Sobald entschieden ist, ob der
// alte entfernt wird, kann hier auf 'admin/update' zurueckgewechselt
// werden (Block in routes.php + README anpassen).
Route::middleware(['auth', 'permission:system.settings'])
    ->prefix('admin/system-update')
    ->name('admin.system_update.')
    ->group(function () {
        Route::get('/', [UpdateController::class, 'index'])->name('index');
        Route::post('/check', [UpdateController::class, 'check'])->name('check');
        Route::post('/install', [UpdateController::class, 'install'])->name('install');
        Route::get('/progress', [UpdateController::class, 'progress'])->name('progress');
        Route::post('/channel', [UpdateController::class, 'setChannel'])->name('channel');
        Route::get('/migrations', [UpdateController::class, 'migrationStatus'])->name('migrations');
    });
