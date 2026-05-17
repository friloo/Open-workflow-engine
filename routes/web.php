<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserImportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Workflow\WorkflowController;
use App\Http\Controllers\Workflow\WorkflowDesignerController;
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

Route::middleware(['auth'])->prefix('workflows')->name('workflows.')->group(function () {
    Route::middleware('permission:workflows.view,workflows.design')->group(function () {
        Route::get('/', [WorkflowController::class, 'index'])->name('index');
    });

    Route::middleware('permission:workflows.design')->group(function () {
        Route::get('create', [WorkflowController::class, 'create'])->name('create');
        Route::post('/', [WorkflowController::class, 'store'])->name('store');
        Route::get('{workflow}/edit', [WorkflowController::class, 'edit'])->name('edit');
        Route::put('{workflow}', [WorkflowController::class, 'update'])->name('update');
        Route::delete('{workflow}', [WorkflowController::class, 'destroy'])->name('destroy');
        Route::get('{workflow}/design', [WorkflowDesignerController::class, 'show'])->name('design');
        Route::post('{workflow}/design', [WorkflowDesignerController::class, 'save'])->name('designer.save');
        Route::get('{workflow}/versions', [WorkflowDesignerController::class, 'versions'])->name('versions');
        Route::post('{workflow}/versions/{version}/restore', [WorkflowDesignerController::class, 'restore'])->name('versions.restore');
    });

    Route::middleware('permission:workflows.publish')->group(function () {
        Route::post('{workflow}/activate', [WorkflowController::class, 'activate'])->name('activate');
        Route::post('{workflow}/archive', [WorkflowController::class, 'archive'])->name('archive');
    });
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
