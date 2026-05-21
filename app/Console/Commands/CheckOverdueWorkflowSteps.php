<?php

namespace App\Console\Commands;

use App\Mail\WorkflowTaskAssignedMail;
use App\Models\User;
use App\Models\WorkflowStepExecution;
use App\Services\WorkflowEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class CheckOverdueWorkflowSteps extends Command
{
    protected $signature = 'workflow:check-due {--limit=200}';
    protected $description = 'Eskaliert überfällige Aufgaben und sendet Reminder vor Fristablauf.';

    public function handle(WorkflowEngine $engine): int
    {
        $limit = (int) $this->option('limit');

        // Wait-Steps mit fälligem due_at -> Workflow weiterlaufen lassen
        $wakeable = WorkflowStepExecution::query()
            ->whereNull('completed_at')
            ->where('step_type', 'wait')
            ->whereNotNull('due_at')
            ->where('due_at', '<=', now())
            ->orderBy('due_at')->limit($limit)->get();

        $resumed = 0;
        foreach ($wakeable as $step) {
            try {
                $engine->resumeWaitStep($step);
                $resumed++;
            } catch (\Throwable $e) {
                $this->error("Wait-Step #{$step->id}: {$e->getMessage()}");
            }
        }

        // Eskalation nur für Approval-Steps
        $overdue = WorkflowStepExecution::query()
            ->whereNull('completed_at')
            ->where('step_type', 'approval')
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->orderBy('due_at')->limit($limit)->get();

        $escalated = 0;
        foreach ($overdue as $step) {
            try {
                if ($engine->escalateOverdueStep($step)) $escalated++;
            } catch (\Throwable $e) {
                $this->error("Schritt #{$step->id}: {$e->getMessage()}");
            }
        }

        // Reminder vor Fristablauf: sendet Mail, wenn die Frist <= 24h ist
        // und noch keine Erinnerung verschickt wurde.
        $reminderWindow = now()->addDay();
        $remind = WorkflowStepExecution::query()
            ->whereNull('completed_at')
            ->whereNull('reminder_sent_at')
            ->whereNotNull('due_at')
            ->where('due_at', '>', now())
            ->where('due_at', '<=', $reminderWindow)
            ->limit($limit)->get();

        $reminded = 0;
        foreach ($remind as $step) {
            foreach ($this->recipientsFor($step) as $user) {
                if (! $user->email_notifications_enabled) continue;
                try {
                    Mail::to($user->email)->send(new WorkflowTaskAssignedMail($step, $user));
                } catch (\Throwable $e) {
                    $this->warn("Reminder an {$user->email} fehlgeschlagen: {$e->getMessage()}");
                }
            }
            $step->forceFill(['reminder_sent_at' => now()])->save();
            $reminded++;
        }

        $this->info("Wait aufgeweckt: {$resumed} · Eskaliert: {$escalated} · Reminder: {$reminded}");
        return self::SUCCESS;
    }

    /** @return iterable<User> */
    private function recipientsFor(WorkflowStepExecution $step): iterable
    {
        if ($step->assigned_to_user_id) {
            return [User::find($step->assigned_to_user_id)];
        }
        if ($step->assigned_to_role_id) {
            return \App\Models\Role::find($step->assigned_to_role_id)?->users ?? [];
        }
        return [];
    }
}
