<?php

namespace App\Console\Commands;

use App\Mail\TaskReminderMail;
use App\Models\AppNotification;
use App\Models\WorkflowStepExecution;
use App\Services\WorkflowEngine;
use App\Support\Settings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Erinnert Empfänger an offene Aufgaben.
 *
 * Konfigurierbar via Settings:
 *  - tasks.reminder_after_days   (Default 3): Schwelle bevor zum ersten Mal erinnert wird
 *  - tasks.reminder_interval_days (Default 3): Mindestabstand zwischen Erinnerungen
 */
class RemindOpenTasks extends Command
{
    protected $signature = 'tasks:remind {--dry-run}';
    protected $description = 'Erinnert per Mail an offene Workflow-Aufgaben.';

    public function handle(WorkflowEngine $engine): int
    {
        $afterDays = (int) Settings::get('tasks.reminder_after_days', 3);
        $intervalDays = (int) Settings::get('tasks.reminder_interval_days', 3);
        $dryRun = (bool) $this->option('dry-run');

        $assignedCutoff = now()->subDays($afterDays);
        $intervalCutoff = now()->subDays($intervalDays);

        $steps = WorkflowStepExecution::query()
            ->with(['instance.workflow', 'instance.version', 'instance.starter'])
            ->whereNull('completed_at')
            ->where('assigned_at', '<', $assignedCutoff)
            ->where(function ($q) use ($intervalCutoff) {
                $q->whereNull('last_reminded_at')->orWhere('last_reminded_at', '<', $intervalCutoff);
            })
            ->orderBy('assigned_at')
            ->get();

        if ($steps->isEmpty()) {
            $this->info('Keine offenen Aufgaben mit fälliger Erinnerung.');
            return self::SUCCESS;
        }

        $mails = 0; $notifications = 0;
        foreach ($steps as $step) {
            $recipients = $engine->stepRecipients($step);
            foreach ($recipients as $user) {
                $this->line("#{$step->id} -> {$user->email}");
                if ($dryRun) continue;
                if (\App\Support\NotificationPreferences::wants($user, 'task.reminder', 'in_app')) {
                    AppNotification::send($user, 'task.reminder',
                        'Erinnerung: offene Aufgabe',
                        'Eine Workflow-Aufgabe wartet seit '.(int) $step->assigned_at->diffInDays(now()).' Tag(en) auf deine Reaktion.',
                        route('tasks.show', $step));
                    $notifications++;
                }
                if (! \App\Support\NotificationPreferences::wants($user, 'task.reminder', 'mail')) continue;
                try {
                    Mail::to($user->email)->send(new TaskReminderMail($step, $user));
                    $mails++;
                } catch (\Throwable $e) {
                    Log::warning('Reminder mail failed', ['to' => $user->email, 'error' => $e->getMessage()]);
                }
            }
            if (! $dryRun) {
                $step->forceFill(['last_reminded_at' => now()])->save();
            }
        }

        $this->info("Erinnerungen: {$steps->count()} Aufgaben, {$notifications} Notifications, {$mails} Mails.");
        return self::SUCCESS;
    }
}
