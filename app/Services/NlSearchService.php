<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\Contract;
use App\Models\DocumentCase;
use App\Models\User;
use App\Models\WorkflowInstance;
use Illuminate\Support\Carbon;

/**
 * Übersetzt eine deutsche Freitext-Frage in einen strukturierten Filter
 * (JSON), führt ihn als Eloquent-Query aus und liefert konsolidierte
 * Treffer über mehrere Entitäten (Verträge / Akten / Vorgänge / Dokumente).
 *
 * Beispiel-Frage:
 *   "Zeig mir alle Verträge die im Juli auslaufen und Wert > 10k"
 * → KI generiert:
 *   { "entities": ["contracts"],
 *     "contracts": { "end_date_between": ["2026-07-01","2026-07-31"], "value_gt": 10000 } }
 *
 * Die Engine darf nichts ausführen, was nicht im erlaubten Filter-Schema
 * steht — d.h. keine Injection über die KI-Antwort. Whitelisting greift
 * vor der Query.
 */
class NlSearchService
{
    public function __construct(private readonly AIClient $ai) {}

    public function isAvailable(): bool
    {
        return $this->ai->isReady() && $this->ai->isFeatureEnabled('nl_search');
    }

    /**
     * @return array{
     *   ok: bool,
     *   query: string,
     *   filter?: array,
     *   results?: array,
     *   error?: string,
     * }
     */
    public function search(string $query, User $user): array
    {
        $query = trim($query);
        if ($query === '') {
            return ['ok' => false, 'query' => $query, 'error' => 'Leere Anfrage.'];
        }

        if (! $this->ai->isReady()) {
            return ['ok' => false, 'query' => $query, 'error' => 'KI ist nicht aktiviert oder nicht konfiguriert.'];
        }

        try {
            $filter = $this->askAi($query);
        } catch (\Throwable $e) {
            return ['ok' => false, 'query' => $query, 'error' => 'KI-Übersetzung fehlgeschlagen: '.$e->getMessage()];
        }

        $results = $this->execute($filter, $user);
        return ['ok' => true, 'query' => $query, 'filter' => $filter, 'results' => $results];
    }

    // ─── KI-Übersetzung ──────────────────────────────────────────────────

    private function askAi(string $query): array
    {
        $today = now()->toDateString();
        $system = <<<TXT
Du übersetzt deutsche Freitext-Suchfragen in einen strukturierten
JSON-Filter für die Anwendung. Antworte AUSSCHLIESSLICH mit reinem JSON,
ohne Markdown-Wrapper.

JSON-Format:

{
  "entities": ["contracts" | "cases" | "instances" | "documents"],
  "contracts": {
    "search":     "Volltext-Suchbegriff (in name, party, description)",
    "status":     "active | notice_due | expired",
    "owner_self": true|false,
    "end_date_between": ["YYYY-MM-DD","YYYY-MM-DD"],
    "value_gt":   123.45,
    "value_lt":   123.45
  },
  "cases": {
    "search":  "Volltext (in name, description)",
    "closed":  true|false
  },
  "instances": {
    "search":  "Volltext (in Workflow-Name)",
    "status":  "running | completed | failed | cancelled",
    "started_self": true|false,
    "started_between": ["YYYY-MM-DD","YYYY-MM-DD"]
  },
  "documents": {
    "search":         "Volltext (original_name, ocr_text)",
    "document_type":  "...",
    "created_between": ["YYYY-MM-DD","YYYY-MM-DD"]
  }
}

Regeln:
- Heutiges Datum: {$today}. Relative Zeitangaben absolut umrechnen.
  "diesen Monat" -> Anfang/Ende des aktuellen Monats.
  "im Juli" -> Juli des aktuellen Jahres.
  "in 30 Tagen" -> [heute, heute+30].
  "letztes Quartal" -> exakte 3-Monats-Spanne.
- "meine Verträge" / "auf mich" -> owner_self: true bzw. started_self: true.
- "abgelaufen" -> status: expired; "aktiv" -> active; "kuendigungsfrist erreicht" -> notice_due.
- Bei Geldbeträgen: "10k", "10000", "10.000" alle 10000.
- Wenn die Frage nichts spezifiziert, nimm die wahrscheinlichste Entität
  und lasse die Felder leer/weg.
- "entities" muss alle Entitäten enthalten, die der Filter füllt.
- KEINE Felder erfinden — nur das oben definierte Schema verwenden.
TXT;

        $resp = $this->ai->chat([
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $query],
        ], 0.1, true);

