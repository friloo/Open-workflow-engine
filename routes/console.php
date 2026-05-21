<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Health-Sentinel: bei jedem Scheduler-Lauf den Zeitstempel ablegen, damit
// die Health-Ansicht erkennt, ob der Cron noch läuft.
Schedule::call(function () {
    @file_put_contents(storage_path('framework/schedule-last-run'), (string) time());
})->everyMinute()->name('owe.schedule-sentinel');

// Karenzzeit-Eskalation: prüfe alle 5 Minuten überfällige Workflow-Schritte.
Schedule::command('workflow:check-due')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Wiederkehrende Workflows: stündlich fällige Schedules starten.
Schedule::command('workflow:run-schedules')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();

// Verträge: Status-Sync + Kündigungs-Reminder für Verantwortliche.
Schedule::command('contracts:check-deadlines')
    ->dailyAt('06:30')
    ->withoutOverlapping()
    ->runInBackground();

// Verträge: Quartals-Review (1. Jan, 1. Apr, 1. Jul, 1. Okt).
Schedule::command('contracts:quarterly-review')
    ->cron('0 8 1 1,4,7,10 *')
    ->withoutOverlapping()
    ->runInBackground();

// Asset-Wiedervorlage: täglich fällige Assets in Workflows ausspielen.
Schedule::command('asset:check-due')
    ->dailyAt('06:00')
    ->withoutOverlapping()
    ->runInBackground();

// OCR für Anhänge, die noch nicht extrahiert wurden (best-effort, nachts).
Schedule::command('ocr:run-pending')
    ->dailyAt('02:30')
    ->withoutOverlapping()
    ->runInBackground();

// Freigabe-Links: täglich Prüfungs-Mails versenden und überfällige
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

// Dokumenten-Aufbewahrung: täglich Regeln pro Dokumenttyp anwenden.
Schedule::command('documents:retention-check')
    ->dailyAt('03:15')
    ->withoutOverlapping()
    ->runInBackground();

// IMAP-Postfächer: alle 5 Minuten auf neue Mails prüfen.
Schedule::command('mail:fetch')
    ->everyFiveMinutes()
    ->withoutOverlapping(10)
    ->runInBackground();

// Folder-Inboxen (Scanner-Ordner, lokale Importe): alle 5 Minuten scannen.
Schedule::command('folder:scan')
    ->everyFiveMinutes()
    ->withoutOverlapping(10)
    ->runInBackground();

// Reminder für offene Aufgaben: täglich 09:00.
Schedule::command('tasks:remind')
    ->dailyAt('09:00')
    ->withoutOverlapping()
    ->runInBackground();

// Tagessicherung: jede Nacht 01:30. Retention via Settings.
Schedule::command('backup:run')
    ->dailyAt('01:30')
    ->withoutOverlapping(120)
    ->runInBackground();

// Update-Check: einmal täglich prüfen, Admins per Glocke informieren
// wenn eine neue Version vorliegt (pro Soll-SHA nur einmal).
Schedule::command('update:notify-available')
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->runInBackground();
