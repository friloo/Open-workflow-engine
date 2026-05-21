<?php

namespace App\Http\Controllers;

use App\Models\IncomingWebhook;
use App\Models\Workflow;
use App\Services\AuditLogger;
use App\Services\WorkflowEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class IncomingWebhookReceiverController extends Controller
{
    public function __construct(
        private readonly WorkflowEngine $engine,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Loest einen Workflow per HTTP-POST aus.
     * Header (optional): X-OWE-Signature: sha256=<hex>
     */
    public function receive(Request $request, string $token): JsonResponse
    {
        $webhook = IncomingWebhook::where('token', $token)->where('is_active', true)->first();
        if (! $webhook) {
            return response()->json(['message' => 'Token ungültig oder inaktiv.'], 404);
        }

        // HMAC-Prüfung wenn Secret konfiguriert.
        if ($secret = $webhook->secret) {
            $sigHeader = (string) $request->header('X-OWE-Signature', '');
            $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);
            if (! hash_equals($expected, $sigHeader)) {
                $this->bumpFailure($webhook, 'Bad signature');
                return response()->json(['message' => 'Signatur ungültig.'], 401);
            }
        }

        $workflow = $webhook->workflow;
        if (! $workflow || $workflow->status !== Workflow::STATUS_ACTIVE) {
            $this->bumpFailure($webhook, 'Workflow inaktiv');
            return response()->json(['message' => 'Workflow nicht aktiv.'], 422);
        }

        $json = (array) $request->json()->all();

        // Field-Mapping anwenden: pfad (data.foo.bar) -> field-key
        $form = [];
        foreach ((array) ($webhook->field_mappings ?? []) as $m) {
            $path = (string) ($m['path'] ?? '');
            $field = (string) ($m['field'] ?? '');
            if ($path === '' || $field === '') continue;
            $value = data_get($json, $path);
            if ($value !== null) $form[$field] = is_scalar($value) ? $value : json_encode($value);
        }
        // Zusatz: vollständigen Payload in `webhook_payload` ablegen
        $form['webhook_payload'] = $json;

        try {
            $instance = $this->engine->start($workflow, $form, null);
        } catch (\Throwable $e) {
            $this->bumpFailure($webhook, $e->getMessage());
            Log::error('Incoming webhook -> workflow start failed', [
                'webhook' => $webhook->id, 'error' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'Workflow-Start fehlgeschlagen.'], 500);
        }

        $webhook->forceFill([
            'last_called_at' => now(),
            'call_count' => $webhook->call_count + 1,
            'last_error' => null,
        ])->save();

        $this->audit->log('incoming_webhook.received', $webhook, null, [
            'instance_id' => $instance->id, 'workflow' => $workflow->name, 'mapped' => array_keys($form),
        ], "Incoming-Webhook {$webhook->name} -> Instanz #{$instance->id}");

        return response()->json([
            'instance_id' => $instance->id,
            'status' => $instance->status,
            'current_step_key' => $instance->current_step_key,
        ], 201);
    }

    private function bumpFailure(IncomingWebhook $webhook, string $reason): void
    {
        $webhook->forceFill([
            'last_called_at' => now(),
            'failure_count' => $webhook->failure_count + 1,
            'last_error' => substr($reason, 0, 1000),
        ])->save();
    }
}
