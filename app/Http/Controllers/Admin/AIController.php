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

    public function ping(AIClient $client, Request $request)
    {
        $r = $client->ping();
        if ($request->wantsJson() || $request->expectsJson()) {
            return response()->json(['ok' => $r['ok'], 'message' => $r['message']], $r['ok'] ? 200 : 502);
        }
        if (! $r['ok']) return back()->withErrors(['ai' => $r['message']]);
        return back()->with('status', $r['message']);
    }

    /**
     * Generiert aus einer Freitext-API-Beschreibung eine HTTP-Knoten-Config.
     */
    public function suggestHttp(AIClient $client, Request $request): JsonResponse
    {
        $data = $request->validate([
            // 'description' bleibt aus Rückwärts-Kompat; 'input' ist neu für
            // einen freien Mix aus curl / OpenAPI / Markdown-Doku / Freitext.
            'description' => ['nullable', 'string', 'max:20000'],
            'input' => ['nullable', 'string', 'max:20000'],
            'available_fields' => ['nullable', 'array'],
            'purpose' => ['nullable', 'string', 'max:500'],
        ]);
        $input = trim((string) ($data['input'] ?? $data['description'] ?? ''));
        if ($input === '') {
            return response()->json(['error' => 'Bitte Beschreibung, curl-Befehl oder API-Doku angeben.'], 422);
        }
        if (! $client->isConfigured()) {
            return response()->json(['error' => 'KI ist nicht konfiguriert.'], 422);
        }

        // 1) Wenn der Input wie ein curl-Befehl aussieht, vorab strukturell
        //    parsen. Das gibt der KI eine sichere Basis und spart Token.
        $preparsed = null;
        if (preg_match('/^\s*curl\b/i', $input) || str_contains($input, "\ncurl ")) {
            try {
                $preparsed = \App\Services\CurlParser::parse($input);
            } catch (\Throwable) {
                $preparsed = null;
            }
        }

        $fields = $data['available_fields'] ?? [];
        $fieldList = $fields ? "Verfügbare Platzhalter: ".implode(', ', $fields) : '';
        $purpose = (string) ($data['purpose'] ?? '');
        $purposeLine = $purpose !== '' ? "Aktueller Zweck des Calls: {$purpose}" : '';

        $system = <<<TXT
Du bist Experte für API-Integrationen. Aus dem gegebenen Input (kann sein:
curl-Befehl, OpenAPI/Swagger-Snippet, Markdown-Doku, oder freie Beschreibung)
erzeugst du ein JSON-Objekt für einen HTTP-Knoten.

Antworte AUSSCHLIESSLICH mit reinem JSON, ohne Markdown-Wrapper, in diesem
Format:

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
  "response_mapping": [{"path": "", "save_as": ""}],
  "notes": "kurze Hinweise an den Admin (max 200 Zeichen)"
}

Regeln:
- KONKRETE BEISPIELWERTE im Body durch passende Platzhalter ersetzen.
  z. B. "max@example.com" -> {{ user_email }}, "Max Mustermann" -> {{ user_name }},
  "Drucker druckt nicht" -> {{ subject }}, etc.
- Nur die unter "Verfügbare Platzhalter" gelisteten Variablen verwenden.
- Authorization-Header NICHT in "headers" auflisten, stattdessen via
  auth_type/auth_token/auth_username/...
- response_mapping mit Punktnotation, z. B. "data.id" -> "ticket_id".
- Wenn mehrere Endpoints im Input stehen, wähle den passendsten zum
  "Aktuellen Zweck" oder den ersten POST/PUT.
- IDs oder Zahlen, für die kein Platzhalter passt, auf 0 oder einen
  sinnvollen Default lassen und in "notes" erwaehnen.

