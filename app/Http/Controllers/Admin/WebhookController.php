<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Webhook;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WebhookController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): View
    {
        return view('admin.webhooks.index', [
            'webhooks' => Webhook::with('creator')->orderBy('name')->paginate(25),
            'allEvents' => Webhook::ALL_EVENTS,
        ]);
    }

    public function create(): View
    {
        return view('admin.webhooks.edit', [
            'webhook' => new Webhook(['is_active' => true, 'events' => [], 'headers' => []]),
            'allEvents' => Webhook::ALL_EVENTS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateWebhook($request);
        $webhook = Webhook::create([
            ...$data,
            'is_active' => $request->boolean('is_active', true),
            'created_by' => $request->user()->id,
        ]);
        $this->audit->log('webhook.created', $webhook, null, [
            'name' => $webhook->name, 'url' => $webhook->url, 'events' => $webhook->events,
        ], "Webhook {$webhook->name} angelegt");
        return redirect()->route('admin.webhooks.index')->with('status', 'Webhook gespeichert.');
    }

    public function edit(Webhook $webhook): View
    {
        return view('admin.webhooks.edit', [
            'webhook' => $webhook,
            'allEvents' => Webhook::ALL_EVENTS,
        ]);
    }

    public function update(Request $request, Webhook $webhook): RedirectResponse
    {
        $data = $this->validateWebhook($request, $webhook);
        // Leeres Secret = unveraendert lassen
        if (empty($data['secret'])) unset($data['secret']);
        $original = $webhook->only(array_keys($data));
        $webhook->update([
            ...$data,
            'is_active' => $request->boolean('is_active', false),
        ]);
        $this->audit->log('webhook.updated', $webhook, $original, $webhook->only(array_keys($data)),
            "Webhook {$webhook->name} aktualisiert");
        return back()->with('status', 'Webhook gespeichert.');
    }

    public function destroy(Webhook $webhook): RedirectResponse
    {
        $snapshot = $webhook->only(['id', 'name', 'url']);
        $webhook->delete();
        $this->audit->log('webhook.deleted', null, $snapshot, null, "Webhook {$snapshot['name']} geloescht");
        return redirect()->route('admin.webhooks.index')->with('status', 'Webhook geloescht.');
    }

    public function test(Webhook $webhook, Request $request): RedirectResponse
    {
        $payload = [
            'event' => 'test',
            'timestamp' => now()->toIso8601String(),
            'message' => 'Test-Aufruf aus OWE',
        ];
        $body = json_encode($payload);
        $headers = array_merge(['Content-Type' => 'application/json'], $webhook->headers ?? []);
        if ($webhook->secret) {
            $headers['X-OWE-Signature'] = 'sha256='.hash_hmac('sha256', $body, $webhook->secret);
        }
        try {
            $resp = Http::withHeaders($headers)->timeout(10)->withBody($body, 'application/json')->post($webhook->url);
            $this->audit->log('webhook.tested', $webhook, null, ['status' => $resp->status()],
                "Webhook-Test {$webhook->name} -> HTTP {$resp->status()}", $request->user()->id);
            return back()->with('status', "Test-Webhook -> HTTP {$resp->status()}.");
        } catch (\Throwable $e) {
            return back()->withErrors(['webhook' => 'Test fehlgeschlagen: '.$e->getMessage()]);
        }
    }

    private function validateWebhook(Request $request, ?Webhook $webhook = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:512'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['required', Rule::in(Webhook::ALL_EVENTS)],
            'headers' => ['nullable', 'array'],
            'headers.*.key' => ['nullable', 'string', 'max:128'],
            'headers.*.value' => ['nullable', 'string', 'max:2000'],
            'secret' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }
}
