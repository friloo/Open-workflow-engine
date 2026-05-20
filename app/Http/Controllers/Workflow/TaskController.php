<?php

namespace App\Http\Controllers\Workflow;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WorkflowStepExecution;
use App\Services\WorkflowEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function __construct(private readonly WorkflowEngine $engine) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $roleIds = $user->roles->pluck('id');
        $filter = $request->get('filter', 'all');
        $q = trim((string) $request->get('q', ''));

        $baseScope = fn ($query) => $query
            ->whereNull('completed_at')
            ->where(function ($q2) use ($user, $roleIds) {
                $q2->where('assigned_to_user_id', $user->id);
                if ($roleIds->isNotEmpty()) {
                    $q2->orWhereIn('assigned_to_role_id', $roleIds);
                }
            });

        // Snoozed (Wiedervorlage) standardmaessig ausblenden — User hat sich
        // bewusst entschieden, das erst spaeter zu bearbeiten.
        // Defensiv: ohne snoozed_until-Migration ueberspringen wir den Filter.
        $hasSnooze = Schema::hasColumn('workflow_step_executions', 'snoozed_until');
        $hideSnoozed = $hasSnooze
            ? fn ($q) => $q->where(function ($q2) {
                $q2->whereNull('snoozed_until')->orWhere('snoozed_until', '<=', now());
            })
            : fn ($q) => $q;

        // Counts pro Filter-Chip — eine Roundtrip-Query reicht nicht, aber
        // pro Chip eine count()-Query ist okay (kleine Datenmengen pro User).
        $now = now();
        $counts = [
            'all' => $hideSnoozed($baseScope(WorkflowStepExecution::query()))->count(),
            'overdue' => $hideSnoozed($baseScope(WorkflowStepExecution::query()))
                ->whereNotNull('due_at')->where('due_at', '<', $now)->count(),
            'today' => $hideSnoozed($baseScope(WorkflowStepExecution::query()))
                ->whereNotNull('due_at')->whereBetween('due_at', [$now->copy()->startOfDay(), $now->copy()->endOfDay()])->count(),
            'week' => $hideSnoozed($baseScope(WorkflowStepExecution::query()))
                ->whereNotNull('due_at')->whereBetween('due_at', [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()])->count(),
            'mine' => $hideSnoozed($baseScope(WorkflowStepExecution::query()))
                ->where('assigned_to_user_id', $user->id)->whereNull('assigned_to_role_id')->count(),
            'snoozed' => $hasSnooze
                ? $baseScope(WorkflowStepExecution::query())
                    ->whereNotNull('snoozed_until')->where('snoozed_until', '>', $now)->count()
                : 0,
        ];

        $query = WorkflowStepExecution::query()
            ->with(['instance.workflow', 'instance.starter', 'assignedRole']);
        $baseScope($query);

        // Snoozed-Filter zeigt absichtlich die zurueckgestellten Aufgaben;
        // sonst werden sie ausgeblendet.
        if ($filter !== 'snoozed') $hideSnoozed($query);

        switch ($filter) {
            case 'overdue':
                $query->whereNotNull('due_at')->where('due_at', '<', $now);
                break;
            case 'today':
                $query->whereNotNull('due_at')->whereBetween('due_at', [$now->copy()->startOfDay(), $now->copy()->endOfDay()]);
                break;
            case 'week':
                $query->whereNotNull('due_at')->whereBetween('due_at', [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()]);
                break;
            case 'mine':
                $query->where('assigned_to_user_id', $user->id)->whereNull('assigned_to_role_id');
                break;
            case 'snoozed':
                if ($hasSnooze) {
                    $query->whereNotNull('snoozed_until')->where('snoozed_until', '>', $now);
                }
                break;
        }

        if ($q !== '') {
            $query->whereHas('instance.workflow', fn ($w) => $w->where('name', 'like', '%'.$q.'%'));
        }

        $open = $query->orderBy('due_at')->paginate(20)->withQueryString();

        $myRecent = WorkflowStepExecution::query()
            ->with(['instance.workflow'])
            ->where('completed_by', $user->id)
            ->orderByDesc('completed_at')
            ->limit(5)->get();

        $savedSearches = Schema::hasTable('saved_searches')
            ? \App\Models\SavedSearch::where('user_id', $user->id)
                ->where('scope', 'tasks')
                ->orderBy('sort_order')->orderBy('id')->get()
            : collect();

        return view('tasks.index', compact('open', 'myRecent', 'counts', 'filter', 'q', 'savedSearches'));
    }

    public function show(WorkflowStepExecution $step, Request $request): View
    {
        $this->authorizeStep($step, $request->user());

        $step->load([
            'instance.workflow', 'instance.starter', 'instance.version',
            'instance.stepExecutions', 'instance.attachments',
            'assignedUser', 'assignedRole',
        ]);

        $node = $step->instance->version->definition['drawflow']['Home']['data'][$step->step_key] ?? null;

        $completedKeys = $step->instance->stepExecutions
            ->whereNotNull('completed_at')
            ->pluck('step_key')->unique()->values()->all();

        return view('tasks.show', [
            'step' => $step,
            'node' => $node,
            'instance' => $step->instance,
            'directory' => [
                'users' => User::where('is_active', true)
                    ->where('id', '!=', $request->user()->id)
                    ->orderBy('name')->limit(500)->get(['id', 'name', 'email']),
            ],
            'viewerPayload' => [
                'definition' => $step->instance->version->definition,
                'completed_step_keys' => $completedKeys,
                'current_step_key' => $step->step_key,
                'status' => $step->instance->status,
            ],
        ]);
    }

    public function decide(Request $request, WorkflowStepExecution $step): RedirectResponse
    {
        $this->authorizeStep($step, $request->user());

        // Bedingte Pflichtfelder aus dem Approval-Knoten lesen.
        $node = $step->instance->version->definition['drawflow']['Home']['data'][$step->step_key] ?? null;
        $requireOnApproval = (bool) data_get($node, 'data.require_comment_on_approval', false);
        $requireOnRejection = (bool) data_get($node, 'data.require_comment_on_rejection', false);
        $extraFieldsConfig = collect((array) data_get($node, 'data.extra_fields', []))
            ->filter(fn ($f) => ! empty($f['key']))
            ->values()->all();

        $decision = $request->input('decision');
        $commentRule = 'nullable';
        if ($decision === 'approved' && $requireOnApproval) {
            $commentRule = 'required';
        } elseif ($decision === 'rejected' && $requireOnRejection) {
            $commentRule = 'required';
        }

        $rules = [
            'decision' => ['required', 'in:approved,rejected,forwarded'],
            'comment' => [$commentRule, 'string', 'max:2000'],
            'forward_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
        $messages = [
            'comment.required' => $decision === 'rejected'
                ? 'Bitte gib eine Begruendung fuer die Ablehnung ein.'
                : 'Bitte gib einen Kommentar zur Genehmigung ein.',
        ];

        // Validierungsregeln pro Zusatzfeld dynamisch aufbauen. Pflichtfelder
        // gelten nur bei approved/rejected, nicht bei forwarded.
        $isFinal = in_array($decision, ['approved', 'rejected'], true);
        foreach ($extraFieldsConfig as $f) {
            $required = $isFinal && ! empty($f['required']);
            $type = $f['type'] ?? 'text';
            $rule = [$required ? 'required' : 'nullable'];
            switch ($type) {
                case 'number':   $rule[] = 'numeric'; break;
                case 'date':     $rule[] = 'date'; break;
                case 'checkbox': $rule = [$required ? 'accepted' : 'nullable', 'in:0,1']; break;
                case 'select':
                    if (! empty($f['options'])) $rule[] = 'in:'.implode(',', (array) $f['options']);
                    break;
                case 'textarea': $rule[] = 'string'; $rule[] = 'max:5000'; break;
                default:         $rule[] = 'string'; $rule[] = 'max:1000';
            }
            $rules['extra.'.$f['key']] = $rule;
            $messages['extra.'.$f['key'].'.required'] = 'Bitte „'.($f['label'] ?? $f['key']).'" ausfuellen.';
        }

        $data = $request->validate($rules, $messages);

        if ($data['decision'] === 'forwarded') {
            if (empty($data['forward_user_id'])) {
                return back()->withErrors(['forward_user_id' => 'Bitte einen Benutzer auswaehlen.']);
            }
            $target = User::findOrFail($data['forward_user_id']);
            $this->engine->forwardStep($step, $target, $data['comment'] ?? null, $request->user()->id);
            return redirect()->route('tasks.index')->with('status', 'Aufgabe weitergeleitet an '.$target->name.'.');
        }

        // Zusatzfelder anwenden: pro Feld entweder ans Doku (alle attached
        // documents kriegen die Werte in indexed_fields) oder an die
        // Workflow-Instance-Daten.
        if (! empty($extraFieldsConfig)) {
            $this->applyExtraFields($step, $extraFieldsConfig, $data['extra'] ?? [], $request->user()->id);
        }

        $this->engine->completeStep(
            $step,
            $data['decision'],
            $data['comment'] ?? null,
            $request->user()->id,
        );

        return redirect()->route('tasks.index')->with('status', 'Entscheidung gespeichert.');
    }

    /**
     * Schreibt die im Approval konfigurierten Zusatzfelder an den
     * vorgesehenen Ort:
     *  - target='doc':       indexed_fields aller an dieser Instanz
     *                        haengenden Anhaenge werden gemerged.
     *  - target='instance':  in $instance->data['_approval'][step_key].
     *
     * Audit pro Feld, damit der Verlauf nachvollziehbar bleibt.
     */
    private function applyExtraFields(WorkflowStepExecution $step, array $fieldsConfig, array $values, int $userId): void
    {
        $instance = $step->instance;
        $docFields = [];
        $instanceFields = [];
        // Mapping vom Designer-Feldtyp zum Schema-Feldtyp.
        $typeMap = [
            'text' => 'string', 'textarea' => 'string', 'select' => 'string',
            'checkbox' => 'string', 'number' => 'number', 'date' => 'date',
        ];
        // Pro Doku-Type sammeln wir die Schema-Felder, die ggf. ergaenzt werden muessen.
        $docSchemaUpdates = [];

        foreach ($fieldsConfig as $f) {
            $key = $f['key'];
            if (! array_key_exists($key, $values)) continue;
            $value = $values[$key];
            // Booleans aus 0/1-Strings normalisieren
            if (($f['type'] ?? '') === 'checkbox') $value = (bool) (int) $value;
            // Leere optional-Felder weglassen
            if ($value === null || $value === '') continue;

            $target = $f['target'] ?? 'doc';
            if ($target === 'instance') {
                $instanceFields[$key] = $value;
            } else {
                $docFields[$key] = $value;
                $docSchemaUpdates[$key] = [
                    'label' => $f['label'] ?? $key,
                    'type' => $typeMap[$f['type'] ?? 'text'] ?? 'string',
                ];
            }
        }

        if (! empty($docFields)) {
            $attachments = $instance->attachments;
            $touchedTypes = [];
            foreach ($attachments as $att) {
                $existing = (array) ($att->indexed_fields ?? []);
                $merged = array_merge($existing, $docFields);
                $att->indexed_fields = $merged;
                $att->indexed_at = now();
                $att->save();
                app(\App\Services\AuditLogger::class)->log(
                    'attachment.indexed_fields.approval',
                    $att,
                    ['indexed_fields' => $existing],
                    ['indexed_fields' => $merged, 'step_key' => $step->step_key],
                    'Indexfelder via Genehmigungs-Zusatzfelder aktualisiert',
                    $userId,
                );
                if ($att->document_type) $touchedTypes[$att->document_type] = true;
            }

            // Schema-Auto-Pflege: pro angefasstem Doku-Type fehlende Felder
            // ergaenzen. Idempotent — bestehende Schemas bleiben unveraendert
            // (Extractor/Pattern werden NICHT ueberschrieben).
            foreach (array_keys($touchedTypes) as $docType) {
                $added = [];
                foreach ($docSchemaUpdates as $key => $meta) {
                    if (\App\Support\DocumentFieldSchema::ensureField($docType, $key, $meta['label'], $meta['type'])) {
                        $added[] = $key;
                    }
                }
                if ($added) {
                    app(\App\Services\AuditLogger::class)->log(
                        'document_schema.field_added_from_approval',
                        null,
                        null,
                        ['document_type' => $docType, 'added_keys' => $added, 'step_key' => $step->step_key],
                        "Schema '{$docType}' um Felder erweitert: ".implode(', ', $added),
                        $userId,
                    );
                }
            }
        }

        if (! empty($instanceFields)) {
            $payload = (array) ($instance->data ?? []);
            $payload['_approval'] = (array) ($payload['_approval'] ?? []);
            $payload['_approval'][$step->step_key] = array_merge(
                (array) ($payload['_approval'][$step->step_key] ?? []),
                $instanceFields,
            );
            // Plus auf Top-Level, damit Folgeknoten direkt drauf zugreifen koennen
            $payload = array_merge($payload, $instanceFields);
            $instance->data = $payload;
            $instance->save();
        }
    }

    /**
     * Snooze (Wiedervorlage): User legt fest, ab wann er die Aufgabe wieder
     * sehen will. Bis dahin ist sie aus seiner Liste — taucht aber nicht
     * verloren, nur ausgeblendet. Bei 'cancel' wird der Snooze entfernt.
     */
    public function snooze(Request $request, WorkflowStepExecution $step): RedirectResponse
    {
        $this->authorizeStep($step, $request->user());

        $data = $request->validate([
            'when' => ['required', 'in:1h,4h,tomorrow,3d,1w,custom,cancel'],
            'custom_at' => ['nullable', 'date', 'after:now'],
        ]);

        if ($data['when'] === 'cancel') {
            $step->update(['snoozed_until' => null]);
            return back()->with('status', 'Wiedervorlage entfernt.');
        }

        $target = match ($data['when']) {
            '1h' => now()->addHour(),
            '4h' => now()->addHours(4),
            'tomorrow' => now()->addDay()->startOfDay()->addHours(8),
            '3d' => now()->addDays(3),
            '1w' => now()->addWeek(),
            'custom' => $data['custom_at'] ? \Carbon\Carbon::parse($data['custom_at']) : null,
        };
        if (! $target) {
            return back()->withErrors(['custom_at' => 'Bitte ein gueltiges Datum angeben.']);
        }

        $step->update(['snoozed_until' => $target]);
        return redirect()->route('tasks.index')
            ->with('status', 'Wiedervorlage gesetzt: '.$target->format('d.m.Y H:i'));
    }

    private function authorizeStep(WorkflowStepExecution $step, User $user): void
    {
        if ($step->completed_at) {
            abort(410, 'Diese Aufgabe wurde bereits abgeschlossen.');
        }
        if ($step->assigned_to_user_id && $step->assigned_to_user_id === $user->id) return;
        if ($step->assigned_to_role_id && $user->roles->pluck('id')->contains($step->assigned_to_role_id)) return;
        abort(403);
    }
}
