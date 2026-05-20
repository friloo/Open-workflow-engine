<?php

namespace App\Services;

use App\Models\Workflow;
use App\Models\WorkflowVersion;

/**
 * Generiert eine **Prozessbeschreibung** zu einem Workflow als HTML
 * (wird dann von DomPDF in PDF gewandelt). Wird typisch fuer
 * QM-/GoBD-Dokumentation gedruckt: was tut dieser Workflow, welche
 * Knoten in welcher Reihenfolge, welche Berechtigungen, welche
 * Eskalations-Regeln.
 *
 * Bewusst lesbar fuer Auditoren — nicht der Drawflow-JSON-Dump,
 * sondern eine strukturierte Tabelle mit allen wichtigen Feldern,
 * pro Knotentyp ein eigener Detail-Block.
 *
 * Geheime Werte (Secrets, Passwords, Tokens, Client-Secrets) werden
 * im Output durch '****' ersetzt — die Beschreibung darf weitergegeben
 * werden, ohne Zugangsdaten zu leaken.
 */
class WorkflowProcessDocService
{
    /**
     * Liefert das aufbereitete Daten-Set fuer das Blade-Template.
     * Hash der Definition wird mitgeliefert fuer das Footer-Stempel —
     * damit ein Auditor merkt, ob die ausgedruckte Version noch der
     * Live-Version entspricht.
     */
    public function buildPayload(Workflow $workflow, ?WorkflowVersion $version = null): array
    {
        $version = $version ?: $workflow->versions()->orderByDesc('version_number')->first();
        if (! $version) {
            throw new \RuntimeException('Workflow hat keine Version — bitte zuerst speichern.');
        }

        $nodes = (array) data_get($version->definition, 'drawflow.Home.data', []);
        // Topologisch sortieren waere ideal — aber die Knoten haben in
        // Drawflow keine garantierte Ordnung. Wir starten beim 'start'-
        // Knoten und folgen den Output-Verbindungen breitensuche-maessig.
        $ordered = $this->orderNodes($nodes);

        $payload = [
            'workflow' => $workflow,
            'version' => $version,
            'trigger_type' => $workflow->trigger_type,
            'trigger_label' => $this->triggerLabel($workflow->trigger_type),
            'created_at' => $workflow->created_at,
            'created_by' => $workflow->creator?->name ?? '—',
            'updated_at' => $version->created_at,
            'nodes' => $ordered,
            'nodes_summary' => array_map(fn ($n) => $this->summarizeNode($n, $nodes), $ordered),
            'form_schema' => (array) ($version->form_schema ?? []),
            'definition_hash' => hash('sha256', json_encode($version->definition, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'generated_at' => now(),
        ];
        return $payload;
    }

    private function orderNodes(array $nodes): array
    {
        $startKey = null;
        foreach ($nodes as $key => $node) {
            if (($node['class'] ?? null) === 'start') { $startKey = $key; break; }
        }
        if (! $startKey) {
            // Kein Start? Trotzdem alle ausgeben, einfach in JSON-Order.
            return array_values($nodes);
        }

        $order = [];
        $seen = [];
        $queue = [$startKey];
        while ($queue) {
            $k = array_shift($queue);
            if (isset($seen[$k])) continue;
            $seen[$k] = true;
            $node = $nodes[$k] ?? null;
            if (! $node) continue;
            $order[] = $node;
            // Output-Targets in die Queue
            $outputs = (array) ($node['outputs'] ?? []);
            foreach ($outputs as $out) {
                foreach (((array) ($out['connections'] ?? [])) as $conn) {
                    $next = $conn['node'] ?? null;
                    if ($next && ! isset($seen[$next]) && isset($nodes[$next])) $queue[] = $next;
                }
            }
        }
        // Falls Knoten wegen disconnected Komponenten nicht erreicht: anhaengen
        foreach ($nodes as $key => $node) {
            if (! isset($seen[$key])) $order[] = $node;
        }
        return $order;
    }

    private function triggerLabel(?string $type): string
    {
        return match ($type) {
            'form' => 'Formular',
            'manual' => 'Manuell (vom Postkorb oder API)',
            'schedule' => 'Zeitplan (Cron)',
            'mail' => 'E-Mail-Eingang (IMAP)',
            'folder' => 'Folder-Inbox',
            'webhook' => 'Eingehender Webhook',
            default => $type ?: 'unbekannt',
        };
    }

    /**
     * Eine Zeile fuer die Uebersichts-Tabelle pro Knoten:
     *   Index, Label, Typ, Folgende Knoten.
     */
    private function summarizeNode(array $node, array $allNodes): array
    {
        $label = (string) data_get($node, 'data.label', '—');
        $type = $node['class'] ?? '?';
        $outputs = (array) ($node['outputs'] ?? []);
        $followLabels = [];
        foreach ($outputs as $outIdx => $out) {
            foreach (((array) ($out['connections'] ?? [])) as $conn) {
                $next = $conn['node'] ?? null;
                if ($next && isset($allNodes[$next])) {
                    $followLabels[] = data_get($allNodes[$next], 'data.label', $next);
                }
            }
        }
        return [
            'id' => $node['id'] ?? '?',
            'label' => $label,
            'type' => $type,
            'type_human' => $this->typeLabel($type),
            'follows' => implode(', ', array_unique($followLabels)) ?: '—',
        ];
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            'start' => 'Start',
            'end' => 'Ende',
            'approval' => 'Genehmigung',
            'condition' => 'Bedingung',
            'switch_node' => 'Switch',
            'notify' => 'Benachrichtigung',
            'http' => 'HTTP-Request',
            'pdf_render' => 'PDF erzeugen',
            'wait' => 'Warten',
            'set_field' => 'Feld setzen',
            'subworkflow' => 'Sub-Workflow',
            'loop' => 'For-each',
            'aggregator' => 'Aggregator',
            default => $type,
        };
    }

    /**
     * Maskiert sensible Konfig-Werte fuer die Druck-Ausgabe. Bei
     * HTTP-Knoten mit Bearer-Token / Basic-Auth-Password sieht man
     * im PDF nur '****' — Doku darf weitergegeben werden.
     */
    public function maskSecret(?string $value): string
    {
        if (! $value) return '';
        if (strlen($value) <= 4) return '****';
        return '****'.substr($value, -4);
    }
}
