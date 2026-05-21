<?php

use App\Http\Controllers\Admin\AIController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SystemSettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserImportController;
use App\Http\Controllers\Admin\WebhookController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\Auth\GoogleLoginController;
use App\Http\Controllers\Auth\MicrosoftLoginController;
use App\Http\Controllers\Auth\OidcLoginController;
use App\Http\Controllers\Auth\SamlLoginController;
use App\Http\Controllers\Lists\LookupListController;
use App\Http\Controllers\Lists\LookupListEntryController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Workflow\FormController;
use App\Http\Controllers\Workflow\FormSubmissionController;
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

// iCal-Feed (Token-basiert, kein Login)
Route::get('/ical/{token}.ics', [\App\Http\Controllers\IcalController::class, 'feed'])->name('ical.feed');

// Public Share-Links
// Genehmigung per Mail (signierter Link, kein Login noetig).
Route::get('/mail-approval/{step}/{user}', [\App\Http\Controllers\MailApprovalController::class, 'show'])->name('mail-approval.show');
Route::post('/mail-approval/{step}/{user}', [\App\Http\Controllers\MailApprovalController::class, 'submit'])->name('mail-approval.submit');

Route::get('/share/{token}', [\App\Http\Controllers\ShareController::class, 'show'])->name('share.show');
Route::post('/share/{token}/password', [\App\Http\Controllers\ShareController::class, 'unlock'])->name('share.unlock');
Route::get('/share/{token}/preview', [\App\Http\Controllers\ShareController::class, 'preview'])->name('share.preview');
Route::get('/share/{token}/download', [\App\Http\Controllers\ShareController::class, 'download'])->name('share.download');

// Review-Endpoints (signed, kein Login noetig)
Route::middleware('signed')->group(function () {
    Route::get('/share/{share}/review/confirm', [\App\Http\Controllers\ShareLinkController::class, 'reviewConfirmForm'])->name('shares.review.confirm');
    Route::post('/share/{share}/review/confirm', [\App\Http\Controllers\ShareLinkController::class, 'reviewConfirm'])->name('shares.review.confirm.submit');
    Route::get('/share/{share}/review/revoke', [\App\Http\Controllers\ShareLinkController::class, 'reviewRevoke'])->name('shares.review.revoke');
});

// Public form submissions (no auth)

// Public standalone forms (no auth)
Route::get('/formular/{slug}', [FormController::class, 'showPublic'])->name('forms.public.show');
Route::post('/formular/{slug}', [FormController::class, 'submitPublic'])->name('forms.public.submit');
Route::get('/formular/{slug}/danke', [FormController::class, 'thanksPublic'])->name('forms.public.thanks');

