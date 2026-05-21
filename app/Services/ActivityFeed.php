<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\AuditLog;
use App\Models\Contract;
use App\Models\User;
use App\Models\WorkflowStepExecution;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Aggregiert verschiedene Aktivitaets-Quellen zu einem zeit-sortierten Stream
 * fuer einen einzelnen User:
 *   - eingegangene App-Notifications (Workflow-Aufgaben, Erinnerungen)
 *   - eigene ueberfaellige + bald-faellige Aufgaben
 *   - Vertraege, die der User verantwortet und die bald auslaufen
 *   - eigene letzte Aktionen aus dem Audit-Log (was habe ich zuletzt getan)
 *
 * Items sind ein einheitliches Schema {type, tone, icon, title, body, url, at}
 * sodass das Frontend ohne Switch-Case rendern kann.
 */
class ActivityFeed
{
    public function for(User $user, int $limit = 30): array
    {
        $items = collect()
            ->concat($this->notifications($user))
            ->concat($this->overdueTasks($user))
            ->concat($this->dueSoonTasks($user))
            ->concat($this->expiringContracts($user))
            ->concat($this->myRecentActions($user))
            ->sortByDesc(fn ($x) => $x['at'])
            ->take($limit)
            ->values()
            ->all();

        return $items;
    }

    private function notifications(User $user): Collection
    {
        return AppNotification::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(15)
            ->get()
            ->map(fn ($n) => [
                'type' => 'notification',
                'tone' => $n->read_at ? 'slate' : 'indigo',
                'icon' => 'bell',
                'title' => $n->title,
                'body' => $n->body,
                'url' => $n->url ?: route('notifications.index'),
                'at' => $n->created_at,
                'unread' => $n->read_at === null,
            ]);
    }

    private function overdueTasks(User $user): Collection
    {
        return WorkflowStepExecution::query()
            ->whereNull('completed_at')
            ->where(function ($q) use ($user) {
                $q->where('assigned_to_user_id', $user->id)
                    ->orWhereIn('assigned_to_role_id', $user->roles->pluck('id'));
            })
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->with('instance.workflow:id,name')
            ->orderBy('due_at')
            ->limit(10)
            ->get()
            ->map(fn ($s) => [
                'type' => 'task_overdue',
                'tone' => 'rose',
                'icon' => 'alert',
                'title' => 'Überfällig: ' . ($s->instance?->workflow?->name ?? 'Aufgabe'),
                'body' => 'Frist war ' . optional($s->due_at)->diffForHumans(),
                'url' => route('tasks.show', $s),
                'at' => $s->due_at,
            ]);
    }

    private function dueSoonTasks(User $user): Collection
    {
        $horizon = now()->addDays(3);
        return WorkflowStepExecution::query()
            ->whereNull('completed_at')
            ->where(function ($q) use ($user) {
                $q->where('assigned_to_user_id', $user->id)
                    ->orWhereIn('assigned_to_role_id', $user->roles->pluck('id'));
            })
            ->whereNotNull('due_at')
            ->whereBetween('due_at', [now(), $horizon])
            ->with('instance.workflow:id,name')
            ->orderBy('due_at')
            ->limit(10)
            ->get()
            ->map(fn ($s) => [
                'type' => 'task_due_soon',
                'tone' => 'amber',
                'icon' => 'clock',
                'title' => 'Bald fällig: ' . ($s->instance?->workflow?->name ?? 'Aufgabe'),
                'body' => 'Frist ' . optional($s->due_at)->diffForHumans(),
                'url' => route('tasks.show', $s),
                'at' => $s->due_at,
            ]);
    }

    private function expiringContracts(User $user): Collection
    {
        if (! class_exists(Contract::class)) return collect();
        $horizon = now()->addDays(30);

        return Contract::query()
            ->where('owner_user_id', $user->id)
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [now(), $horizon])
            ->orderBy('end_date')
            ->limit(10)
            ->get()
            ->map(fn ($c) => [
                'type' => 'contract_expiring',
                'tone' => 'amber',
                'icon' => 'document',
                'title' => 'Vertrag läuft aus: ' . $c->name,
                'body' => 'Endet ' . optional($c->end_date)->diffForHumans(),
                'url' => route('contracts.show', $c),
                'at' => Carbon::parse($c->end_date),
            ]);
    }

    private function myRecentActions(User $user): Collection
    {
        return AuditLog::query()
            ->where('user_id', $user->id)
            ->whereNotIn('event', ['auth.login', 'auth.logout', 'audit.chain_verified', 'audit.exported'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn ($a) => [
                'type' => 'recent_action',
                'tone' => 'slate',
                'icon' => 'check',
                'title' => $a->description ?: $a->event,
                'body' => null,
                'url' => $this->subjectUrl($a),
                'at' => $a->created_at,
            ]);
    }

    private function subjectUrl(AuditLog $log): ?string
    {
        // Best-effort: bekannte Auditable-Klassen auf Show-Routes mappen
        $map = [
            \App\Models\WorkflowInstance::class => 'workflow-instances.show',
            \App\Models\Contract::class => 'contracts.show',
            \App\Models\DocumentCase::class => 'cases.show',
            \App\Models\Attachment::class => 'documents.show',
        ];
        if ($log->auditable_type && $log->auditable_id && ($route = $map[$log->auditable_type] ?? null)) {
            try {
                return route($route, $log->auditable_id);
            } catch (\Throwable) {
                return null;
            }
        }
        return null;
    }
}
