<?php

namespace App\Console\Commands;

use App\Models\WorkflowSchedule;
use App\Services\WorkflowEngine;
use Illuminate\Console\Command;

class RunDueWorkflowSchedules extends Command
{
    protected $signature = 'workflow:run-schedules {--limit=200}';
    protected $description = 'Startet wiederkehrende Workflows, deren nächste Fälligkeit erreicht ist.';

    public function handle(WorkflowEngine $engine): int
    {
        $due = WorkflowSchedule::with('workflow', 'subjectUser')
            ->where('is_active', true)
            ->where('next_run_at', '<=', now())
            ->limit((int) $this->option('limit'))
            ->get();

        if ($due->isEmpty()) {
            $this->info('Keine fälligen Schedules.');
            return self::SUCCESS;
        }

        $started = 0;
        foreach ($due as $schedule) {
            $workflow = $schedule->workflow;
            if (! $workflow || $workflow->status !== 'active' || ! $workflow->current_version_id) {
                $this->warn("Schedule #{$schedule->id}: Workflow nicht aktiv — übersprungen.");
                continue;
            }

            $formData = $schedule->payload ?? [];
            if ($schedule->subjectUser) {
                $formData['subject_user_id'] = $schedule->subjectUser->id;
                $formData['subject_user_email'] = $schedule->subjectUser->email;
                $formData['subject_user_name'] = $schedule->subjectUser->name;
            }
            if ($schedule->subject_label) {
                $formData['subject_label'] = $schedule->subject_label;
            }

            try {
                // Use the subject user as the initiator if set, so supervisor
                // resolution works against them in the engine.
                $engine->start($workflow, $formData, $schedule->subjectUser);
                $schedule->advance(now());
                $started++;
            } catch (\Throwable $e) {
                $this->error("Schedule #{$schedule->id}: {$e->getMessage()}");
            }
        }

        $this->info("{$started} Workflow(s) gestartet, {$due->count()} Schedules ausgewertet.");
        return self::SUCCESS;
    }
}