// Microsoft 365 SSO
Route::middleware('guest')->group(function () {
    Route::get('/auth/m365/redirect', [MicrosoftLoginController::class, 'redirect'])->name('auth.m365.redirect');
    Route::get('/auth/m365/callback', [MicrosoftLoginController::class, 'callback'])->name('auth.m365.callback');

    // Generic OIDC (Keycloak/Authentik/Auth0/Okta/Zitadel/...)
    Route::get('/auth/oidc/redirect', [OidcLoginController::class, 'redirect'])->name('auth.oidc.redirect');
    Route::get('/auth/oidc/callback', [OidcLoginController::class, 'callback'])->name('auth.oidc.callback');

    // Google Workspace SSO
    Route::get('/auth/google/redirect', [GoogleLoginController::class, 'redirect'])->name('auth.google.redirect');
    Route::get('/auth/google/callback', [GoogleLoginController::class, 'callback'])->name('auth.google.callback');

    // SAML 2.0 — callback ist POST vom IdP (CSRF-Ausnahme via bootstrap/app.php)
    Route::get('/auth/saml/redirect', [SamlLoginController::class, 'redirect'])->name('auth.saml.redirect');
    Route::post('/auth/saml/callback', [SamlLoginController::class, 'callback'])->name('auth.saml.callback');
    Route::get('/auth/saml/metadata', [SamlLoginController::class, 'metadata'])->name('auth.saml.metadata');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/delegation', [\App\Http\Controllers\DelegationController::class, 'update'])->name('profile.delegation.update');
    Route::delete('/profile/delegation', [\App\Http\Controllers\DelegationController::class, 'clear'])->name('profile.delegation.clear');
    Route::post('/profile/notifications', [\App\Http\Controllers\ProfileController::class, 'updateNotificationPreferences'])->name('profile.notifications.update');

    Route::post('/onboarding/dismiss', [\App\Http\Controllers\OnboardingController::class, 'dismiss'])->name('onboarding.dismiss');
    Route::post('/onboarding/complete', [\App\Http\Controllers\OnboardingController::class, 'complete'])->name('onboarding.complete');

    Route::post('/profile/ical/rotate', [\App\Http\Controllers\IcalController::class, 'rotate'])->name('profile.ical.rotate');
    Route::post('/profile/ical/revoke', [\App\Http\Controllers\IcalController::class, 'revoke'])->name('profile.ical.revoke');
    Route::post('/profile/push/subscribe', [\App\Http\Controllers\PushController::class, 'subscribe'])->name('push.subscribe');
    Route::post('/profile/push/unsubscribe', [\App\Http\Controllers\PushController::class, 'unsubscribe'])->name('push.unsubscribe');
    Route::post('/profile/push/test', [\App\Http\Controllers\PushController::class, 'test'])->name('push.test');

    // 2FA-Verwaltung pro Benutzer (opt-in)
    Route::get('/profile/two-factor', [\App\Http\Controllers\TwoFactorController::class, 'show'])->name('two-factor.show');
    Route::post('/profile/two-factor', [\App\Http\Controllers\TwoFactorController::class, 'confirm'])->name('two-factor.confirm');
    Route::delete('/profile/two-factor', [\App\Http\Controllers\TwoFactorController::class, 'disable'])->name('two-factor.disable');
    Route::post('/profile/two-factor/recovery', [\App\Http\Controllers\TwoFactorController::class, 'regenerateCodes'])->name('two-factor.recovery');

    // API-Tokens
    Route::get('/profile/tokens', [\App\Http\Controllers\ApiTokenController::class, 'index'])->name('tokens.index');
    Route::post('/profile/tokens', [\App\Http\Controllers\ApiTokenController::class, 'store'])->name('tokens.store');
    Route::delete('/profile/tokens/{token}', [\App\Http\Controllers\ApiTokenController::class, 'destroy'])->name('tokens.destroy');

    // Global Search (Cmd+K)
    Route::get('/search', [\App\Http\Controllers\GlobalSearchController::class, 'search'])->name('search.global');

    // IT-Support (eingeblendet wenn support.enabled in Settings)
    Route::get('/support', [\App\Http\Controllers\SupportController::class, 'show'])->name('support.show');
    Route::post('/support', [\App\Http\Controllers\SupportController::class, 'send'])->name('support.send');

    // Saved Searches (Filter-Presets) — user-spezifisch
    Route::post('/saved-searches', [\App\Http\Controllers\SavedSearchController::class, 'store'])->name('saved_searches.store');
    Route::delete('/saved-searches/{savedSearch}', [\App\Http\Controllers\SavedSearchController::class, 'destroy'])->name('saved_searches.destroy');

    // In-App-Notifications
    Route::get('/notifications', [\App\Http\Controllers\NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/dropdown', [\App\Http\Controllers\NotificationController::class, 'dropdown'])->name('notifications.dropdown');
    Route::get('/notifications/{notification}/read', [\App\Http\Controllers\NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [\App\Http\Controllers\NotificationController::class, 'markAllRead'])->name('notifications.read_all');
});

// 2FA-Challenge nach erfolgreichem Passwort-Login
Route::middleware('guest')->group(function () {
    Route::get('/two-factor-challenge', [\App\Http\Controllers\Auth\TwoFactorChallengeController::class, 'show'])->name('two-factor.challenge');
    Route::post('/two-factor-challenge', [\App\Http\Controllers\Auth\TwoFactorChallengeController::class, 'store']);
});

Route::middleware(['auth'])->prefix('workflows')->name('workflows.')->group(function () {
    Route::middleware('permission:workflows.view,workflows.design')->group(function () {
        Route::get('/', [WorkflowController::class, 'index'])->name('index');
        Route::get('stats', [\App\Http\Controllers\Workflow\StatsController::class, 'index'])->name('stats.index');
        Route::get('{workflow}/stats', [\App\Http\Controllers\Workflow\StatsController::class, 'show'])->name('stats.show');
        Route::get('{workflow}/simulate', [\App\Http\Controllers\Workflow\SimulationController::class, 'show'])->name('simulate.show');
        Route::post('{workflow}/simulate', [\App\Http\Controllers\Workflow\SimulationController::class, 'run'])->name('simulate.run');
        Route::get('{workflow}/process-doc.pdf', [WorkflowController::class, 'processDoc'])->name('process_doc');
        Route::get('{workflow}/versions/{version}/process-doc.pdf', [WorkflowController::class, 'processDocVersion'])->name('process_doc.version');
    });

    Route::middleware('permission:workflows.design')->group(function () {
        Route::get('templates', [\App\Http\Controllers\Workflow\TemplateController::class, 'index'])->name('templates.index');
        Route::get('templates/import', [\App\Http\Controllers\Workflow\TemplateController::class, 'importShow'])->name('templates.import.show');
        Route::post('templates/import', [\App\Http\Controllers\Workflow\TemplateController::class, 'importStore'])->name('templates.import.store');
        Route::get('{workflow}/export', [\App\Http\Controllers\Workflow\TemplateController::class, 'export'])->name('templates.export');

        Route::get('create', [WorkflowController::class, 'create'])->name('create');
        Route::post('/', [WorkflowController::class, 'store'])->name('store');
        Route::get('{workflow}/edit', [WorkflowController::class, 'edit'])->name('edit');
        Route::put('{workflow}', [WorkflowController::class, 'update'])->name('update');
        Route::delete('{workflow}', [WorkflowController::class, 'destroy'])->name('destroy');
        Route::get('{workflow}/design', [WorkflowDesignerController::class, 'show'])->name('design');
        Route::post('{workflow}/design', [WorkflowDesignerController::class, 'save'])->name('designer.save');
        Route::get('{workflow}/versions', [WorkflowDesignerController::class, 'versions'])->name('versions');
        Route::get('{workflow}/versions/diff', [WorkflowDesignerController::class, 'versionsDiff'])->name('versions.diff');
        Route::post('{workflow}/versions/{version}/restore', [WorkflowDesignerController::class, 'restore'])->name('versions.restore');
    });

    Route::middleware('permission:workflows.design')->group(function () {
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
    Route::post('/vorgaenge/{instance}/kommentar', [WorkflowInstanceController::class, 'comment'])->name('workflow-instances.comment');
    Route::post('/vorgaenge/bulk-abbrechen', [WorkflowInstanceController::class, 'bulkCancel'])->name('workflow-instances.bulk_cancel');
});

// Lookup-Listen (Kostenstellen etc.)
Route::middleware(['auth'])->prefix('listen')->name('lists.')->group(function () {
    Route::middleware('permission:lists.view,lists.manage')->group(function () {
        Route::get('/', [LookupListController::class, 'index'])->name('index');
        Route::get('{list}/edit', [LookupListController::class, 'edit'])->name('edit');
        Route::get('{list}/export', [LookupListEntryController::class, 'export'])->name('entries.export');
    });
    Route::middleware('permission:lists.manage')->group(function () {
        Route::get('create', [LookupListController::class, 'create'])->name('create');
        Route::post('/', [LookupListController::class, 'store'])->name('store');
        Route::put('{list}', [LookupListController::class, 'update'])->name('update');
        Route::delete('{list}', [LookupListController::class, 'destroy'])->name('destroy');
        Route::post('{list}/entries', [LookupListEntryController::class, 'store'])->name('entries.store');
        Route::delete('{list}/entries/{entry}', [LookupListEntryController::class, 'destroy'])->name('entries.destroy');
        Route::post('{list}/import', [LookupListEntryController::class, 'import'])->name('entries.import');
    });
});

// Assets (Fuehrerscheine, Unterweisungen, ...)
Route::middleware(['auth'])->prefix('assets')->name('assets.')->group(function () {
    Route::middleware('permission:assets.view,assets.manage')->group(function () {
        Route::get('/', [AssetController::class, 'index'])->name('index');
    });
    Route::middleware('permission:assets.manage')->group(function () {
        Route::get('create', [AssetController::class, 'create'])->name('create');
        Route::post('/', [AssetController::class, 'store'])->name('store');
        Route::get('{asset}/edit', [AssetController::class, 'edit'])->name('edit');
        Route::put('{asset}', [AssetController::class, 'update'])->name('update');
        Route::delete('{asset}', [AssetController::class, 'destroy'])->name('destroy');
        Route::post('import', [AssetController::class, 'import'])->name('import');
    });
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

// Anleitung
Route::middleware(['auth'])->group(function () {
    Route::get('/hilfe', [\App\Http\Controllers\HelpController::class, 'index'])->name('help.index');
    Route::get('/hilfe/{topic}', [\App\Http\Controllers\HelpController::class, 'show'])->name('help.show');
});

// Dokumenten-Suche (OCR-Volltext) + Versionen + Bulk-Upload
Route::middleware(['auth', 'permission:documents.search'])->prefix('dokumente')->name('documents.')->group(function () {
    Route::get('/', [\App\Http\Controllers\DocumentController::class, 'index'])->name('index');
    Route::get('postkorb', [\App\Http\Controllers\DocumentController::class, 'inbox'])->name('inbox');
    Route::post('postkorb/bulk-start', [\App\Http\Controllers\DocumentController::class, 'bulkStartWorkflow'])->name('inbox.bulk_start');
    Route::get('export.csv', [\App\Http\Controllers\DocumentController::class, 'exportCsv'])->name('export_csv');
    Route::get('upload', [\App\Http\Controllers\DocumentController::class, 'bulkUploadShow'])->name('bulk');
    Route::post('upload', [\App\Http\Controllers\DocumentController::class, 'bulkUploadStore'])->name('bulk.store');
    Route::get('{attachment}', [\App\Http\Controllers\DocumentController::class, 'show'])->name('show');
    Route::get('{attachment}/preview', [\App\Http\Controllers\DocumentController::class, 'preview'])->name('preview');
    Route::post('{attachment}/reindex', [\App\Http\Controllers\DocumentController::class, 'reindex'])->name('reindex');
    Route::post('{attachment}/fields', [\App\Http\Controllers\DocumentController::class, 'updateIndexedFields'])->name('fields.update');
    Route::post('{attachment}/start-workflow', [\App\Http\Controllers\DocumentController::class, 'startWorkflow'])->name('start_workflow');
    Route::post('{attachment}/new-version', [\App\Http\Controllers\DocumentController::class, 'uploadVersion'])->name('new_version');
    Route::post('{attachment}/annotations', [\App\Http\Controllers\PdfAnnotationController::class, 'store'])->name('annotations.store');
    Route::delete('annotations/{annotation}', [\App\Http\Controllers\PdfAnnotationController::class, 'destroy'])->name('annotations.destroy');
    Route::post('bulk-action', [\App\Http\Controllers\DocumentController::class, 'bulkAction'])->name('bulk_action');
});

// Tags + Akten — Permission documents.search reicht zum Anlegen/Editieren.
Route::middleware(['auth', 'permission:documents.search'])->group(function () {
    Route::get('/tags', [\App\Http\Controllers\TagController::class, 'index'])->name('tags.index');
    Route::post('/tags', [\App\Http\Controllers\TagController::class, 'store'])->name('tags.store');
    Route::put('/tags/{tag}', [\App\Http\Controllers\TagController::class, 'update'])->name('tags.update');
    Route::delete('/tags/{tag}', [\App\Http\Controllers\TagController::class, 'destroy'])->name('tags.destroy');

    Route::resource('akten', \App\Http\Controllers\DocumentCaseController::class)
        ->parameters(['akten' => 'case'])
        ->names('cases');
    Route::post('/akten/{case}/close', [\App\Http\Controllers\DocumentCaseController::class, 'close'])->name('cases.close');
    Route::post('/akten/{case}/workflows', [\App\Http\Controllers\DocumentCaseController::class, 'attachWorkflowInstance'])->name('cases.workflows.attach');
    Route::delete('/akten/{case}/workflows/{workflowInstanceId}', [\App\Http\Controllers\DocumentCaseController::class, 'detachWorkflowInstance'])->name('cases.workflows.detach');
    Route::post('/akten/{case}/contracts', [\App\Http\Controllers\DocumentCaseController::class, 'attachContract'])->name('cases.contracts.attach');
    Route::delete('/akten/{case}/contracts/{contractId}', [\App\Http\Controllers\DocumentCaseController::class, 'detachContract'])->name('cases.contracts.detach');
    Route::post('/akten/{case}/notes', [\App\Http\Controllers\DocumentCaseController::class, 'addNote'])->name('cases.notes.add');
    Route::delete('/akten/{case}/notes/{noteId}', [\App\Http\Controllers\DocumentCaseController::class, 'deleteNote'])->name('cases.notes.delete');
});

// Sharing-Links verwalten
Route::middleware(['auth'])->prefix('freigaben')->name('shares.')->group(function () {
    Route::get('/', [\App\Http\Controllers\ShareLinkController::class, 'index'])->name('index');
    Route::post('attachment/{attachment}', [\App\Http\Controllers\ShareLinkController::class, 'store'])->name('store');
    Route::post('{share}/revoke', [\App\Http\Controllers\ShareLinkController::class, 'revoke'])->name('revoke');
});

// Unified Inbox-Entry — leitet auf die Aufgaben-Hauptansicht weiter
Route::middleware(['auth'])->get('/inbox', fn () => redirect()->route('tasks.index'))->name('inbox');

// Reports / KPIs
Route::middleware(['auth', 'permission:reports.view'])->group(function () {
    Route::get('/reports', [\App\Http\Controllers\ReportsController::class, 'index'])->name('reports.index');
});

// Vertragsmanagement
Route::middleware(['auth', 'permission:contracts.view,contracts.manage'])->group(function () {
    Route::resource('contracts', \App\Http\Controllers\ContractController::class)
        ->middleware([
            'create' => 'permission:contracts.manage',
            'store' => 'permission:contracts.manage',
            'edit' => 'permission:contracts.manage',
            'update' => 'permission:contracts.manage',
            'destroy' => 'permission:contracts.manage',
        ]);
    // Akten-Verknuepfung vom Vertrag aus
    Route::post('contracts/{contract}/cases', [\App\Http\Controllers\ContractController::class, 'attachCase'])->name('contracts.cases.attach');
    Route::delete('contracts/{contract}/cases/{caseId}', [\App\Http\Controllers\ContractController::class, 'detachCase'])->name('contracts.cases.detach');
    // Bulk-Aktionen — nur fuer Manage-Berechtigte
    Route::middleware('permission:contracts.manage')
        ->post('contracts-bulk', [\App\Http\Controllers\ContractController::class, 'bulk'])->name('contracts.bulk');
});
// Vertragsarten-Verwaltung — nur fuer Admins/Manage-Berechtigte
Route::middleware(['auth', 'permission:contracts.manage'])->group(function () {
    Route::resource('contract-types', \App\Http\Controllers\ContractTypeController::class)
        ->parameters(['contract-types' => 'contractType'])
        ->except(['show']);

    // Vertrags-Vorlagen mit Mustache-Platzhaltern
    Route::resource('contract-templates', \App\Http\Controllers\ContractTemplateController::class)
        ->parameters(['contract-templates' => 'contractTemplate'])
        ->except(['show']);
    Route::post('contracts/{contract}/generate-from-template', [\App\Http\Controllers\ContractTemplateController::class, 'generate'])->name('contracts.template.generate');
});

// Tasks-Inbox (jeder authentifizierte aktive Benutzer)
Route::middleware(['auth'])->prefix('tasks')->name('tasks.')->group(function () {
    Route::get('/', [TaskController::class, 'index'])->name('index');
    Route::get('{step}', [TaskController::class, 'show'])->name('show');
    Route::post('{step}/decide', [TaskController::class, 'decide'])->name('decide');
    Route::post('{step}/snooze', [TaskController::class, 'snooze'])->name('snooze');
});

// Attachments (polymorph)
Route::middleware(['auth'])->prefix('attachments')->name('attachments.')->group(function () {
    Route::post('{type}/{id}', [AttachmentController::class, 'store'])->name('store');
    Route::get('{attachment}/download', [AttachmentController::class, 'download'])->name('download');
    Route::delete('{attachment}', [AttachmentController::class, 'destroy'])->name('destroy');
    Route::post('/verify-all', [AttachmentController::class, 'verifyAll'])->name('verify_all');
});

Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    Route::middleware('permission:users.view,users.create,users.update,users.delete')->group(function () {
        Route::get('users/import', [UserImportController::class, 'show'])->name('users.import')
            ->middleware('permission:users.import');
        Route::post('users/import', [UserImportController::class, 'store'])->name('users.import.store')
            ->middleware('permission:users.import');
        Route::resource('users', UserController::class)->except(['show']);

        // Berechtigungs-Report: User x Rolle x Permission
        Route::get('reports/permissions', [\App\Http\Controllers\Admin\PermissionsReportController::class, 'index'])->name('reports.permissions');
        Route::get('reports/permissions.csv', [\App\Http\Controllers\Admin\PermissionsReportController::class, 'csv'])->name('reports.permissions.csv');
        Route::get('reports/permissions.pdf', [\App\Http\Controllers\Admin\PermissionsReportController::class, 'pdf'])->name('reports.permissions.pdf');

        // Admin-Token-Verwaltung pro Benutzer (auch fuer Service-Accounts)
        Route::get('users/{user}/tokens', [\App\Http\Controllers\Admin\UserApiTokenController::class, 'index'])->name('users.tokens.index');
        Route::post('users/{user}/tokens', [\App\Http\Controllers\Admin\UserApiTokenController::class, 'store'])->name('users.tokens.store');
        Route::delete('users/{user}/tokens/{token}', [\App\Http\Controllers\Admin\UserApiTokenController::class, 'destroy'])->name('users.tokens.destroy');
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
        Route::get('settings/mail', [SystemSettingsController::class, 'mail'])->name('settings.mail');
        Route::get('settings/communication', [SystemSettingsController::class, 'communication'])->name('settings.communication');
        Route::get('settings/m365', [SystemSettingsController::class, 'm365'])->name('settings.m365');
        Route::get('settings/sso', [SystemSettingsController::class, 'sso'])->name('settings.sso');
        Route::post('settings/sso', [SystemSettingsController::class, 'updateSso'])->name('settings.sso.update');
        Route::post('settings/sso/test-ldap', [SystemSettingsController::class, 'testLdap'])->name('settings.sso.test_ldap');
        Route::get('settings/ai', [SystemSettingsController::class, 'ai'])->name('settings.ai');
        Route::get('settings/branding', [SystemSettingsController::class, 'branding'])->name('settings.branding');
        Route::get('settings/documents', [SystemSettingsController::class, 'documents'])->name('settings.documents');
        Route::get('settings/sharing', [SystemSettingsController::class, 'sharing'])->name('settings.sharing');
        Route::get('settings/support', [SystemSettingsController::class, 'support'])->name('settings.support');
        Route::post('settings/support', [SystemSettingsController::class, 'updateSupport'])->name('settings.support.update');
        Route::get('settings/integrations', [SystemSettingsController::class, 'integrations'])->name('settings.integrations');
        Route::post('settings/integrations', [SystemSettingsController::class, 'updateIntegrations'])->name('settings.integrations.update');
        Route::post('settings/integrations/test-teams', [SystemSettingsController::class, 'testTeams'])->name('settings.integrations.test_teams');
        Route::get('settings/infrastructure', [SystemSettingsController::class, 'infrastructure'])->name('settings.infrastructure');
        Route::post('settings/infrastructure', [SystemSettingsController::class, 'updateInfrastructure'])->name('settings.infrastructure.update');
        Route::post('settings/infrastructure/test', [SystemSettingsController::class, 'testInfrastructure'])->name('settings.infrastructure.test');
        Route::post('settings/mail', [SystemSettingsController::class, 'updateMail'])->name('settings.mail.update');
        Route::post('settings/mail/test', [SystemSettingsController::class, 'sendTestMail'])->name('settings.mail.test');
        Route::post('settings/m365', [SystemSettingsController::class, 'updateM365'])->name('settings.m365.update');
        Route::post('settings/m365/sync', [SystemSettingsController::class, 'syncM365'])->name('settings.m365.sync');
        Route::post('settings/m365/test', [SystemSettingsController::class, 'testM365'])->name('settings.m365.test');
        Route::post('settings/branding', [SystemSettingsController::class, 'updateBranding'])->name('settings.branding.update');
        Route::post('settings/custom-fields', [SystemSettingsController::class, 'updateCustomFields'])->name('settings.custom_fields.update');
        Route::post('settings/document-types', [SystemSettingsController::class, 'updateDocumentTypes'])->name('settings.document_types.update');
        Route::post('settings/shares', [SystemSettingsController::class, 'updateShares'])->name('settings.shares.update');
        Route::post('settings/role-document-types', [SystemSettingsController::class, 'updateRoleDocumentTypes'])->name('settings.role_document_types.update');
        Route::post('settings/retention', [SystemSettingsController::class, 'updateRetention'])->name('settings.retention.update');

        // API-Dokumentation (Swagger-UI, intern)
        Route::get('api-docs', [\App\Http\Controllers\Admin\ApiDocsController::class, 'index'])->name('api_docs.index');
        Route::get('api-docs/openapi.yaml', [\App\Http\Controllers\Admin\ApiDocsController::class, 'spec'])->name('api_docs.spec');

        Route::get('document-schemas', [\App\Http\Controllers\Admin\DocumentSchemaController::class, 'index'])->name('document_schemas.index');
        Route::get('document-schemas/{type}/edit', [\App\Http\Controllers\Admin\DocumentSchemaController::class, 'edit'])->name('document_schemas.edit');
        Route::put('document-schemas/{type}', [\App\Http\Controllers\Admin\DocumentSchemaController::class, 'update'])->name('document_schemas.update');
        Route::post('document-schemas/{type}/reindex', [\App\Http\Controllers\Admin\DocumentSchemaController::class, 'reindex'])->name('document_schemas.reindex');
        Route::post('settings/ai', [AIController::class, 'update'])->name('ai.update');
    });

    Route::middleware('permission:workflows.design,system.settings')->group(function () {
        Route::post('settings/ai/ping', [AIController::class, 'ping'])->name('ai.ping');
    });

    // KI-Vorschlaege brauchen nur workflows.design (oder darueber).
    // Rate-Limit verhindert versehentliche Kosten-Explosion.
    Route::middleware(['permission:workflows.design', 'throttle:10,1'])->group(function () {
        Route::post('ai/suggest-http', [AIController::class, 'suggestHttp'])->name('ai.suggest_http');
        Route::post('ai/suggest-workflow', [AIController::class, 'suggestWorkflow'])->name('ai.suggest_workflow');
    });

    Route::middleware('permission:secrets.manage')->group(function () {
        Route::get('secrets', [\App\Http\Controllers\Admin\SecretController::class, 'index'])->name('secrets.index');
        Route::post('secrets', [\App\Http\Controllers\Admin\SecretController::class, 'store'])->name('secrets.store');
        Route::put('secrets/{secret}', [\App\Http\Controllers\Admin\SecretController::class, 'update'])->name('secrets.update');
        Route::delete('secrets/{secret}', [\App\Http\Controllers\Admin\SecretController::class, 'destroy'])->name('secrets.destroy');
    });

    Route::middleware('permission:folder_inboxes.manage')->group(function () {
        Route::get('folder-inboxes', [\App\Http\Controllers\Admin\FolderInboxController::class, 'index'])->name('folder-inboxes.index');
        Route::get('folder-inboxes/create', [\App\Http\Controllers\Admin\FolderInboxController::class, 'create'])->name('folder-inboxes.create');
        Route::post('folder-inboxes', [\App\Http\Controllers\Admin\FolderInboxController::class, 'store'])->name('folder-inboxes.store');
        Route::get('folder-inboxes/{folderInbox}/edit', [\App\Http\Controllers\Admin\FolderInboxController::class, 'edit'])->name('folder-inboxes.edit');
        Route::put('folder-inboxes/{folderInbox}', [\App\Http\Controllers\Admin\FolderInboxController::class, 'update'])->name('folder-inboxes.update');
        Route::delete('folder-inboxes/{folderInbox}', [\App\Http\Controllers\Admin\FolderInboxController::class, 'destroy'])->name('folder-inboxes.destroy');
        Route::post('folder-inboxes/{folderInbox}/scan', [\App\Http\Controllers\Admin\FolderInboxController::class, 'scan'])->name('folder-inboxes.scan');
    });

    Route::middleware('permission:incoming_webhooks.manage')->group(function () {
        Route::get('incoming-webhooks', [\App\Http\Controllers\Admin\IncomingWebhookController::class, 'index'])->name('incoming-webhooks.index');
        Route::get('incoming-webhooks/create', [\App\Http\Controllers\Admin\IncomingWebhookController::class, 'create'])->name('incoming-webhooks.create');
        Route::post('incoming-webhooks', [\App\Http\Controllers\Admin\IncomingWebhookController::class, 'store'])->name('incoming-webhooks.store');
        Route::get('incoming-webhooks/{incomingWebhook}/edit', [\App\Http\Controllers\Admin\IncomingWebhookController::class, 'edit'])->name('incoming-webhooks.edit');
        Route::put('incoming-webhooks/{incomingWebhook}', [\App\Http\Controllers\Admin\IncomingWebhookController::class, 'update'])->name('incoming-webhooks.update');
        Route::delete('incoming-webhooks/{incomingWebhook}', [\App\Http\Controllers\Admin\IncomingWebhookController::class, 'destroy'])->name('incoming-webhooks.destroy');
        Route::post('incoming-webhooks/{incomingWebhook}/rotate', [\App\Http\Controllers\Admin\IncomingWebhookController::class, 'rotateToken'])->name('incoming-webhooks.rotate');
    });

    Route::middleware('permission:webhooks.manage')->group(function () {
        Route::get('webhooks', [WebhookController::class, 'index'])->name('webhooks.index');
        Route::get('webhooks/create', [WebhookController::class, 'create'])->name('webhooks.create');
        Route::post('webhooks', [WebhookController::class, 'store'])->name('webhooks.store');
        Route::get('webhooks/{webhook}/edit', [WebhookController::class, 'edit'])->name('webhooks.edit');
        Route::put('webhooks/{webhook}', [WebhookController::class, 'update'])->name('webhooks.update');
        Route::delete('webhooks/{webhook}', [WebhookController::class, 'destroy'])->name('webhooks.destroy');
        Route::post('webhooks/{webhook}/test', [WebhookController::class, 'test'])->name('webhooks.test');
    });

    Route::middleware('permission:system.health')->group(function () {
        Route::get('health', [\App\Http\Controllers\Admin\HealthController::class, 'index'])->name('health.index');
        Route::get('health.json', [\App\Http\Controllers\Admin\HealthController::class, 'json'])->name('health.json');
        Route::get('perf', [\App\Http\Controllers\Admin\PerfController::class, 'index'])->name('perf.index');
        Route::get('queue', [\App\Http\Controllers\Admin\QueueController::class, 'index'])->name('queue.index');
    });

    Route::middleware('permission:system.settings')->group(function () {
        Route::get('datev', [\App\Http\Controllers\Admin\DatevController::class, 'index'])->name('datev.index');
        Route::post('datev/config', [\App\Http\Controllers\Admin\DatevController::class, 'updateConfig'])->name('datev.config.update');
        Route::post('datev/export', [\App\Http\Controllers\Admin\DatevController::class, 'export'])->name('datev.export');

        Route::get('dsgvo', [\App\Http\Controllers\Admin\GdprController::class, 'index'])->name('gdpr.index');
        Route::post('dsgvo/export', [\App\Http\Controllers\Admin\GdprController::class, 'exportAccess'])->name('gdpr.export');
        Route::post('dsgvo/anonymize', [\App\Http\Controllers\Admin\GdprController::class, 'anonymize'])->name('gdpr.anonymize');
    });

    Route::middleware('permission:system.backup')->group(function () {
        Route::get('backups', [\App\Http\Controllers\Admin\BackupController::class, 'index'])->name('backups.index');
        Route::post('backups', [\App\Http\Controllers\Admin\BackupController::class, 'store'])->name('backups.store');
        Route::get('backups/{file}/download', [\App\Http\Controllers\Admin\BackupController::class, 'download'])->name('backups.download');
        Route::delete('backups/{file}', [\App\Http\Controllers\Admin\BackupController::class, 'destroy'])->name('backups.destroy');
        Route::post('backups/retention', [\App\Http\Controllers\Admin\BackupController::class, 'updateRetention'])->name('backups.retention');
    });

    Route::middleware('permission:system.update')->group(function () {
        Route::get('update', [\App\Http\Controllers\Admin\UpdateController::class, 'index'])->name('update.index');
        Route::get('update/status', [\App\Http\Controllers\Admin\UpdateController::class, 'status'])->name('update.status');
        Route::post('update/channel', [\App\Http\Controllers\Admin\UpdateController::class, 'updateChannel'])->name('update.channel');
        Route::post('update/run', [\App\Http\Controllers\Admin\UpdateController::class, 'run'])->name('update.run');
        Route::post('update/upload', [\App\Http\Controllers\Admin\UpdateController::class, 'upload'])->name('update.upload');
    });

    Route::middleware('permission:mailboxes.manage')->group(function () {
        Route::get('mailboxes', [\App\Http\Controllers\Admin\MailboxController::class, 'index'])->name('mailboxes.index');
        Route::get('mailboxes/create', [\App\Http\Controllers\Admin\MailboxController::class, 'create'])->name('mailboxes.create');
        Route::post('mailboxes', [\App\Http\Controllers\Admin\MailboxController::class, 'store'])->name('mailboxes.store');
        Route::get('mailboxes/{mailbox}/edit', [\App\Http\Controllers\Admin\MailboxController::class, 'edit'])->name('mailboxes.edit');
        Route::put('mailboxes/{mailbox}', [\App\Http\Controllers\Admin\MailboxController::class, 'update'])->name('mailboxes.update');
        Route::delete('mailboxes/{mailbox}', [\App\Http\Controllers\Admin\MailboxController::class, 'destroy'])->name('mailboxes.destroy');
        Route::post('mailboxes/{mailbox}/test', [\App\Http\Controllers\Admin\MailboxController::class, 'test'])->name('mailboxes.test');
        Route::post('mailboxes/{mailbox}/fetch', [\App\Http\Controllers\Admin\MailboxController::class, 'fetch'])->name('mailboxes.fetch');
    });
});

// Erstinstallations-Wizard (durch RedirectIfNotInstalled gated)
Route::prefix('install')->name('install.')->group(function () {
    Route::get('/', [\App\Http\Controllers\InstallerController::class, 'welcome'])->name('welcome');
    Route::get('/database', [\App\Http\Controllers\InstallerController::class, 'databaseShow'])->name('database');
    Route::post('/database', [\App\Http\Controllers\InstallerController::class, 'databaseSave']);
    Route::get('/admin', [\App\Http\Controllers\InstallerController::class, 'adminShow'])->name('admin');
    Route::post('/admin', [\App\Http\Controllers\InstallerController::class, 'adminSave']);
    Route::get('/finish', [\App\Http\Controllers\InstallerController::class, 'finishShow'])->name('finish');
    Route::get('/restore', [\App\Http\Controllers\InstallerController::class, 'restoreShow'])->name('restore');
    Route::post('/restore', [\App\Http\Controllers\InstallerController::class, 'restoreSave']);
    Route::get('/restore/done', [\App\Http\Controllers\InstallerController::class, 'restoreDone'])->name('restoreDone');
});

require __DIR__.'/auth.php';
