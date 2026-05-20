<?php

namespace App\Support;

use App\Models\WorkflowStepExecution;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Liefert die Anzahl offener (nicht zurueckgestellter) Aufgaben fuer den
 * aktuell eingeloggten Benutzer. Wird im Topbar-Inbox-Badge verwendet,
 * 30s gecached um pro Request hoeher Last zu vermeiden.
 */
class InboxCounter
{
    public static function openCount(): int
    {
        $user = Auth::user();
        if (! $user) return 0;

        return Cache::remember('topbar.inbox.u' . $user->id, 30, function () use ($user) {
            $roleIds = $user->roles->pluck('id');
            $q = WorkflowStepExecution::query()
                ->whereNull('completed_at')
                ->where(function ($q2) use ($user, $roleIds) {
                    $q2->where('assigned_to_user_id', $user->id);
                    if ($roleIds->isNotEmpty()) $q2->orWhereIn('assigned_to_role_id', $roleIds);
                });
            if (Schema::hasColumn('workflow_step_executions', 'snoozed_until')) {
                $q->where(function ($q2) {
                    $q2->whereNull('snoozed_until')->orWhere('snoozed_until', '<=', now());
                });
            }
            return $q->count();
        });
    }
}
