<?php

use App\Models\Workflow;
use App\Services\WorkflowEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Public JSON-API mit Token-Auth.
 * Header: Authorization: Bearer owe_xxxx
 */
Route::middleware('token.auth')->prefix('v1')->group(function () {
    Route::get('/me', function (Request $request) {
        $u = $request->user();
        return response()->json([
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'roles' => $u->roles->pluck('slug'),
            'token' => [
                'name' => $request->attributes->get('api_token')?->name,
                'abilities' => $request->attributes->get('api_token')?->abilities,
            ],
        ]);
    });

    // Workflows
    Route::middleware('token.ability:workflows.read')->group(function () {
        Route::get('/workflows', [\App\Http\Controllers\Api\V1\WorkflowsApiController::class, 'index']);
        Route::get('/workflow-instances', [\App\Http\Controllers\Api\V1\WorkflowsApiController::class, 'instances']);
        Route::get('/workflow-instances/{instance}', [\App\Http\Controllers\Api\V1\WorkflowsApiController::class, 'instance']);
    });
    Route::middleware('token.ability:workflows.run')->post('/workflows/{workflow}/start', function (Request $request, Workflow $workflow, WorkflowEngine $engine) {
        if ($workflow->status !== Workflow::STATUS_ACTIVE) {
            return response()->json(['message' => 'Workflow nicht aktiv.'], 422);
        }
        $data = $request->validate(['data' => ['array']]);
        $instance = $engine->start($workflow, $data['data'] ?? [], $request->user());
        return response()->json([
            'instance_id' => $instance->id,
            'status' => $instance->status,
            'current_step_key' => $instance->current_step_key,
        ], 201);
    });

    // Tasks
    Route::middleware('token.ability:tasks.read')->get('/tasks', [\App\Http\Controllers\Api\V1\TasksApiController::class, 'index']);
    Route::middleware('token.ability:tasks.write')->post('/tasks/{step}/decide', [\App\Http\Controllers\Api\V1\TasksApiController::class, 'decide']);

    // Documents
    Route::middleware('token.ability:documents.read')->group(function () {
        Route::get('/documents', [\App\Http\Controllers\Api\V1\DocumentsApiController::class, 'index']);
        Route::get('/documents/{attachment}', [\App\Http\Controllers\Api\V1\DocumentsApiController::class, 'show']);
        Route::get('/documents/{attachment}/download', [\App\Http\Controllers\Api\V1\DocumentsApiController::class, 'download']);
    });
    Route::middleware('token.ability:documents.write')->group(function () {
        Route::post('/documents', [\App\Http\Controllers\Api\V1\DocumentsApiController::class, 'upload']);
        Route::patch('/documents/{attachment}', [\App\Http\Controllers\Api\V1\DocumentsApiController::class, 'update']);
    });

    // Contracts (read/write via contracts.view / contracts.manage)
    Route::middleware('token.ability:contracts.view')->group(function () {
        Route::get('/contracts', [\App\Http\Controllers\Api\V1\ContractsApiController::class, 'index']);
        Route::get('/contracts/{contract}', [\App\Http\Controllers\Api\V1\ContractsApiController::class, 'show']);
    });
    Route::middleware('token.ability:contracts.manage')->group(function () {
        Route::post('/contracts', [\App\Http\Controllers\Api\V1\ContractsApiController::class, 'store']);
        Route::patch('/contracts/{contract}', [\App\Http\Controllers\Api\V1\ContractsApiController::class, 'update']);
        Route::post('/contracts/{contract}/attachments', [\App\Http\Controllers\Api\V1\ContractsApiController::class, 'uploadAttachment']);
    });

    // Akten (Aktendeckel) — Sichtbarkeit ueber documents.search
    Route::middleware('token.ability:documents.search')->group(function () {
        Route::get('/cases', [\App\Http\Controllers\Api\V1\CasesApiController::class, 'index']);
        Route::get('/cases/{case}', [\App\Http\Controllers\Api\V1\CasesApiController::class, 'show']);
        Route::post('/cases', [\App\Http\Controllers\Api\V1\CasesApiController::class, 'store']);
        Route::post('/cases/{case}/contracts', [\App\Http\Controllers\Api\V1\CasesApiController::class, 'attachContract']);
        Route::post('/cases/{case}/workflow-instances', [\App\Http\Controllers\Api\V1\CasesApiController::class, 'attachWorkflowInstance']);
        Route::post('/cases/{case}/notes', [\App\Http\Controllers\Api\V1\CasesApiController::class, 'addNote']);
    });

    // Reports (read-only) — fuer BI-Tools
    Route::middleware('token.ability:reports.view')
        ->get('/reports/kpis', [\App\Http\Controllers\Api\V1\ReportsApiController::class, 'kpis']);

    // Audit-Log (read-only) — fuer SIEM/Compliance
    Route::middleware('token.ability:audit.view')
        ->get('/audit-logs', [\App\Http\Controllers\Api\V1\AuditLogsApiController::class, 'index']);

    // Users (read-only)
    Route::middleware('token.ability:users.view')->group(function () {
        Route::get('/users', [\App\Http\Controllers\Api\V1\UsersApiController::class, 'index']);
        Route::get('/users/{user}', [\App\Http\Controllers\Api\V1\UsersApiController::class, 'show']);
    });

    // Lookup-Listen
    Route::middleware('token.ability:lists.view')->group(function () {
        Route::get('/lists', [\App\Http\Controllers\Api\V1\ListsApiController::class, 'index']);
        Route::get('/lists/{list:slug}', [\App\Http\Controllers\Api\V1\ListsApiController::class, 'show']);
        Route::get('/lists/{list:slug}/entries', [\App\Http\Controllers\Api\V1\ListsApiController::class, 'entries']);
    });
    Route::middleware('token.ability:lists.manage')
        ->post('/lists/{list:slug}/entries', [\App\Http\Controllers\Api\V1\ListsApiController::class, 'storeEntry']);

    // Notifications (eigene)
    Route::get('/notifications', [\App\Http\Controllers\Api\V1\NotificationsApiController::class, 'index']);
    Route::post('/notifications/{notification}/read', [\App\Http\Controllers\Api\V1\NotificationsApiController::class, 'markRead']);
    Route::post('/notifications/read-all', [\App\Http\Controllers\Api\V1\NotificationsApiController::class, 'markAllRead']);

    // Globale Suche (Permissions werden im Controller gehaertet)
    Route::get('/search', [\App\Http\Controllers\Api\V1\SearchApiController::class, 'search']);
});

// Incoming-Webhook: oeffentlicher Endpoint, Auth via Token in URL (+ optional HMAC).
Route::post('/incoming/{token}', [\App\Http\Controllers\IncomingWebhookReceiverController::class, 'receive'])
    ->name('incoming.receive');
