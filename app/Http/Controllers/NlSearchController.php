<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use App\Services\NlSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NlSearchController extends Controller
{
    public function __construct(
        private readonly NlSearchService $search,
        private readonly AuditLogger $audit,
    ) {}

    public function form(): View
    {
        return view('search.nl', [
            'available' => $this->search->isAvailable(),
        ]);
    }

    public function ask(Request $request): JsonResponse
    {
        $data = $request->validate([
            'query' => ['required', 'string', 'max:500'],
        ]);

        if (! $this->search->isAvailable()) {
            return response()->json([
                'ok' => false,
                'error' => 'KI ist nicht aktiviert oder nicht konfiguriert. Suche unter „Erweiterte Suche" weiterhin verfügbar.',
            ], 422);
        }

        $r = $this->search->search($data['query'], $request->user());

        if ($r['ok']) {
            $this->audit->log('search.nl.executed', null, null, [
                'query' => $data['query'],
                'entities' => array_keys($r['results'] ?? []),
                'counts' => array_map(fn ($v) => count($v), $r['results'] ?? []),
            ], "NL-Suche: ".\Illuminate\Support\Str::limit($data['query'], 100), $request->user()->id);
        }

        return response()->json($r, $r['ok'] ? 200 : 422);
    }
}
