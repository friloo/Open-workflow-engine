<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Optionaler KI-Extraktor. Schickt OCR-Text + Feld-Schema an die
 * konfigurierte OpenAI-kompatible KI und erwartet ein JSON-Objekt
 * mit den gewuenschten Schluesseln zurueck.
 *
 * Wird nur aufgerufen, wenn die KI konfiguriert ist UND ein Feld
 * explizit `extractor=ki` oder `ki_fallback=true` hat.
 */
class AIFieldExtractor
{
    public function __construct(private readonly AIClient $ai) {}

    public function isAvailable(): bool
    {
        return $this->ai->isConfigured();
    }

    /**
     * @param array<int, array{key:string, label:string, type:string}> $fields
     * @return array<string, ?string>
     */
    public function extract(array $fields, string $context): array
    {
        if (! $fields) return [];

        $schemaForPrompt = collect($fields)->map(fn ($f) => [
            'key' => $f['key'], 'label' => $f['label'], 'type' => $f['type'],
        ])->values()->all();

        // Begrenzen, damit wir nicht ins Token-Limit laufen.
        $ctx = mb_substr($context, 0, 12000);

        $system = 'Du extrahierst strukturierte Werte aus deutschen Geschaeftsdokumenten. '
            .'Liefere AUSSCHLIESSLICH ein JSON-Objekt mit den vorgegebenen Schluesseln. '
            .'Datumsfelder im Format YYYY-MM-DD. Betraege als Dezimalzahl mit Punkt ("1234.56"). '
            .'Wenn ein Feld nicht eindeutig erkennbar ist, setze den Wert auf null. '
            .'Keine Erklaerungen, kein Markdown, nur JSON.';

        $user = "Schema (JSON):\n".json_encode($schemaForPrompt, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            ."\n\nDokumenttext:\n".$ctx;

        try {
            $resp = $this->ai->chat(
                [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $user],
                ],
                0.1, true,
            );
        } catch (\Throwable $e) {
            Log::warning('AIFieldExtractor: Aufruf fehlgeschlagen', ['error' => $e->getMessage()]);
            return [];
        }

        $json = trim($resp['text'] ?? '');
        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            Log::info('AIFieldExtractor: konnte JSON nicht parsen', ['text' => mb_substr($json, 0, 300)]);
            return [];
        }

        $out = [];
        foreach ($fields as $f) {
            $val = $decoded[$f['key']] ?? null;
            if (is_array($val) || is_object($val)) continue;
            if ($val === null) continue;
            $out[$f['key']] = (string) $val;
        }
        return $out;
    }
}
