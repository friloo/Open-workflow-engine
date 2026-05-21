<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\User;
use App\Models\WorkflowStepExecution;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Erzeugt einen iCalendar-Feed (RFC 5545) für einen User:
 * - alle offenen Aufgaben mit Fälligkeit als VEVENT
 * - alle Verträge wo der User Owner ist: Frist + Ende als VEVENT
 *
 * Wird unter /ical/{token}.ics ausgeliefert; in Outlook/Apple Calendar
 * als Internet-Kalender einlesen, dann werden die Fälligkeiten dort
 * automatisch sichtbar.
 */
class IcalFeedGenerator
{
    public function generate(User $user): string
    {
        $appName = config('app.name', 'Open Workflow Engine');
        $appUrl = rtrim((string) config('app.url'), '/');
        $now = now();
        $stamp = $now->copy()->utc()->format('Ymd\THis\Z');

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//OWE//Inbox Feed//DE',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:'.self::escape($appName.' — '.$user->name),
            'X-WR-TIMEZONE:Europe/Berlin',
        ];

        // 1) Offene Aufgaben mit Fälligkeit
        $roleIds = $user->roles->pluck('id');
        $tasks = WorkflowStepExecution::query()
            ->with(['instance.workflow'])
            ->whereNull('completed_at')
            ->whereNotNull('due_at')
            ->where(function ($q) use ($user, $roleIds) {
                $q->where('assigned_to_user_id', $user->id);
                if ($roleIds->isNotEmpty()) $q->orWhereIn('assigned_to_role_id', $roleIds);
            })
            ->get();

        foreach ($tasks as $step) {
            $due = $step->due_at;
            if (! $due) continue;
            $wfName = $step->instance->workflow?->name ?? 'Workflow';
            $summary = '[OWE] Aufgabe: '.$wfName;
            $desc = "Workflow: {$wfName}\nVorgang: #{$step->workflow_instance_id}\n"
                . ($step->step_key ? "Knoten: {$step->step_key}\n" : '')
                . "Direkt-Link: {$appUrl}/tasks/{$step->id}";
            $lines = array_merge($lines, $this->event(
                uid: "task-{$step->id}@owe",
                stamp: $stamp,
                start: $due,
                end: $due->copy()->addMinutes(30),
                summary: $summary,
                description: $desc,
                url: $appUrl.'/tasks/'.$step->id,
                category: 'TASK',
            ));
        }

        // 2) Verträge wo der User Owner ist — Kündigungs-Frist + Ende
        if (Schema::hasTable('contracts')) {
            $contracts = Contract::where('owner_user_id', $user->id)
                ->whereNotNull('end_date')
                ->get();
            foreach ($contracts as $c) {
                $deadline = $c->noticeDeadline();
                if ($deadline) {
                    $lines = array_merge($lines, $this->allDayEvent(
                        uid: "contract-notice-{$c->id}@owe",
                        stamp: $stamp,
                        date: $deadline,
                        summary: '[OWE] Frist: '.$c->name,
                        description: 'Kündigungsfrist für Vertrag '.$c->name
                            . ($c->party ? ' ('.$c->party.')' : '')
                            . "\nDirekt-Link: {$appUrl}/contracts/{$c->id}",
                        url: $appUrl.'/contracts/'.$c->id,
                        category: 'CONTRACT',
                    ));
                }
                if ($c->end_date) {
                    $lines = array_merge($lines, $this->allDayEvent(
                        uid: "contract-end-{$c->id}@owe",
                        stamp: $stamp,
                        date: $c->end_date,
                        summary: '[OWE] Vertragsende: '.$c->name,
                        description: 'Vertragsende '.$c->name
                            . ($c->party ? ' ('.$c->party.')' : '')
                            . "\nDirekt-Link: {$appUrl}/contracts/{$c->id}",
                        url: $appUrl.'/contracts/'.$c->id,
                        category: 'CONTRACT',
                    ));
                }
            }
        }

        $lines[] = 'END:VCALENDAR';
        // RFC 5545 verlangt CRLF-Zeilenenden
        return implode("\r\n", $lines)."\r\n";
    }

    private function event(string $uid, string $stamp, Carbon $start, Carbon $end, string $summary, string $description, string $url, string $category): array
    {
        return [
            'BEGIN:VEVENT',
            'UID:'.$uid,
            'DTSTAMP:'.$stamp,
            'DTSTART:'.$start->copy()->utc()->format('Ymd\THis\Z'),
            'DTEND:'.$end->copy()->utc()->format('Ymd\THis\Z'),
            'SUMMARY:'.self::escape($summary),
            'DESCRIPTION:'.self::escape($description),
            'URL:'.$url,
            'CATEGORIES:'.$category,
            'END:VEVENT',
        ];
    }

    private function allDayEvent(string $uid, string $stamp, Carbon $date, string $summary, string $description, string $url, string $category): array
    {
        $d = $date->format('Ymd');
        $dEnd = $date->copy()->addDay()->format('Ymd');
        return [
            'BEGIN:VEVENT',
            'UID:'.$uid,
            'DTSTAMP:'.$stamp,
            'DTSTART;VALUE=DATE:'.$d,
            'DTEND;VALUE=DATE:'.$dEnd,
            'SUMMARY:'.self::escape($summary),
            'DESCRIPTION:'.self::escape($description),
            'URL:'.$url,
            'CATEGORIES:'.$category,
            'END:VEVENT',
        ];
    }

    /**
     * RFC 5545 text escape: backslash, comma, semicolon, newline.
     */
    private static function escape(string $text): string
    {
        return str_replace(
            ["\\",   "\n",  ",",  ";"],
            ['\\\\', '\\n', '\\,', '\\;'],
            $text,
        );
    }
}