        $text = trim($resp['text']);
        if (str_starts_with($text, '```')) {
            $text = trim(preg_replace('/^```(json)?|```$/m', '', $text));
        }
        $parsed = json_decode($text, true);
        if (! is_array($parsed) || empty($parsed['entities'])) {
            throw new \RuntimeException('Filter konnte nicht extrahiert werden.');
        }
        return $parsed;
    }

    // ─── Filter ausführen ────────────────────────────────────────────────

    private function execute(array $filter, User $user): array
    {
        $entities = array_intersect(
            (array) ($filter['entities'] ?? []),
            ['contracts', 'cases', 'instances', 'documents'],
        );

        $out = [];
        foreach ($entities as $e) {
            $f = (array) ($filter[$e] ?? []);
            $out[$e] = match ($e) {
                'contracts' => $this->queryContracts($f, $user),
                'cases' => $this->queryCases($f, $user),
                'instances' => $this->queryInstances($f, $user),
                'documents' => $this->queryDocuments($f, $user),
            };
        }
        return $out;
    }

    private function queryContracts(array $f, User $user): array
    {
        $q = Contract::query()->visibleTo($user);

        if (! empty($f['search'])) {
            $term = '%'.trim($f['search']).'%';
            $q->where(function ($qq) use ($term) {
                $qq->where('name', 'like', $term)
                   ->orWhere('party', 'like', $term)
                   ->orWhere('description', 'like', $term);
            });
        }
        if (! empty($f['status']) && in_array($f['status'], ['active', 'notice_due', 'expired'], true)) {
            $q->where('status', $f['status']);
        }
        if (! empty($f['owner_self'])) {
            $q->where('owner_user_id', $user->id);
        }
        if (! empty($f['end_date_between']) && is_array($f['end_date_between']) && count($f['end_date_between']) === 2) {
            [$a, $b] = $this->parseRange($f['end_date_between']);
            if ($a && $b) $q->whereBetween('end_date', [$a, $b]);
        }
        if (isset($f['value_gt']) && is_numeric($f['value_gt'])) {
            $q->where('value', '>', (float) $f['value_gt']);
        }
        if (isset($f['value_lt']) && is_numeric($f['value_lt'])) {
            $q->where('value', '<', (float) $f['value_lt']);
        }

        return $q->orderBy('end_date')->limit(50)->get(['id', 'name', 'party', 'status', 'end_date', 'value', 'owner_user_id'])
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'subtitle' => $c->party,
                'meta' => ($c->end_date ? 'bis '.$c->end_date->format('d.m.Y') : '').' · '.$c->status,
                'url' => route('contracts.show', $c),
            ])->all();
    }

    private function queryCases(array $f, User $user): array
    {
        $q = DocumentCase::query();
        if (! empty($f['search'])) {
            $term = '%'.$f['search'].'%';
            $q->where(function ($qq) use ($term) {
                $qq->where('name', 'like', $term)->orWhere('description', 'like', $term);
            });
        }
        if (array_key_exists('closed', $f)) {
            if ($f['closed']) $q->whereNotNull('closed_at');
            else $q->whereNull('closed_at');
        }
        return $q->orderByDesc('updated_at')->limit(50)->get(['id', 'name', 'closed_at'])
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'subtitle' => $c->closed_at ? 'geschlossen' : 'offen',
                'meta' => null,
                'url' => route('cases.show', $c),
            ])->all();
    }

    private function queryInstances(array $f, User $user): array
    {
        $q = WorkflowInstance::query()->with('workflow:id,name');
        if (! empty($f['search'])) {
            $term = '%'.$f['search'].'%';
            $q->whereHas('workflow', fn ($w) => $w->where('name', 'like', $term));
        }
        if (! empty($f['status']) && in_array($f['status'], ['running', 'completed', 'failed', 'cancelled'], true)) {
            $q->where('status', $f['status']);
        }
        if (! empty($f['started_self'])) {
            $q->where('started_by', $user->id);
        }
        if (! empty($f['started_between']) && is_array($f['started_between']) && count($f['started_between']) === 2) {
            [$a, $b] = $this->parseRange($f['started_between']);
            if ($a && $b) $q->whereBetween('started_at', [$a, $b]);
        }
        return $q->orderByDesc('started_at')->limit(50)->get(['id', 'workflow_id', 'status', 'started_at'])
            ->map(fn ($i) => [
                'id' => $i->id,
                'name' => '#'.$i->id.' · '.($i->workflow?->name ?? '—'),
                'subtitle' => $i->status,
                'meta' => $i->started_at?->format('d.m.Y H:i'),
                'url' => route('workflow-instances.show', $i),
            ])->all();
    }

    private function queryDocuments(array $f, User $user): array
    {
        $q = Attachment::query()->where('is_current_version', true);
        if (! empty($f['search'])) {
            $term = '%'.$f['search'].'%';
            $q->where(function ($qq) use ($term) {
                $qq->where('original_name', 'like', $term)
                   ->orWhere('ocr_text', 'like', $term);
            });
        }
        if (! empty($f['document_type'])) {
            $q->where('document_type', $f['document_type']);
        }
        if (! empty($f['created_between']) && is_array($f['created_between']) && count($f['created_between']) === 2) {
            [$a, $b] = $this->parseRange($f['created_between']);
            if ($a && $b) $q->whereBetween('created_at', [$a, $b]);
        }
        // Sichtbarkeits-Filter manuell anwenden
        $rows = $q->orderByDesc('created_at')->limit(80)->get();
        $visible = $rows->filter(fn ($r) => $r->visibleTo($user))->take(50);

        return $visible->map(fn ($a) => [
            'id' => $a->id,
            'name' => $a->original_name,
            'subtitle' => $a->document_type ?: 'Unklassifiziert',
            'meta' => $a->created_at?->format('d.m.Y H:i'),
            'url' => route('documents.show', $a),
        ])->values()->all();
    }

    private function parseRange(array $r): array
    {
        try {
            $from = Carbon::parse($r[0])->startOfDay();
            $to = Carbon::parse($r[1])->endOfDay();
            return [$from, $to];
        } catch (\Throwable) {
            return [null, null];
        }
    }
}
