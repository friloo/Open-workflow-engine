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
    protected $description = 'Eskaliert ueberfaellige Aufgaben und sendet Reminder vor Fristablauf.';

    public function handle(WorkflowEngine $engine): int
    {
        $limit = (int) $this->option('limit');

        // Eskalation
        $overdue = WorkflowStepExecution::query()
            ->whereNull('completed_at')
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

        $this->info("Eskaliert: {$escalated} · Reminder: {$reminded} · Ueberfaellig insgesamt: {$overdue->count()}");
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
