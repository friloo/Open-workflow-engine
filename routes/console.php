<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Karenzzeit-Eskalation: pruefe alle 5 Minuten ueberfaellige Workflow-Schritte.
Schedule::command('workflow:check-due')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();
