<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\ContractType;
use App\Services\AttachmentStorage;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API: Verträge. Token-Abilities:
 *  - contracts.view  (Lesen)
 *  - contracts.manage (Anlegen/Ändern/Anhänge hochladen)
 */
class ContractsApiController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $q = Contract::query()->visibleTo($user)->with(['type', 'owner']);
        if ($s = trim((string) $request->get('q', ''))) {
            $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")
                ->orWhere('party', 'like', "%{$s}%"));
        }
        if ($status = $request->get('status')) $q->where('status', $status);
        if ($typeId = (int) $request->get('type_id', 0)) $q->where('contract_type_id', $typeId);

        return response()->json([
            'data' => $q->orderBy('end_date')->limit(200)->get()->map(fn (Contract $c) => self::serialize($c)),
        ]);
    }

    public function show(Request $request, Contract $contract): JsonResponse
    {
        if (! Contract::query()->visibleTo($request->user())->whereKey($contract->id)->exists()) {
            return response()->json(['message' => 'Kein Zugriff.'], 403);
        }
        $contract->load(['type', 'owner', 'roles', 'attachments']);
        return response()->json(self::serialize($contract, full: true));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'party' => ['nullable', 'string', 'max:255'],
            'contract_type_id' => ['nullable', 'exists:contract_types,id'],
            'description' => ['nullable', 'string'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'notice_period_days' => ['nullable', 'integer', 'between:0,3650'],
            'owner_user_id' => ['nullable', 'exists:users,id'],
        ]);
        $data['notice_period_days'] = $data['notice_period_days']
            ?? (ContractType::find($data['contract_type_id'] ?? 0)?->default_notice_period_days ?? 90);
        $data['created_by'] = $request->user()->id;
        $c = Contract::create($data);
        $c->update(['status' => $c->computedStatus()]);

        $this->audit->log('contract.created', $c, null, $data,
            'Vertrag '.$c->name.' via API angelegt', $request->user()->id);

        return response()->json(self::serialize($c, full: true), 201);
    }

    public function update(Request $request, Contract $contract): JsonResponse
    {
        if (! $contract->userCanManage($request->user())) {
            return response()->json(['message' => 'Kein Bearbeitungsrecht.'], 403);
        }
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'party' => ['nullable', 'string', 'max:255'],
            'contract_type_id' => ['nullable', 'exists:contract_types,id'],
            'description' => ['nullable', 'string'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'notice_period_days' => ['nullable', 'integer', 'between:0,3650'],
            'owner_user_id' => ['nullable', 'exists:users,id'],
        ]);
        $contract->update($data);
        $contract->update(['status' => $contract->computedStatus()]);
        $this->audit->log('contract.updated', $contract, null, $data,
            'Vertrag '.$contract->name.' via API geändert', $request->user()->id);
        return response()->json(self::serialize($contract, full: true));
    }

    public function uploadAttachment(Request $request, Contract $contract, AttachmentStorage $storage): JsonResponse
    {
        if (! $contract->userCanManage($request->user())) {
            return response()->json(['message' => 'Kein Bearbeitungsrecht.'], 403);
        }
        $request->validate([
            'file' => ['required', 'file', 'max:15360'],
            'label' => ['nullable', 'string', 'max:128'],
        ]);
        try {
            $att = $storage->store(
                $request->file('file'),
                $contract,
                $request->input('label'),
                $request->user()->id,
                null,
                null,
                (bool) $request->boolean('force'),
            );
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return response()->json([
            'id' => $att->id,
            'name' => $att->original_name,
            'size' => $att->size,
            'mime' => $att->mime_type,
            'sha256' => $att->content_hash,
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    public static function serialize(Contract $c, bool $full = false): array
    {
        $base = [
            'id' => $c->id,
            'name' => $c->name,
            'party' => $c->party,
            'status' => $c->status,
            'start_date' => $c->start_date?->format('Y-m-d'),
            'end_date' => $c->end_date?->format('Y-m-d'),
            'notice_period_days' => $c->notice_period_days,
            'notice_deadline' => $c->noticeDeadline()?->format('Y-m-d'),
            'auto_renew' => (bool) $c->auto_renew,
            'auto_renew_months' => $c->auto_renew_months,
            'contract_type' => $c->type ? ['id' => $c->type->id, 'name' => $c->type->name, 'slug' => $c->type->slug] : null,
            'owner' => $c->owner ? ['id' => $c->owner->id, 'name' => $c->owner->name, 'email' => $c->owner->email] : null,
        ];
        if ($full) {
            $base['description'] = $c->description;
            $base['attachments'] = $c->attachments->map(fn ($a) => [
                'id' => $a->id, 'name' => $a->original_name, 'size' => $a->size,
                'mime' => $a->mime_type, 'sha256' => $a->content_hash,
            ])->all();
            $base['roles'] = $c->roles->map(fn ($r) => [
                'id' => $r->id, 'slug' => $r->slug, 'name' => $r->name,
                'can_manage' => (bool) $r->pivot->can_manage,
            ])->all();
        }
        return $base;
    }
}
