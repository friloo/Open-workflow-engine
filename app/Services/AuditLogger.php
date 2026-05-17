<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

class AuditLogger
{
    /**
     * Write an audit entry that is hash-chained to the previous entry.
     *
     * Hash = sha256( prev_hash | event | auditable | user | timestamp | payload ).
     */
    public function log(
        string $event,
        ?Model $auditable = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $description = null,
        ?int $userId = null,
    ): AuditLog {
        return DB::transaction(function () use ($event, $auditable, $oldValues, $newValues, $description, $userId) {
            $prev = AuditLog::orderByDesc('id')->lockForUpdate()->first();
            $prevHash = $prev?->hash;

            $payload = [
                'prev_hash' => $prevHash,
                'event' => $event,
                'auditable_type' => $auditable?->getMorphClass(),
                'auditable_id' => $auditable?->getKey(),
                'user_id' => $userId ?? Auth::id(),
                'description' => $description,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'created_at' => now()->toIso8601String(),
            ];

            $hash = hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return AuditLog::create([
                'user_id' => $payload['user_id'],
                'event' => $event,
                'auditable_type' => $payload['auditable_type'],
                'auditable_id' => $payload['auditable_id'],
                'description' => $description,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'ip_address' => Request::ip(),
                'user_agent' => substr((string) Request::userAgent(), 0, 512),
                'prev_hash' => $prevHash,
                'hash' => $hash,
            ]);
        });
    }

    /**
     * Walk the chain and report the first broken link, if any.
     */
    public function verifyChain(): ?array
    {
        $prevHash = null;

        foreach (AuditLog::orderBy('id')->cursor() as $row) {
            $payload = [
                'prev_hash' => $prevHash,
                'event' => $row->event,
                'auditable_type' => $row->auditable_type,
                'auditable_id' => $row->auditable_id,
                'user_id' => $row->user_id,
                'description' => $row->description,
                'old_values' => $row->old_values,
                'new_values' => $row->new_values,
                'created_at' => $row->created_at?->toIso8601String(),
            ];
            $expected = hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            if ($row->prev_hash !== $prevHash || $row->hash !== $expected) {
                return [
                    'broken_at_id' => $row->id,
                    'expected_prev' => $prevHash,
                    'stored_prev' => $row->prev_hash,
                    'expected_hash' => $expected,
                    'stored_hash' => $row->hash,
                ];
            }

            $prevHash = $row->hash;
        }

        return null;
    }
}
