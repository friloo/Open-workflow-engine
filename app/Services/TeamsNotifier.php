<?php

namespace App\Services;

use App\Models\WorkflowStepExecution;
use App\Support\Settings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Schickt Adaptive-Card-Benachrichtigungen an einen Microsoft-Teams-
 * Channel-Connector. Die URL kommt entweder global aus Settings
 * (Key 'integrations.teams_webhook_url') oder per Workflow-Knoten.
 *
 * Implementation ist bewusst leichtgewichtig:
 * - Kein Bot, kein OAuth, kein Approve-from-Teams.
 * - Adaptive-Card mit Titel + Antrags-Daten + Action-Button 'In OWE
 *   öffnen' → User klickt, kommt in seine Aufgabe.
 *
 * Setup: in Teams → Channel → Connectors → 'Incoming Webhook'. Die
 * generierte URL hier eintragen.
 */
class TeamsNotifier
{
    public function isConfigured(?string $overrideUrl = null): bool
    {
        return ! empty($overrideUrl ?: Settings::get('integrations.teams_webhook_url'));
    }

    public function sendTaskNotification(WorkflowStepExecution $step, ?string $overrideUrl = null): bool
    {
        $url = $overrideUrl ?: (string) Settings::get('integrations.teams_webhook_url', '');
        if ($url === '') return false;

        $instance = $step->instance;
        $node = data_get($instance->version->definition, "drawflow.Home.data.{$step->step_key}");
        $title = data_get($node, 'data.label', 'Genehmigung');
        $workflow = $instance->workflow?->name ?? 'Workflow';
        $starter = $instance->starter?->name ?? 'System';

        $card = $this->buildAdaptiveCard([
            'title' => "{$title} · {$workflow}",
            'subtitle' => "Antragsteller: {$starter}",
            'fields' => $this->factsFor($instance),
            'url' => route('tasks.show', $step),
        ]);

        try {
            $r = Http::timeout(10)->post($url, $card);
            if (! $r->successful()) {
                Log::warning('Teams-Webhook fehlgeschlagen', ['status' => $r->status(), 'body' => $r->body()]);
                return false;
            }
            return true;
        } catch (\Throwable $e) {
            Log::warning('Teams-Webhook Exception', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Liefert ein Adaptive-Card-Payload im Format das Teams erwartet
     * (MessageCard-Wrapper um Adaptive-Card). Funktioniert sowohl mit
     * dem klassischen 'Office 365 Connectors'-Endpoint als auch mit
     * dem neueren 'Workflows app'-Endpoint.
     */
    private function buildAdaptiveCard(array $data): array
    {
        $facts = [];
        foreach (($data['fields'] ?? []) as $key => $value) {
            $facts[] = ['name' => (string) $key, 'value' => (string) $value];
        }

        return [
            '@type' => 'MessageCard',
            '@context' => 'https://schema.org/extensions',
            'summary' => $data['title'],
            'themeColor' => '6366f1', // indigo-500
            'title' => $data['title'],
            'text' => $data['subtitle'] ?? '',
            'sections' => [[
                'facts' => $facts,
                'markdown' => false,
            ]],
            'potentialAction' => [[
                '@type' => 'OpenUri',
                'name' => 'In OWE öffnen',
                'targets' => [[
                    'os' => 'default',
                    'uri' => $data['url'],
                ]],
            ]],
        ];
    }

    private function factsFor($instance): array
    {
        $f = [];
        $data = (array) $instance->data;
        // Heuristisch die wichtigsten Felder zeigen
        foreach (['betrag', 'betrag_brutto', 'kostenstelle', 'rechnungsnummer', 'datum'] as $key) {
            if (! empty($data[$key])) $f[ucfirst($key)] = $data[$key];
        }
        if ($instance->started_at) {
            $f['Eingegangen'] = $instance->started_at->format('d.m.Y H:i');
        }
        return $f;
    }
}
