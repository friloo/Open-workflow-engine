<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\WorkflowInstance;
use App\Models\WorkflowStepExecution;
use App\Services\HealthChecker;
use App\Support\DocumentTypes;
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

        return view('dashboard', [
            'myOpenCount' => $myOpenCount,
            'myOverdueCount' => $myOverdueCount,
            'myOpenTasks' => $myOpenTasks,
            'myRecentInstances' => $myRecentInstances,
            'inboxCount' => $inboxCount,
            'delegatedTo' => $delegatedTo,
            'adminInfo' => $adminInfo,
        ]);
    }
}
