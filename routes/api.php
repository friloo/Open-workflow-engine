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

    Route::middleware('token.ability:workflows.run')->post('/workflows/{workflow}/start', function (Request $request, Workflow $workflow, WorkflowEngine $engine) {
        if ($workflow->status !== Workflow::STATUS_ACTIVE) {
            return response()->json(['message' => 'Workflow nicht aktiv.'], 422);
        }
        $data = $request->validate([
            'data' => ['array'],
        ]);
        $instance = $engine->start($workflow, $data['data'] ?? [], $request->user());
        return response()->json([
            'instance_id' => $instance->id,
            'status' => $instance->status,
            'current_step_key' => $instance->current_step_key,
        ], 201);
    });
});

// Incoming-Webhook: oeffentlicher Endpoint, Auth via Token in URL (+ optional HMAC).
Route::post('/incoming/{token}', [\App\Http\Controllers\IncomingWebhookReceiverController::class, 'receive'])
    ->name('incoming.receive');