{$purposeLine}
{$fieldList}
TXT;

        // Pre-Parse-Hilfe an die KI weiterreichen
        $userMsg = $input;
        if ($preparsed) {
            $userMsg = "Ich habe diesen curl-Befehl bereits strukturell geparst:\n"
                .json_encode($preparsed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                ."\n\nOriginal-curl:\n".$input
                ."\n\nNimm diese Struktur, ersetze Beispielwerte im Body durch passende Platzhalter und liefere das verlangte JSON.";
        }

        try {
            $r = $client->chat([
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $userMsg],
            ], 0.1, true);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }

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

        // Wenn KI auth_token leer lässt, Pre-Parse-Auth übernehmen (Token aus curl).
        if ($preparsed && ! empty($preparsed['auth'])) {
            $a = $preparsed['auth'];
            if (empty($parsed['auth_type']) || $parsed['auth_type'] === 'none') $parsed['auth_type'] = $a['type'] ?? 'none';
            if (empty($parsed['auth_token']) && ! empty($a['token'])) $parsed['auth_token'] = $a['token'];
            if (empty($parsed['auth_username']) && ! empty($a['username'])) $parsed['auth_username'] = $a['username'];
            if (empty($parsed['auth_password']) && ! empty($a['password'])) $parsed['auth_password'] = $a['password'];
            if (empty($parsed['auth_header_name']) && ! empty($a['header_name'])) $parsed['auth_header_name'] = $a['header_name'];
        }

        $this->audit->log('ai.suggest_http', null, null, [
            'provider' => $client->provider(),
            'method' => $parsed['method'] ?? null,
            'url' => $parsed['url'] ?? null,
            'preparsed' => $preparsed !== null,
        ], 'KI-Vorschlag für HTTP-Call generiert', $request->user()->id);

        return response()->json(['suggestion' => $parsed]);
    }

    /**
     * Generiert aus einer Workflow-Beschreibung einen kompletten Entwurf
     * (Form-Schema + Knoten-Graph). Wird vom Designer per Klick aufgerufen.
     */
    public function suggestWorkflow(AIClient $client, Request $request): JsonResponse
    {
        $data = $request->validate([
            'description' => ['required', 'string', 'max:6000'],
            'trigger_type' => ['nullable', 'in:form,manual,recurring'],
        ]);
        if (! $client->isConfigured()) {
            return response()->json(['error' => 'KI ist nicht konfiguriert.'], 422);
        }

        $trigger = $data['trigger_type'] ?? 'form';

        $system = <<<TXT
Du baust den ersten Entwurf eines Workflows für die Open Workflow Engine.
Antworte AUSSCHLIESSLICH mit reinem JSON (kein Markdown-Codeblock).

JSON-Schema:

{
  "form_schema": [
    { "key": "snake_case_key", "label": "...", "type": "text|textarea|number|date|select|radio|checkbox|file", "required": true|false, "options": ["..."] }
  ],
  "nodes": [
    { "id": "n1", "type": "start", "label": "Start", "data": {} },
    { "id": "n2", "type": "approval", "label": "Genehmigung", "data": {
        "recipient_type": "supervisor_of_initiator|supervisor_of_previous|subject_user|supervisor_of_subject|role|user|list_lookup",
        "grace_value": 3, "grace_unit": "hours|days|months",
        "escalation_type": "none|supervisor_of_current|role|list_lookup",
        "allow_forward": false
    }},
    { "id": "n3", "type": "condition", "label": "...", "data": {
        "branches": [ { "label": "...", "field": "form_key", "operator": "eq|neq|contains|gt|gte|lt|lte|checked|unchecked|empty|not_empty", "value": "..." } ]
    }},
    { "id": "n4", "type": "notify", "label": "...", "data": {
        "recipient_type": "initiator|subject_user|supervisor_of_initiator|supervisor_of_subject|role|user|list_lookup",
        "subject": "...", "body": "Hallo {{ initiator_name }}, ..."
    }},
    { "id": "n5", "type": "http", "label": "...", "data": {
        "method": "POST", "url": "https://...",
        "auth_type": "none|bearer|basic|api_key_header",
        "headers": [{"key":"X","value":"Y"}],
        "body_type": "json", "body_template": "{...mit Mustache-Platzhaltern...}",
        "response_mapping": [{"path":"id","save_as":"ticket_id"}],
        "timeout_seconds": 30, "continue_on_error": false
    }},
    { "id": "n6", "type": "end", "label": "Ende", "data": {"result": "completed|rejected|cancelled"} }
  ],
  "edges": [
    { "from": "n1", "from_output": 1, "to": "n2", "to_input": 1 },
    { "from": "n2", "from_output": 1, "to": "n3", "to_input": 1 },
    { "from": "n2", "from_output": 2, "to": "n6", "to_input": 1 }
  ]
}

Regeln:
- Genau ein Knoten "start" und mindestens ein "end".
- Approval-Ausgänge: 1=Genehmigt, 2=Abgelehnt, 3=Weitergeleitet (falls allow_forward=true).
- Condition: pro branches[i] ein Ausgang i (1-basiert), zusaetzlich letzter Ausgang = Else.
- HTTP: 1=OK, 2=Fehler. Notify und Start haben Ausgang 1. End hat keine.
- Platzhalter im Body/Subject: {{ initiator_name }}, {{ initiator_email }}, {{ subject_user_email }}, beliebige Formularfeld-Keys, {{ initiator_custom.<key> }}.
- Trigger-Typ ist: {$trigger}. Wenn "form": baue passendes form_schema. Wenn "recurring": form_schema darf leer sein.
- Wenn der Nutzer Kostenstellen/Listen-Lookups erwaehnt, setze recipient_type="list_lookup" und benenne lookup_source als den Form-Feld-Key (Liste muss später manuell zugeordnet werden, list_id leer lassen).
- Keine Erläuterungen, nur valide JSON-Antwort.
TXT;

        try {
            $r = $client->chat([
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $data['description']],
            ], 0.2, true);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }

        $text = trim($r['text']);
        if (str_starts_with($text, '```')) {
            $text = trim(preg_replace('/^```(json)?|```$/m', '', $text));
        }
        $parsed = json_decode($text, true);
        if (! is_array($parsed) || ! isset($parsed['nodes'])) {
            return response()->json([
                'error' => 'KI-Antwort konnte nicht als Workflow gelesen werden.',
                'raw' => $r['text'],
            ], 422);
        }

        $this->audit->log('ai.suggest_workflow', null, null, [
            'provider' => $client->provider(),
            'nodes' => count($parsed['nodes'] ?? []),
            'edges' => count($parsed['edges'] ?? []),
            'fields' => count($parsed['form_schema'] ?? []),
        ], 'KI-Vorschlag für kompletten Workflow generiert', $request->user()->id);

        return response()->json(['draft' => $parsed]);
    }
}
