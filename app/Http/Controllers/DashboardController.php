<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowInstance;
use App\Models\WorkflowStepExecution;
use App\Services\HealthChecker;
use App\Support\DocumentTypes;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request, HealthChecker $health): View
    {
        $user = $request->user();
        $roleIds = $user->roles->pluck('id');

        // Meine offenen Aufgaben (direkt oder via Rolle)
        $myOpenTasksQuery = WorkflowStepExecution::query()
            ->with(['instance.workflow', 'instance.starter'])
            ->whereNull('completed_at')
            ->where(function ($q) use ($user, $roleIds) {
                $q->where('assigned_to_user_id', $user->id);
                if ($roleIds->isNotEmpty()) {
                    $q->orWhereIn('assigned_to_role_id', $roleIds);
                }
            });

        $myOpenCount = (clone $myOpenTasksQuery)->count();
        $myOverdueCount = (clone $myOpenTasksQuery)
            ->whereNotNull('due_at')->where('due_at', '<', now())->count();
        $myOpenTasks = $myOpenTasksQuery->orderByRaw('due_at IS NULL, due_at')->limit(5)->get();

        // Meine letzten Vorgaenge
        $myRecentInstances = WorkflowInstance::query()
            ->with(['workflow'])
            ->where('started_by', $user->id)
            ->orderByDesc('id')
            ->limit(5)->get();

        // Postkorb-Zaehler (orphan + sichtbar)
        $visibleTypes = DocumentTypes::visibleForUser($user);
        $inboxQuery = Attachment::whereNull('attachable_type')
            ->where('is_current_version', true);
        if (! $user->hasRole('admin')) {
            $inboxQuery->whereIn('document_type', $visibleTypes ?: ['__none__']);
        }
        $inboxCount = $inboxQuery->count();

        // Vertretungs-Hinweis
        $delegatedTo = $user->activeDelegate();

        // Admin-Extras
        $adminInfo = null;
        $onboarding = null;
        if ($user->hasPermission('system.health')) {
            $checks = $health->all();
            $overall = collect($checks)->reduce(function ($carry, $c) {
                if ($carry === 'fail' || $c['status'] === 'fail') return 'fail';
                if ($carry === 'warn' || $c['status'] === 'warn') return 'warn';
                return 'ok';
            }, 'ok');
            $adminInfo = [
                'health_status' => $overall,
                'health_warns' => collect($checks)->where('status', '!=', 'ok')->values()->all(),
            ];
        }

        // Onboarding-Checkliste fuer Admins — zeigt nur, wenn noch was offen ist.
        if ($user->hasPermission('system.settings')) {
            $onboarding = $this->buildOnboarding($user);
        }

        // Persoenliche Statistik fuer jeden User mit completed Steps in den
        // letzten 30 Tagen — sonst weglassen.
        $myStats = $this->buildPersonalStats($user);

        return view('dashboard', [
            'myOpenCount' => $myOpenCount,
            'myOverdueCount' => $myOverdueCount,
            'myOpenTasks' => $myOpenTasks,
            'myRecentInstances' => $myRecentInstances,
            'inboxCount' => $inboxCount,
            'delegatedTo' => $delegatedTo,
            'adminInfo' => $adminInfo,
            'onboarding' => $onboarding,
            'myStats' => $myStats,
        ]);
    }

    /**
     * Persoenliche Mini-Statistik der letzten 30 Tage: was hat dieser User
     * abgeschlossen, wie schnell, in welchen Workflows.
     *
     * Liefert null, wenn der User in dem Zeitraum nichts entschieden hat —
     * dann wird die Karte am Dashboard gar nicht angezeigt.
     */
    private function buildPersonalStats(User $user): ?array
    {
        $since = now()->subDays(30);
        $base = WorkflowStepExecution::query()
            ->where('completed_by', $user->id)
            ->where('completed_at', '>=', $since);

        $total = (clone $base)->count();
        if ($total === 0) return null;

        $byDecision = (clone $base)
            ->selectRaw('decision, count(*) as c')
            ->groupBy('decision')
            ->pluck('c', 'decision')->all();

        // Durchschnittliche Bearbeitungszeit in Minuten
        // (datediff variant pro DB-Treiber waere robuster, aber wir lesen
        // einfach und rechnen in PHP — Datensaetze sind klein).
        $rows = (clone $base)
            ->whereNotNull('assigned_at')
            ->whereNotNull('completed_at')
            ->get(['assigned_at', 'completed_at']);
        $avgMinutes = 0;
        if ($rows->count() > 0) {
            $sum = $rows->sum(fn ($r) => $r->assigned_at->diffInMinutes($r->completed_at));
            $avgMinutes = (int) round($sum / $rows->count());
        }

        // Top-Workflows
        $topWorkflows = (clone $base)
            ->with('instance.workflow:id,name')
            ->get(['workflow_instance_id'])
            ->groupBy(fn ($s) => $s->instance->workflow?->name ?: '—')
            ->map(fn ($items, $name) => ['name' => $name, 'count' => $items->count()])
            ->sortByDesc('count')
            ->take(3)
            ->values()
            ->all();

        return [
            'since' => $since,
            'total' => $total,
            'approved' => (int) ($byDecision['approved'] ?? 0),
            'rejected' => (int) ($byDecision['rejected'] ?? 0),
            'forwarded' => (int) ($byDecision['forwarded'] ?? 0),
            'avg_minutes' => $avgMinutes,
            'top_workflows' => $topWorkflows,
        ];
    }

    /**
     * Liefert eine Liste von Konfigurations-Hinweisen fuer Admins —
     * leer wenn alle abgeklopft sind. Wird auf dem Dashboard als
     * Erste-Schritte-Karte gerendert.
     */
    private function buildOnboarding(User $user): ?array
    {
        $mail = Settings::group('mail');
        $items = [
            [
                'label' => 'SMTP-Versand konfigurieren',
                'done' => ! empty($mail['host']) && ! empty($mail['from_address']),
                'url' => route('admin.settings.mail'),
                'hint' => 'Workflow-Benachrichtigungen brauchen einen Mailserver.',
            ],
            [
                'label' => 'Mindestens einen Workflow anlegen',
                'done' => Workflow::query()->exists(),
                'url' => route('workflows.index'),
                'hint' => 'Ohne Workflow gibt es keine Aufgaben.',
            ],
            [
                'label' => 'Dokumenttypen / Archive definieren',
                'done' => count(DocumentTypes::all()) > 0,
                'url' => route('admin.settings.documents'),
                'hint' => 'Klassifiziert Dokumente — Basis fuer Schemas + Retention.',
            ],
            [
                'label' => 'Datenquelle anbinden (Mailbox oder Folder-Inbox)',
                'done' => \App\Models\Mailbox::query()->exists() || \App\Models\FolderInbox::query()->exists(),
                'url' => route('admin.mailboxes.index'),
                'hint' => 'IMAP-Postfach oder Scan-Ordner fuer automatischen Ingest.',
            ],
            [
                'label' => 'Mindestens einen weiteren Benutzer anlegen',
                'done' => User::query()->where('id', '!=', $user->id)->exists(),
                'url' => route('admin.users.index'),
                'hint' => 'Damit ueberhaupt jemand Aufgaben zugewiesen bekommen kann.',
            ],
        ];

        $doneCount = collect($items)->where('done', true)->count();
        $totalCount = count($items);

        // Alles erledigt -> keine Karte mehr zeigen.
        if ($doneCount === $totalCount) return null;

        return ['items' => $items, 'done' => $doneCount, 'total' => $totalCount];
    }
}
