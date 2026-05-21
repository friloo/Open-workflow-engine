<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IncomingWebhook;
use App\Models\Workflow;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IncomingWebhookController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): View
    {
        return view('admin.incoming-webhooks.index', [
            'webhooks' => IncomingWebhook::with('workflow', 'creator')->orderBy('name')->paginate(25),
        ]);
    }

    public function create(): View
    {
        return view('admin.incoming-webhooks.edit', [
            'webhook' => new IncomingWebhook(['is_active' => true, 'field_mappings' => []]),
            'workflows' => Workflow::orderBy('name')->get(['id', 'name', 'status']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateRequest($request);
        $secret = $data['secret'] ?? null;
        unset($data['secret']);

        $webhook = new IncomingWebhook($data);
        $webhook->is_active = $request->boolean('is_active', true);
        $webhook->created_by = $request->user()->id;
        if ($secret) $webhook->secret = $secret;
        $webhook->save();

        $this->audit->log('incoming_webhook.created', $webhook, null, [
            'name' => $webhook->name, 'workflow_id' => $webhook->workflow_id, 'has_secret' => $secret !== null,
        ], "Incoming-Webhook angelegt: {$webhook->name}");

        return redirect()->route('admin.incoming-webhooks.edit', $webhook)->with('status', 'Webhook gespeichert.');
    }

    public function edit(IncomingWebhook $incomingWebhook): View
    {
        return view('admin.incoming-webhooks.edit', [
            'webhook' => $incomingWebhook,
            'workflows' => Workflow::orderBy('name')->get(['id', 'name', 'status']),
            'showToken' => true,
        ]);
    }

    public function update(Request $request, IncomingWebhook $incomingWebhook): RedirectResponse
    {
        $data = $this->validateRequest($request, $incomingWebhook);
        $secret = $data['secret'] ?? null;
        unset($data['secret']);

        $original = $incomingWebhook->only(['name', 'workflow_id', 'is_active']);
        $incomingWebhook->fill($data);
        $incomingWebhook->is_active = $request->boolean('is_active', false);
        if ($secret) $incomingWebhook->secret = $secret;
        $incomingWebhook->save();

        $this->audit->log('incoming_webhook.updated', $incomingWebhook, $original, $incomingWebhook->only(array_keys($original)),
            "Incoming-Webhook {$incomingWebhook->name} aktualisiert");

        return back()->with('status', 'Webhook gespeichert.');
    }

    public function destroy(IncomingWebhook $incomingWebhook): RedirectResponse
    {
        $name = $incomingWebhook->name;
        $incomingWebhook->delete();
        $this->audit->log('incoming_webhook.deleted', null, ['name' => $name], null,
            "Incoming-Webhook {$name} gelöscht");
        return redirect()->route('admin.incoming-webhooks.index')->with('status', 'Webhook gelöscht.');
    }

    public function rotateToken(IncomingWebhook $incomingWebhook): RedirectResponse
    {
        $incomingWebhook->token = IncomingWebhook::makeToken();
        $incomingWebhook->save();
        $this->audit->log('incoming_webhook.token_rotated', $incomingWebhook, null, null,
            "Token rotiert: {$incomingWebhook->name}");
        return back()->with('status', 'Neuer Token erzeugt.');
    }

    private function validateRequest(Request $request, ?IncomingWebhook $existing = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'workflow_id' => ['required', 'integer', 'exists:workflows,id'],
            'secret' => ['nullable', 'string', 'max:255'],
            'field_mappings' => ['nullable', 'array'],
            'field_mappings.*.path' => ['required_with:field_mappings.*.field', 'string', 'max:255'],
            'field_mappings.*.field' => ['required_with:field_mappings.*.path', 'string', 'max:128'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }
}
