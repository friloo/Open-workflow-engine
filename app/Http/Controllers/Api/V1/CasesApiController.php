<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\DocumentCase;
use App\Models\WorkflowInstance;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API: Akten (Aktendeckel). Token-Abilities:
 *  - documents.search   (Lesen)
 *  - documents.search + workflows.design oder documents.search + contracts.manage (Schreiben/Anhängen)
 */
class CasesApiController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): JsonResponse
    {
        $q = DocumentCase::query()->withCount(['attachments', 'workflowInstances', 'contracts'])
            ->orderBy('name');
        if ($s = trim((string) $request->get('q', ''))) {
            $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")
                ->orWhere('reference', 'like', "%{$s}%"));
        }
        if ($request->boolean('open_only')) $q->whereNull('closed_at');

        return response()->json(['data' => $q->limit(200)->get()->map(fn ($c) => self::serialize($c))]);
    }

    public function show(DocumentCase $case): JsonResponse
    {
        $case->load(['attachments', 'workflowInstances.workflow', 'contracts.type', 'notes.user']);
        return response()->json(self::serialize($case, full: true));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:128'],
            'description' => ['nullable', 'string'],
        ]);
        $case = DocumentCase::create([...$data, 'created_by' => $request->user()->id]);
        $this->audit->log('case.created', $case, null, $data,
            'Akte '.$case->name.' via API angelegt', $request->user()->id);
        return response()->json(self::serialize($case, full: true), 201);
    }

    public function attachContract(Request $request, DocumentCase $case): JsonResponse
    {
        $data = $request->validate(['contract_id' => ['required', 'exists:contracts,id']]);
        $case->contracts()->syncWithoutDetaching([$data['contract_id']]);
        $this->audit->log('case.contract_attached', $case, null, $data,
            'Vertrag '.$data['contract_id'].' an Akte '.$case->name.' geheftet', $request->user()->id);
        return response()->json(['ok' => true], 201);
    }

    public function attachWorkflowInstance(Request $request, DocumentCase $case): JsonResponse
    {
        $data = $request->validate(['workflow_instance_id' => ['required', 'exists:workflow_instances,id']]);
        $case->workflowInstances()->syncWithoutDetaching([$data['workflow_instance_id']]);
        $this->audit->log('case.workflow_attached', $case, null, $data,
            'Vorgang '.$data['workflow_instance_id'].' an Akte '.$case->name.' geheftet', $request->user()->id);
        return response()->json(['ok' => true], 201);
    }

    public function addNote(Request $request, DocumentCase $case): JsonResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:65535']]);
        $note = $case->notes()->create(['user_id' => $request->user()->id, 'body' => $data['body']]);
        return response()->json([
            'id' => $note->id, 'body' => $note->body,
            'user_id' => $note->user_id, 'created_at' => $note->created_at?->toIso8601String(),
        ], 201);
    }

    public static function serialize(DocumentCase $c, bool $full = false): array
    {
        $base = [
            'id' => $c->id,
            'name' => $c->name,
            'reference' => $c->reference,
            'closed_at' => $c->closed_at?->toIso8601String(),
            'attachments_count' => $c->attachments_count ?? $c->attachments()->count(),
            'workflow_instances_count' => $c->workflow_instances_count ?? $c->workflowInstances()->count(),
            'contracts_count' => $c->contracts_count ?? $c->contracts()->count(),
        ];
        if ($full) {
            $base['description'] = $c->description;
            $base['attachments'] = $c->attachments->map(fn ($a) => [
                'id' => $a->id, 'name' => $a->original_name, 'mime' => $a->mime_type, 'size' => $a->size,
            ])->all();
            $base['workflow_instances'] = $c->workflowInstances->map(fn ($i) => [
                'id' => $i->id, 'workflow' => $i->workflow?->name, 'status' => $i->status,
            ])->all();
            $base['contracts'] = $c->contracts->map(fn ($x) => [
                'id' => $x->id, 'name' => $x->name, 'status' => $x->status,
            ])->all();
            $base['notes'] = $c->notes->map(fn ($n) => [
                'id' => $n->id, 'body' => $n->body, 'user' => $n->user?->name,
                'created_at' => $n->created_at?->toIso8601String(),
            ])->all();
        }
        return $base;
    }
}
