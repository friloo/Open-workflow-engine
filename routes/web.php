<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SystemSettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserImportController;
use App\Http\Controllers\Auth\MicrosoftLoginController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Workflow\FormController;
use App\Http\Controllers\Workflow\FormSubmissionController;
use App\Http\Controllers\Workflow\PublicFormController;
use App\Http\Controllers\Workflow\TaskController;
use App\Http\Controllers\Workflow\WorkflowController;
use App\Http\Controllers\Workflow\WorkflowDesignerController;
use App\Http\Controllers\Workflow\WorkflowInstanceController;
use App\Http\Controllers\Workflow\WorkflowScheduleController;
use App\Http\Controllers\Workflow\WorkflowStartController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

// Public form submissions (no auth)
Route::get('/f/{slug}', [PublicFormController::class, 'show'])->name('public.form.show');
Route::post('/f/{slug}', [PublicFormController::class, 'submit'])->name('public.form.submit');
Route::get('/f/{slug}/danke', [PublicFormController::class, 'thanks'])->name('public.form.thanks');

// Public standalone forms (no auth)
Route::get('/formular/{slug}', [FormController::class, 'showPublic'])->name('forms.public.show');
Route::post('/formular/{slug}', [FormController::class, 'submitPublic'])->name('forms.public.submit');
Route::get('/formular/{slug}/danke', [FormController::class, 'thanksPublic'])->name('forms.public.thanks');

// Microsoft 365 SSO
Route::middleware('guest')->group(function () {
    Route::get('/auth/m365/redirect', [MicrosoftLoginController::class, 'redirect'])->name('auth.m365.redirect');
    Route::get('/auth/m365/callback', [MicrosoftLoginController::class, 'callback'])->name('auth.m365.callback');
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

    Route::middleware('permission:workflows.run')->group(function () {
        Route::get('{workflow}/start', [WorkflowStartController::class, 'show'])->name('start');
        Route::post('{workflow}/start', [WorkflowStartController::class, 'submit'])->name('start.submit');
    });

    // Wiederkehrende Workflows
    Route::middleware('permission:workflows.design')->group(function () {
        Route::get('{workflow}/schedules', [WorkflowScheduleController::class, 'index'])->name('schedules.index');
        Route::post('{workflow}/schedules', [WorkflowScheduleController::class, 'store'])->name('schedules.store');
        Route::put('{workflow}/schedules/{schedule}', [WorkflowScheduleController::class, 'update'])->name('schedules.update');
        Route::delete('{workflow}/schedules/{schedule}', [WorkflowScheduleController::class, 'destroy'])->name('schedules.destroy');
    });

    // Pro-Workflow Instanzen-Liste
    Route::get('{workflow}/instances', [WorkflowInstanceController::class, 'indexForWorkflow'])->name('instances');
});

// Globaler Vorgangs-Browser
Route::middleware(['auth'])->group(function () {
    Route::get('/vorgaenge', [WorkflowInstanceController::class, 'indexAll'])->name('workflow-instances.index');
    Route::get('/vorgaenge/{instance}', [WorkflowInstanceController::class, 'show'])->name('workflow-instances.show');
    Route::post('/vorgaenge/{instance}/abbrechen', [WorkflowInstanceController::class, 'cancel'])->name('workflow-instances.cancel');
});

// Stand-Alone-Formulare
Route::middleware(['auth'])->prefix('forms')->name('forms.')->group(function () {
    Route::middleware('permission:forms.view,forms.manage')->group(function () {
        Route::get('/', [FormController::class, 'index'])->name('index');
    });
    Route::middleware('permission:forms.manage')->group(function () {
        Route::get('create', [FormController::class, 'create'])->name('create');
        Route::post('/', [FormController::class, 'store'])->name('store');
        Route::get('{form}/edit', [FormController::class, 'edit'])->name('edit');
        Route::put('{form}', [FormController::class, 'update'])->name('update');
        Route::delete('{form}', [FormController::class, 'destroy'])->name('destroy');
    });

    Route::middleware('permission:forms.view,forms.manage')->group(function () {
        Route::get('{form}/submissions', [FormSubmissionController::class, 'index'])->name('submissions.index');
        Route::get('{form}/submissions/export', [FormSubmissionController::class, 'export'])->name('submissions.export');
        Route::get('{form}/submissions/{submission}', [FormSubmissionController::class, 'show'])->name('submissions.show');
    });
});

// Tasks-Inbox (jeder authentifizierte aktive Benutzer)
Route::middleware(['auth'])->prefix('tasks')->name('tasks.')->group(function () {
    Route::get('/', [TaskController::class, 'index'])->name('index');
    Route::get('{step}', [TaskController::class, 'show'])->name('show');
    Route::post('{step}/decide', [TaskController::class, 'decide'])->name('decide');
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

    Route::middleware('permission:system.settings')->group(function () {
        Route::get('settings', [SystemSettingsController::class, 'index'])->name('settings.index');
        Route::post('settings/mail', [SystemSettingsController::class, 'updateMail'])->name('settings.mail.update');
        Route::post('settings/mail/test', [SystemSettingsController::class, 'sendTestMail'])->name('settings.mail.test');
        Route::post('settings/m365', [SystemSettingsController::class, 'updateM365'])->name('settings.m365.update');
        Route::post('settings/m365/sync', [SystemSettingsController::class, 'syncM365'])->name('settings.m365.sync');
        Route::post('settings/m365/test', [SystemSettingsController::class, 'testM365'])->name('settings.m365.test');
    });
});

require __DIR__.'/auth.php';
