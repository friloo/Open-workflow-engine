<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AIClient;
use App\Services\AuditLogger;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AIController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'provider' => ['required', 'in:openai,deepseek,ollama,custom'],
            'base_url' => ['required', 'url', 'max:512'],
            'model' => ['required', 'string', 'max:128'],
            'api_key' => ['nullable', 'string', 'max:1024'],
        ]);
        foreach (['provider', 'base_url', 'model'] as $k) {
            Settings::set("ai.{$k}", $data[$k], $request->user()->id);
        }
        if (! empty($data['api_key'])) {
            Settings::set('ai.api_key', $data['api_key'], $request->user()->id);
        }
        $this->audit->log('settings.ai.updated', null, null, [
            'provider' => $data['provider'], 'model' => $data['model'],
        ], 'KI-Konfiguration aktualisiert', $request->user()->id);

        return back()->with('status', 'KI-Konfiguration gespeichert.');
    }

    public function ping(AIClient $client, Request $request): RedirectResponse
    {
        $r = $client->ping();
        if (! $r['ok']) return back()->withErrors(['ai' => $r['message']]);
        return back()->with('status', $r['message']);
    }

    /**
     * Generiert aus einer Freitext-API-Beschreibung eine HTTP-Knoten-Config.
     */
    public function suggestHttp(AIClient $client, Request $request): JsonResponse
    {
        $data = $request->validate([
            'description' => ['required', 'string', 'max:6000'],
            'available_fields' => ['nullable', 'array'],
        ]);
        if (! $client->isConfigured()) {
            return response()->json(['error' => 'KI ist nicht konfiguriert.'], 422);
        }

        $fields = $data['available_fields'] ?? [];
        $fieldList = $fields ? "Verfuegbare Platzhalter (Formular- und Workflow-Felder): ".implode(', ', $fields) : '';

        $system = <<<TXT
Du bist Experte fuer API-Integrationen. Aus der gegebenen API-Beschreibung erzeugst du
strikt eine JSON-Antwort fuer einen HTTP-Workflow-Knoten. Antworte ausschliesslich mit
einem JSON-Objekt mit diesen Feldern (alle optional, leer lassen wenn nicht relevant):

{
  "method": "GET|POST|PUT|PATCH|DELETE",
  "url": "https://...",
  "auth_type": "none|bearer|basic|api_key_header",
  "auth_token": "",
  "auth_username": "",
  "auth_password": "",
  "auth_header_name": "",
  "headers": [{"key": "", "value": ""}],
  "body_type": "none|json|form|raw",
  "body_template": "",
  "body_form": [{"key": "", "value": ""}],
  "response_mapping": [{"path": "", "save_as": ""}]
}

Regeln:
- Dynamische Werte als Mustache-Platzhalter formulieren, z. B. {{ initiator_email }},
  {{ subject_user_name }}, {{ kostenstelle }}, {{ instance_id }}, {{ workflow_name }}.
- response_mapping nutzt Punktnotation, z. B. "data.id" -> "ticket_id".
- Wenn der API-Body als JSON erwartet wird, body_type = json + body_template als
  gueltiges JSON mit Platzhaltern.
- Wenn die API ein API-Key-Header verlangt, auth_type = api_key_header und
  auth_header_name korrekt setzen (z. B. "X-API-Key" oder "Authorization").
- Antworte AUSSCHLIESSLICH mit reinem JSON, ohne Markdown-Wrapper.
{$fieldList}
TXT;

        try {
            $r = $client->chat([
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $data['description']],
            ], 0.1, true);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }

        // JSON aus Antwort herausfischen (manche Modelle umgeben mit ```json)
        $text = trim($r['text']);
        if (str_starts_with($text, '```')) {
            $text = trim(preg_replace('/^```(json)?|```$/m', '', $text));
        }
        $parsed = json_decode($text, true);
        if (! is_array($parsed)) {
            return response()->json([
                'error' => 'KI-Antwort konnte nicht als JSON gelesen werden.',
                'raw' => $r['text'],
            ], 422);
        }

        $this->audit->log('ai.suggest_http', null, null, [
            'provider' => $client->provider(),
            'method' => $parsed['method'] ?? null,
            'url' => $parsed['url'] ?? null,
        ], 'KI-Vorschlag fuer HTTP-Knoten generiert', $request->user()->id);

        return response()->json(['suggestion' => $parsed]);
    }
}
