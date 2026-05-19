<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Health-Sentinel: bei jedem Scheduler-Lauf den Zeitstempel ablegen, damit
// die Health-Ansicht erkennt, ob der Cron noch laeuft.
Schedule::call(function () {
    @file_put_contents(storage_path('framework/schedule-last-run'), (string) time());
})->everyMinute()->name('owe.schedule-sentinel');

// Karenzzeit-Eskalation: pruefe alle 5 Minuten ueberfaellige Workflow-Schritte.
Schedule::command('workflow:check-due')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Wiederkehrende Workflows: stuendlich faellige Schedules starten.
Schedule::command('workflow:run-schedules')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();

// Asset-Wiedervorlage: taeglich faellige Assets in Workflows ausspielen.
Schedule::command('asset:check-due')
    ->dailyAt('06:00')
    ->withoutOverlapping()
    ->runInBackground();

// OCR fuer Anhaenge, die noch nicht extrahiert wurden (best-effort, nachts).
Schedule::command('ocr:run-pending')
    ->dailyAt('02:30')
    ->withoutOverlapping()
    ->runInBackground();

// Freigabe-Links: taeglich Pruefungs-Mails versenden und ueberfaellige
// Links automatisch widerrufen.
Schedule::command('shares:review')
    ->dailyAt('07:00')
    ->withoutOverlapping()
    ->runInBackground();

// DSGVO-Audit-Cleanup: monatlich IP/UA aelter als 2 Jahre anonymisieren.
Schedule::command('audit:cleanup --days=730')
    ->monthlyOn(1, '03:00')
    ->withoutOverlapping()
    ->runInBackground();

// Dokumenten-Aufbewahrung: taeglich Regeln pro Dokumenttyp anwenden.
Schedule::command('documents:retention-check')
    ->dailyAt('03:15')
    ->withoutOverlapping()
    ->runInBackground();

// IMAP-Postfaecher: alle 5 Minuten auf neue Mails pruefen.
Schedule::command('mail:fetch')
    ->everyFiveMinutes()
    ->withoutOverlapping(10)
    ->runInBackground();

// Folder-Inboxen (Scanner-Ordner, lokale Importe): alle 5 Minuten scannen.
Schedule::command('folder:scan')
    ->everyFiveMinutes()
    ->withoutOverlapping(10)
    ->runInBackground();

// Reminder fuer offene Aufgaben: taeglich 09:00.
Schedule::command('tasks:remind')
    ->dailyAt('09:00')
    ->withoutOverlapping()
    ->runInBackground();

// Tagessicherung: jede Nacht 01:30. Retention via Settings.
Schedule::command('backup:run')
    ->dailyAt('01:30')
    ->withoutOverlapping(120)
    ->runInBackground();

// Update-Check: einmal taeglich pruefen, Admins per Glocke informieren
// wenn eine neue Version vorliegt (pro Soll-SHA nur einmal).
Schedule::command('update:notify-available')
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->runInBackground();
