<?php

namespace App\Services\Search;

use App\Models\Attachment;
use Illuminate\Support\Facades\Http;

/**
 * Such-Service mit zwei Backends:
 *
 * - 'database' (Default): klassisches LIKE %q% ueber original_name, label
 *   und ocr_text. Skaliert bis ~50k Dokumente passabel, danach lahm.
 * - 'meilisearch': delegiert an einen externen MeiliSearch-Server. Liefert
 *   IDs der Treffer, die wir dann in der Eloquent-Query als whereIn
 *   reinklemmen — so bleiben alle anderen Filter (Typ, Status, Indexfelder,
 *   Permission-Scope) erhalten.
 *
 * Wenn MeiliSearch konfiguriert ist aber unreachable, fallen wir
 * stillschweigend auf 'database' zurueck — die Seite funktioniert weiter,
 * nur langsamer.
 */
class DocumentSearch
{
    public function driver(): string
    {
        $d = (string) config('search.driver', 'database');
        return in_array($d, ['database', 'meilisearch'], true) ? $d : 'database';
    }

    /**
     * Wendet die Volltext-Suche auf die gegebene Eloquent-Query an. Andere
     * Filter (Typ, Status, etc.) sind bereits angewendet — wir muessen also
     * nur 'q' einarbeiten.
     */
    public function applyFulltext(\Illuminate\Database\Eloquent\Builder $query, string $q): void
    {
        if ($q === '') return;

        if ($this->driver() === 'meilisearch') {
            try {
                $ids = $this->meilisearchIds($q);
                $query->whereIn('id', $ids ?: [0]);
                return;
            } catch (\Throwable) {
                // Fallback auf database (logge nichts -> User wartet eh schon).
            }
        }

        $query->where(function ($qq) use ($q) {
            $qq->where('original_name', 'like', "%{$q}%")
               ->orWhere('label', 'like', "%{$q}%")
               ->orWhere('ocr_text', 'like', "%{$q}%");
        });
    }

    /**
     * Indexiert / aktualisiert einen einzelnen Anhang in MeiliSearch.
     * No-op wenn driver != meilisearch.
     */
    public function index(Attachment $att): void
    {
        if ($this->driver() !== 'meilisearch') return;
        $body = [[
            'id' => $att->id,
            'original_name' => $att->original_name,
            'label' => $att->label,
            'ocr_text' => $att->ocr_text,
            'document_type' => $att->document_type,
            'created_at' => $att->created_at?->timestamp,
        ]];
        try {
            $this->req('POST', '/indexes/documents/documents', $body);
        } catch (\Throwable) {}
    }

    public function delete(int $attachmentId): void
    {
        if ($this->driver() !== 'meilisearch') return;
        try {
            $this->req('DELETE', "/indexes/documents/documents/{$attachmentId}");
        } catch (\Throwable) {}
    }

    /**
     * Re-Index aller Anhaenge. Wird vom 'search:reindex'-Command benutzt.
     */
    public function reindexAll(int $batch = 200, ?\Closure $onProgress = null): int
    {
        if ($this->driver() !== 'meilisearch') return 0;
        $this->ensureIndex();
        $count = 0;
        Attachment::query()->chunkById($batch, function ($attachments) use (&$count, $onProgress) {
            $body = $attachments->map(fn ($a) => [
                'id' => $a->id,
                'original_name' => $a->original_name,
                'label' => $a->label,
                'ocr_text' => $a->ocr_text,
                'document_type' => $a->document_type,
                'created_at' => $a->created_at?->timestamp,
            ])->all();
            $this->req('POST', '/indexes/documents/documents', $body);
            $count += count($body);
            if ($onProgress) $onProgress($count);
        });
        return $count;
    }

    public function health(): array
    {
        if ($this->driver() !== 'meilisearch') {
            return ['ok' => true, 'driver' => 'database', 'message' => 'database fulltext (LIKE)'];
        }
        try {
            $r = Http::timeout(3)->get(rtrim(config('search.meilisearch.host'), '/').'/health');
            return ['ok' => $r->successful(), 'driver' => 'meilisearch', 'status' => $r->json('status') ?? 'unknown'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'driver' => 'meilisearch', 'error' => $e->getMessage()];
        }
    }

    private function meilisearchIds(string $q, int $limit = 1000): array
    {
        $r = $this->req('POST', '/indexes/documents/search', [
            'q' => $q,
            'limit' => $limit,
            'attributesToRetrieve' => ['id'],
        ]);
        $hits = (array) ($r['hits'] ?? []);
        return array_values(array_filter(array_map(fn ($h) => (int) ($h['id'] ?? 0), $hits)));
    }

    private function ensureIndex(): void
    {
        try {
            $this->req('POST', '/indexes', ['uid' => 'documents', 'primaryKey' => 'id']);
        } catch (\Throwable) {
            // Existiert vermutlich schon
        }
        // Searchable + filterable Attribute setzen
        try {
            $this->req('PATCH', '/indexes/documents/settings', [
                'searchableAttributes' => ['original_name', 'label', 'ocr_text'],
                'filterableAttributes' => ['document_type'],
                'sortableAttributes' => ['created_at'],
            ]);
        } catch (\Throwable) {}
    }

    /** Wirft RuntimeException bei Non-2xx. */
    private function req(string $method, string $path, ?array $body = null): array
    {
        $url = rtrim(config('search.meilisearch.host'), '/').$path;
        $req = Http::timeout(10)->withHeaders(array_filter([
            'Authorization' => config('search.meilisearch.key') ? 'Bearer '.config('search.meilisearch.key') : null,
            'Content-Type' => 'application/json',
        ]));
        $r = match ($method) {
            'GET' => $req->get($url),
            'POST' => $req->post($url, $body ?? []),
            'PATCH' => $req->patch($url, $body ?? []),
            'DELETE' => $req->delete($url),
        };
        if (! $r->successful()) {
            throw new \RuntimeException("MeiliSearch {$method} {$path}: HTTP {$r->status()}");
        }
        return (array) ($r->json() ?? []);
    }
}
