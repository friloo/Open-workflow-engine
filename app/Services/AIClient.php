<?php

namespace App\Services;

use App\Support\Settings;
use Illuminate\Support\Facades\Http;

/**
 * Schmaler OpenAI-kompatibler Client. Funktioniert mit:
 *  - OpenAI (https://api.openai.com/v1)
 *  - DeepSeek (https://api.deepseek.com/v1)
 *  - Ollama lokal (http://localhost:11434/v1, kein API-Key)
 *  - jedem anderen OpenAI-API-kompatiblen Endpunkt
 */
class AIClient
{
    public function isConfigured(): bool
    {
        return ! empty(Settings::get('ai.base_url')) && ! empty(Settings::get('ai.model'));
    }

    /**
     * Globaler Master-Schalter — wenn false, sind ALLE KI-Features in der
     * Anwendung deaktiviert (Workflow-Entwurf, HTTP-Vorschlag, NL-Suche,
     * Field-Extractor etc.). Default = true, damit bestehende Installationen
     * wie bisher funktionieren.
     */
    public function isEnabled(): bool
    {
        $flag = Settings::get('ai.enabled', true);
        return $flag === null || (bool) $flag;
    }

    /**
     * Pro-Feature-Schalter. Default-Werte unterscheiden bewusst nach
     * Datensensibilitaet:
     *  - workflow_design / http_suggest: liefern nur Templates, kein
     *    Zugriff auf Produktivdaten -> Default an.
     *  - field_extract: liest Dokument-Inhalte fuer Feld-Extraktion ->
     *    Default an (war schon vorhanden).
     *  - nl_search: liest Vertraege, Akten, Vorgaenge -> Default AUS
     *    (Opt-in), weil neues Feature mit Zugriff auf Produktivdaten.
     */
    public function isFeatureEnabled(string $feature): bool
    {
        if (! $this->isEnabled()) return false;

        $defaults = [
            'workflow_design' => true,
            'http_suggest' => true,
            'field_extract' => true,
            'nl_search' => false,
        ];
        $default = $defaults[$feature] ?? false;
        $value = Settings::get("ai.feature.{$feature}", $default);
        return $value === null ? $default : (bool) $value;
    }

    /**
     * Liefert die bekannten Feature-Keys mit Default-Wert und Beschreibung
     * fuer das Admin-UI.
     *
     * @return array<string, array{label: string, description: string, data_access: bool, default: bool}>
     */
    public static function knownFeatures(): array
    {
        return [
            'workflow_design' => [
                'label' => 'Workflow-Entwurf aus Freitext',
                'description' => 'Designer-Button „KI-Entwurf". Erzeugt nur Vorlagen — keinen Zugriff auf Produktivdaten.',
                'data_access' => false,
                'default' => true,
            ],
            'http_suggest' => [
                'label' => 'HTTP-Knoten-Vorschlag aus curl/OpenAPI',
                'description' => 'Übersetzt curl-Befehle und API-Dokus in HTTP-Knoten-Konfiguration. Keine Produktivdaten.',
                'data_access' => false,
                'default' => true,
            ],
            'field_extract' => [
                'label' => 'Dokument-Feld-Extraktor (OCR-Nachgang)',
                'description' => 'Liest OCR-Text hochgeladener Dokumente und extrahiert konfigurierte Felder per KI.',
                'data_access' => true,
                'default' => true,
            ],
            'nl_search' => [
                'label' => 'Natürlich-sprachliche Suche',
                'description' => 'Übersetzt Freitext-Anfragen in Datenbankabfragen über Verträge, Akten, Vorgänge und Dokumente. Liest direkt Produktivdaten.',
                'data_access' => true,
                'default' => false,
            ],
        ];
    }

    /** Praktischer Helper: KI ist konfiguriert UND eingeschaltet. */
    public function isReady(): bool
    {
        return $this->isEnabled() && $this->isConfigured();
    }

    public function provider(): string
    {
        return Settings::get('ai.provider', 'openai');
    }

    /**
     * Send a chat completion request.
     *
     * @param array<int, array{role:string, content:string}> $messages
     * @return array{text:string, raw:array}
     */
    public function chat(array $messages, ?float $temperature = 0.2, bool $jsonMode = false): array
    {
        $base = rtrim((string) Settings::get('ai.base_url'), '/');
        $model = (string) Settings::get('ai.model');
        $key = (string) Settings::get('ai.api_key', '');
        if (! $this->isEnabled()) {
            throw new \RuntimeException('KI ist global deaktiviert (Settings: ai.enabled).');
        }
        if (! $base || ! $model) {
            throw new \RuntimeException('KI ist nicht konfiguriert.');
        }

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $temperature,
        ];
        if ($jsonMode) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $request = Http::timeout(60)->acceptJson();
        if ($key !== '') $request = $request->withToken($key);

        $resp = $request->post($base.'/chat/completions', $payload);
        if (! $resp->successful()) {
            $err = $resp->json('error.message') ?? $resp->body();
            throw new \RuntimeException("KI-Aufruf fehlgeschlagen (HTTP {$resp->status()}): {$err}");
        }

        $text = (string) ($resp->json('choices.0.message.content') ?? '');
        return ['text' => $text, 'raw' => $resp->json() ?? []];
    }

    public function ping(): array
    {
        try {
            $r = $this->chat([
                ['role' => 'system', 'content' => 'Antworte nur mit "pong".'],
                ['role' => 'user', 'content' => 'ping'],
            ], 0, false);
            return ['ok' => true, 'message' => 'Verbindung ok. Antwort: '.\Illuminate\Support\Str::limit($r['text'], 80)];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }
}
