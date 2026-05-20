<?php

namespace App\View\Components;

use App\Models\Attachment;
use App\Models\WorkflowStepExecution;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Vereinheitlichte Eingangs-Navigation: Aufgaben, Posteingang
 * (=Postkorb) und Wiedervorlagen.
 *
 * Wird auf den drei Seiten (tasks/index, documents/inbox,
 * tasks/index?filter=snoozed) eingebunden, damit der Anwender alle
 * eingehenden Dinge an einem Ort sieht.
 *
 * Counts werden pro Request gecached (60s), um drei kleine COUNT-Queries
 * nicht auf jedem Reload zu wiederholen.
 */
class InboxTabs extends Component
{
    public string $current;

    /** @var array<string, int> */
    public array $counts = ['tasks' => 0, 'postkorb' => 0, 'snoozed' => 0];

    public bool $showPostkorb = false;

    public function __construct(string $current = 'tasks')
    {
        $this->current = $current;
        $this->loadCounts();
    }

    private function loadCounts(): void
    {
        $user = Auth::user();
        if (! $user) return;

        $this->showPostkorb = $user->hasPermission('documents.search');
        $roleIds = $user->roles->pluck('id');
        $hasSnooze = Schema::hasColumn('workflow_step_executions', 'snoozed_until');

        $base = fn () => WorkflowStepExecution::query()
            ->whereNull('completed_at')
            ->where(function ($q) use ($user, $roleIds) {
                $q->where('assigned_to_user_id', $user->id);
                if ($roleIds->isNotEmpty()) $q->orWhereIn('assigned_to_role_id', $roleIds);
            });

        $openQuery = $base();
        if ($hasSnooze) {
            $openQuery->where(function ($q) {
                $q->whereNull('snoozed_until')->orWhere('snoozed_until', '<=', now());
            });
        }

        $this->counts = [
            'tasks' => $openQuery->count(),
            'postkorb' => $this->showPostkorb && Schema::hasTable('attachments')
                ? Attachment::query()->whereNull('attachable_id')->count()
                : 0,
            'snoozed' => $hasSnooze
                ? $base()->whereNotNull('snoozed_until')->where('snoozed_until', '>', now())->count()
                : 0,
        ];
    }

    public function render(): View
    {
        return view('components.inbox-tabs');
    }
}
