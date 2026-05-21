<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowStepExecution;
use App\Support\DocumentTypes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Liefert Suchergebnisse für das Cmd+K-Modal — gruppiert nach Bereich
 * (Dokumente, Workflows, Aufgaben, Benutzer). Pro Bereich max. 5 Treffer,
 * damit das Modal schnell bleibt.
 */
class GlobalSearchController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->get('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['groups' => [], 'q' => $q]);
        }

        $user = $request->user();
        $like = '%'.$q.'%';
        $groups = [];

        // Dokumente
        if ($user->hasPermission('documents.search')) {
            $visibleTypes = DocumentTypes::visibleForUser($user);
            $allowAll = $user->hasRole('admin');
            $includeUnclassified = $allowAll || (bool) \App\Support\Settings::get('attachments.unclassified_visible_for_all', false);

            $docQuery = Attachment::query()
                ->with('attachable')
                ->where('is_current_version', true);
            if (! $allowAll) {
                $docQuery->where(function ($w) use ($visibleTypes, $includeUnclassified) {
                    if ($includeUnclassified) $w->whereNull('document_type');
                    if (! empty($visibleTypes)) $w->orWhereIn('document_type', $visibleTypes);
                    if (! $includeUnclassified && empty($visibleTypes)) $w->whereRaw('1=0');
                });
            }
            $docQuery->where(function ($w) use ($like) {
                $w->where('original_name', 'like', $like)
                  ->orWhere('label', 'like', $like)
                  ->orWhere('ocr_text', 'like', $like);
            });
            $docs = $docQuery->orderByDesc('id')->limit(5)->get();
            if ($docs->isNotEmpty()) {
                $groups[] = [
                    'label' => 'Dokumente',
                    'items' => $docs->map(fn ($d) => [
                        'title' => $d->original_name,
                        'subtitle' => $d->document_type ?: 'Unklassifiziert',
                        'url' => route('documents.show', $d),
                    ])->values(),
                ];
            }
        }

        // Workflows
        if ($user->hasAnyPermission(['workflows.view', 'workflows.design', 'workflows.run'])) {
            $wfs = Workflow::where('name', 'like', $like)
                ->orderBy('name')->limit(5)->get(['id', 'name']);
            if ($wfs->isNotEmpty()) {
                $groups[] = [
                    'label' => 'Workflows',
                    'items' => $wfs->map(fn ($w) => [
                        'title' => $w->name,
                        'subtitle' => 'Workflow #'.$w->id,
                        'url' => $user->hasPermission('workflows.design')
                            ? route('workflows.design', $w)
                            : route('workflow-instances.index', ['workflow_id' => $w->id]),
                    ])->values(),
                ];
            }
        }

        // Eigene offene Aufgaben (filter by workflow name)
        $roleIds = $user->roles->pluck('id');
        $taskQuery = WorkflowStepExecution::query()
            ->with('instance.workflow')
            ->whereNull('completed_at')
            ->where(function ($w) use ($user, $roleIds) {
                $w->where('assigned_to_user_id', $user->id);
                if ($roleIds->isNotEmpty()) $w->orWhereIn('assigned_to_role_id', $roleIds);
            })
            ->whereHas('instance.workflow', fn ($w) => $w->where('name', 'like', $like));
        $tasks = $taskQuery->orderBy('due_at')->limit(5)->get();
        if ($tasks->isNotEmpty()) {
            $groups[] = [
                'label' => 'Meine Aufgaben',
                'items' => $tasks->map(fn ($t) => [
                    'title' => $t->instance->workflow->name,
                    'subtitle' => $t->due_at ? 'fällig '.$t->due_at->format('d.m.Y H:i') : 'ohne Frist',
                    'url' => route('tasks.show', $t),
                ])->values(),
            ];
        }

        // Benutzer
        if ($user->hasAnyPermission(['users.view', 'users.update'])) {
            $users = User::humans()->where(function ($w) use ($like) {
                $w->where('name', 'like', $like)->orWhere('email', 'like', $like);
            })->orderBy('name')->limit(5)->get(['id', 'name', 'email']);
            if ($users->isNotEmpty()) {
                $groups[] = [
                    'label' => 'Benutzer',
                    'items' => $users->map(fn ($u) => [
                        'title' => $u->name,
                        'subtitle' => $u->email,
                        'url' => route('admin.users.edit', $u),
                    ])->values(),
                ];
            }
        }

        return response()->json(['groups' => $groups, 'q' => $q]);
    }
}
