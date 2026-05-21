<?php

/**
 * Updater-Routen. Registriert vom Bestands-routes/web.php per require
 * am Ende des auth-Stacks. Setzt voraus, dass Auth + Permission
 * 'system.settings' den Bestand-Middleware-Aliassen entsprechen.
 */

use Illuminate\Support\Facades\Route;
use Updater\UpdateController;

Route::middleware(['auth', 'permission:system.update'])
    ->prefix('admin/update')
    ->name('admin.update.')
    ->group(function () {
        Route::get('/', [UpdateController::class, 'index'])->name('index');
        Route::post('/check', [UpdateController::class, 'check'])->name('check');
        Route::post('/install', [UpdateController::class, 'install'])->name('install');
        Route::get('/progress', [UpdateController::class, 'progress'])->name('progress');
        Route::post('/channel', [UpdateController::class, 'setChannel'])->name('channel');
        Route::get('/migrations', [UpdateController::class, 'migrationStatus'])->name('migrations');
        Route::post('/migrations/run', [UpdateController::class, 'runMigrations'])->name('migrations.run');
        Route::post('/caches/clear', [UpdateController::class, 'clearCaches'])->name('caches.clear');
    });
