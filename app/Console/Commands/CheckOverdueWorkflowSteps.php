<?php

namespace App\Console\Commands;

use App\Models\WorkflowStepExecution;
use App\Services\WorkflowEngine;
use Illuminate\Console\Command;

class CheckOverdueWorkflowSteps extends Command
{
    protected $signature = 'workflow:check-due {--limit=200}';
    protected $description = 'Eskaliert offene Workflow-Schritte, deren Karenzzeit abgelaufen ist.';

    public function handle(WorkflowEngine $engine): int
    {
        $limit = (int) $this->option('limit');

        $steps = WorkflowStepExecution::query()
            ->whereNull('completed_at')
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->orderBy('due_at')
            ->limit($limit)
            ->get();

        if ($steps->isEmpty()) {
            $this->info('Keine ueberfaelligen Aufgaben.');
            return self::SUCCESS;
        }

        $escalated = 0;
        foreach ($steps as $step) {
            try {
                $new = $engine->escalateOverdueStep($step);
                if ($new) $escalated++;
            } catch (\Throwable $e) {
                $this->error("Schritt #{$step->id}: {$e->getMessage()}");
            }
        }

        $this->info("{$steps->count()} ueberfaellige Aufgaben verarbeitet, {$escalated} eskaliert.");
        return self::SUCCESS;
    }
}
