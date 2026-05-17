<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserImportController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    Route::middleware('permission:users.view,users.create,users.update,users.delete')->group(function () {
        Route::get('users/import', [UserImportController::class, 'show'])->name('users.import')
            ->middleware('permission:users.import');
        Route::post('users/import', [UserImportController::class, 'store'])->name('users.import.store')
            ->middleware('permission:users.import');
        Route::resource('users', UserController::class)->except(['show']);
    });

    Route::middleware('permission:roles.view,roles.manage')->group(function () {
        Route::resource('roles', RoleController::class)->except(['show']);
    });

    Route::middleware('permission:audit.view')->group(function () {
        Route::get('audit', [AuditLogController::class, 'index'])->name('audit.index');
        Route::get('audit/verify', [AuditLogController::class, 'verify'])
            ->name('audit.verify')
            ->middleware('permission:audit.verify');
    });
});

require __DIR__.'/auth.php';
