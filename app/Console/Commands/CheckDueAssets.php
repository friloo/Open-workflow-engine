<?php

namespace App\Console\Commands;

use App\Models\Asset;
use App\Services\WorkflowEngine;
use Illuminate\Console\Command;

class CheckDueAssets extends Command
{
    protected $signature = 'asset:check-due {--limit=200}';
    protected $description = 'Startet Workflows fuer Assets, deren Vorlauffrist erreicht ist (Fuehrerschein, Unterweisung etc.).';

    public function handle(WorkflowEngine $engine): int
    {
        $assets = Asset::with('user', 'workflow')
            ->where('status', Asset::STATUS_ACTIVE)
            ->whereNotNull('valid_until')
            ->whereNotNull('workflow_id')
            ->limit((int) $this->option('limit'))
            ->get()
            ->filter(fn (Asset $a) => $a->isDue());

        if ($assets->isEmpty()) {
            $this->info('Keine faelligen Assets.');
            return self::SUCCESS;
        }

        $started = 0;
        foreach ($assets as $asset) {
            $workflow = $asset->workflow;
            if (! $workflow || $workflow->status !== 'active' || ! $workflow->current_version_id) {
                $this->warn("Asset #{$asset->id}: Workflow nicht aktiv — uebersprungen.");
                continue;
            }
            $data = [
                'asset_id' => $asset->id,
                'asset_name' => $asset->name,
                'asset_type' => $asset->type,
                'asset_valid_until' => $asset->valid_until?->format('Y-m-d'),
                'subject_user_id' => $asset->user_id,
                'subject_user_email' => $asset->user?->email,
                'subject_user_name' => $asset->user?->name,
            ];

            try {
                $engine->start($workflow, $data, $asset->user);
                $asset->forceFill(['last_review_at' => now()])->save();
                $started++;
            } catch (\Throwable $e) {
                $this->error("Asset #{$asset->id}: {$e->getMessage()}");
            }
        }

        $this->info("{$started} Workflow(s) gestartet, {$assets->count()} faellige Assets verarbeitet.");
        return self::SUCCESS;
    }
}
