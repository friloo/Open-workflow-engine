<?php

namespace App\Http\Controllers\Workflow;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowSchedule;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WorkflowScheduleController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Workflow $workflow): View
    {
        $schedules = $workflow->schedules()->with("subjectUser", "creator")->orderBy("next_run_at")->paginate(50);
        $users = User::humans()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'email']);
        return view('workflows.schedules.index', compact('workflow', 'schedules', 'users'));
    }

    public function store(Request $request, Workflow $workflow): RedirectResponse
    {
        $data = $request->validate([
            'subject_user_id' => ['nullable', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'subject_label' => ['nullable', 'string', 'max:255'],
            'interval_value' => ['required', 'integer', 'between:1,365'],
            'interval_unit' => ['required', 'in:days,weeks,months,years'],
            'next_run_at' => ['required', 'date'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $schedule = WorkflowSchedule::create([
            ...$data,
            'workflow_id' => $workflow->id,
            'is_active' => $request->boolean('is_active', true),
            'next_run_at' => Carbon::parse($data['next_run_at']),
            'created_by' => $request->user()->id,
        ]);

        $this->audit->log('workflow.schedule.created', $schedule, null, [
            'workflow' => $workflow->name,
            'subject' => $schedule->subjectUser?->email ?? $schedule->subject_label,
            'interval' => $schedule->interval_value.' '.$schedule->interval_unit,
        ], "Schedule fuer Workflow {$workflow->name} angelegt");

        return redirect()->route('workflows.schedules.index', $workflow)->with('status', 'Schedule angelegt.');
    }

    public function update(Request $request, Workflow $workflow, WorkflowSchedule $schedule): RedirectResponse
    {
        abort_unless($schedule->workflow_id === $workflow->id, 404);

        $data = $request->validate([
            'subject_user_id' => ['nullable', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'subject_label' => ['nullable', 'string', 'max:255'],
            'interval_value' => ['required', 'integer', 'between:1,365'],
            'interval_unit' => ['required', 'in:days,weeks,months,years'],
            'next_run_at' => ['required', 'date'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $original = $schedule->only(array_keys($data));
        $schedule->update([
            ...$data,
            'is_active' => $request->boolean('is_active'),
            'next_run_at' => Carbon::parse($data['next_run_at']),
        ]);

        $this->audit->log('workflow.schedule.updated', $schedule, $original, $schedule->only(array_keys($data)),
            "Schedule #{$schedule->id} aktualisiert");

        return back()->with('status', 'Schedule aktualisiert.');
    }

    public function destroy(Workflow $workflow, WorkflowSchedule $schedule): RedirectResponse
    {
        abort_unless($schedule->workflow_id === $workflow->id, 404);

        $snapshot = $schedule->only(['id', 'subject_user_id', 'subject_label']);
        $schedule->delete();

        $this->audit->log('workflow.schedule.deleted', null, $snapshot, null,
            "Schedule #{$snapshot['id']} geloescht");

        return back()->with('status', 'Schedule geloescht.');
    }
}
