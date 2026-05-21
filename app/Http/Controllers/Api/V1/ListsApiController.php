<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LookupList;
use App\Models\LookupListEntry;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API: Lookup-Listen (Kostenstellen, Lieferanten, ...).
 * Token-Abilities:
 *   - lists.view  (Lesen)
 *   - lists.manage (Neue Einträge via API anlegen)
 */
class ListsApiController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => LookupList::orderBy('name')->get()->map(fn (LookupList $l) => [
                'id' => $l->id,
                'slug' => $l->slug,
                'name' => $l->name,
                'description' => $l->description,
                'columns' => $l->columns,
            ])->all(),
        ]);
    }

    public function show(LookupList $list): JsonResponse
    {
        return response()->json([
            'id' => $list->id,
            'slug' => $list->slug,
            'name' => $list->name,
            'columns' => $list->columns,
            'entries_count' => $list->entries()->count(),
        ]);
    }

    public function entries(Request $request, LookupList $list): JsonResponse
    {
        $q = LookupListEntry::query()->where('lookup_list_id', $list->id);
        if ($s = trim((string) $request->get('q', ''))) {
            $q->where('key_value', 'like', "%{$s}%");
        }
        $perPage = min(500, max(10, (int) $request->get('per_page', 100)));
        $page = $q->orderBy('key_value')->paginate($perPage);

        return response()->json([
            'data' => collect($page->items())->map(fn (LookupListEntry $e) => [
                'id' => $e->id,
                'key' => $e->key_value,
                'data' => $e->data,
            ])->all(),
            'meta' => [
                'page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function storeEntry(Request $request, LookupList $list): JsonResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:255'],
            'data' => ['nullable', 'array'],
        ]);

        $e = LookupListEntry::updateOrCreate(
            ['lookup_list_id' => $list->id, 'key_value' => $data['key']],
            ['data' => $data['data'] ?? []],
        );

        $this->audit->log('lookup.entry.upserted', $list, null,
            ['key' => $data['key'], 'list_slug' => $list->slug],
            'Eintrag '.$data['key'].' in Liste '.$list->slug.' via API gesetzt',
            $request->user()->id);

        return response()->json([
            'id' => $e->id, 'key' => $e->key_value, 'data' => $e->data,
        ], $e->wasRecentlyCreated ? 201 : 200);
    }
}
