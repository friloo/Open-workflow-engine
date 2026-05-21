<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\Contract;
use App\Models\DocumentCase;
use App\Models\User;
use App\Models\Workflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API: Globale Volltext-Suche ueber Dokumente, Vertraege, Akten,
 * Workflows und Benutzer. Liefert pro Bereich max 10 Treffer mit
 * minimalem Datensatz + URL. Permission-gehaertet:
 *  - Dokumente brauchen documents.search
 *  - Vertraege werden ueber visibleTo gefiltert (contracts.view)
 *  - Akten brauchen documents.search
 *  - Workflows brauchen workflows.view oder workflows.design
 *  - Benutzer brauchen users.view
 */
class SearchApiController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:128'],
            'limit' => ['nullable', 'integer', 'between:1,50'],
        ]);
        $q = (string) $request->get('q');
        $limit = (int) $request->get('limit', 10);
        $user = $request->user();
        $appUrl = rtrim((string) config('app.url'), '/');

        $out = ['query' => $q, 'documents' => [], 'contracts' => [], 'cases' => [], 'workflows' => [], 'users' => []];

        if ($user->hasPermission('documents.search')) {
            $out['documents'] = Attachment::query()
                ->where('is_current_version', true)
                ->where(fn ($w) => $w->where('original_name', 'like', "%{$q}%")
                    ->orWhere('ocr_text', 'like', "%{$q}%"))
                ->orderByDesc('id')->limit($limit)
                ->get(['id', 'original_name', 'mime_type', 'document_type'])
                ->map(fn ($a) => [
                    'id' => $a->id, 'name' => $a->original_name, 'mime' => $a->mime_type,
                    'document_type' => $a->document_type,
                    'url' => "{$appUrl}/dokumente/{$a->id}",
                ])->all();

            $out['cases'] = DocumentCase::query()
                ->where(fn ($w) => $w->where('name', 'like', "%{$q}%")
                    ->orWhere('reference', 'like', "%{$q}%"))
                ->orderBy('name')->limit($limit)
                ->get(['id', 'name', 'reference'])
                ->map(fn ($c) => [
                    'id' => $c->id, 'name' => $c->name, 'reference' => $c->reference,
                    'url' => "{$appUrl}/akten/{$c->id}",
                ])->all();
        }

        if ($user->hasAnyPermission(['contracts.view', 'contracts.manage'])) {
            $out['contracts'] = Contract::query()->visibleTo($user)
                ->where(fn ($w) => $w->where('name', 'like', "%{$q}%")
                    ->orWhere('party', 'like', "%{$q}%"))
                ->orderBy('name')->limit($limit)
                ->get(['id', 'name', 'party', 'status'])
                ->map(fn ($c) => [
                    'id' => $c->id, 'name' => $c->name, 'party' => $c->party, 'status' => $c->status,
                    'url' => "{$appUrl}/contracts/{$c->id}",
                ])->all();
        }

        if ($user->hasAnyPermission(['workflows.view', 'workflows.design'])) {
            $out['workflows'] = Workflow::query()
                ->where('name', 'like', "%{$q}%")
                ->orderBy('name')->limit($limit)
                ->get(['id', 'name', 'slug', 'status'])
                ->map(fn ($w) => [
                    'id' => $w->id, 'name' => $w->name, 'status' => $w->status,
                    'url' => "{$appUrl}/workflows/{$w->id}/design",
                ])->all();
        }

        if ($user->hasPermission('users.view')) {
            $out['users'] = User::query()->humans()->where('is_active', true)
                ->where(fn ($w) => $w->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%"))
                ->orderBy('name')->limit($limit)
                ->get(['id', 'name', 'email'])
                ->map(fn ($u) => [
                    'id' => $u->id, 'name' => $u->name, 'email' => $u->email,
                ])->all();
        }

        return response()->json($out);
    }
}
