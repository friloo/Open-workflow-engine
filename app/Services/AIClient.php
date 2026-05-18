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
