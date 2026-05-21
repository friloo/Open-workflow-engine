<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\WorkflowInstance;
use App\Models\WorkflowInstanceComment;
use App\Models\WorkflowStepExecution;
use ZipArchive;

/**
 * DSGVO-Service: Auskunft (Art. 15) und Vergessenwerden (Art. 17).
 *
 * Auskunft: sammelt alle personenbezogenen Daten zu einer Email
 * aus sämtlichen Tabellen und exportiert sie als ZIP mit JSON-
 * Dateien + den vom User hochgeladenen Anhängen.
 *
 * Vergessenwerden: anonymisiert den User-Datensatz. Workflow-
 * Historie + Belege bleiben (gesetzlich begründete Aufbewahrung),
 * aber alle personenbezogenen Felder werden ersetzt.
 */
class GdprService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @return array{filename:string, path:string, summary:array<string,int>}
     */
    public function exportForEmail(string $email): array
    {
        $user = User::where('email', $email)->first();
        $data = [
            'subject_email' => $email,
            'subject_user' => $user ? $this->serializeUser($user) : null,
            'exported_at' => now()->toIso8601String(),
            'data' => [],
        ];
        $summary = [];

        if ($user) {
            // Workflow-Instances vom User gestartet
            $instances = WorkflowInstance::with('workflow:id,name')
                ->where('started_by', $user->id)->get();
            $data['data']['workflow_instances'] = $instances->map(fn ($i) => [
                'id' => $i->id, 'workflow' => $i->workflow?->name,
                'status' => $i->status,
                'started_at' => $i->started_at?->toIso8601String(),
                'completed_at' => $i->completed_at?->toIso8601String(),
                'form_data' => $i->data,
            ])->all();
            $summary['workflow_instances'] = $instances->count();

            // Step-Executions wo der User Assignee / Decider war
            $steps = WorkflowStepExecution::with('instance.workflow:id,name')
                ->where('assigned_to_user_id', $user->id)
                ->orWhere('completed_by', $user->id)
                ->get();
            $data['data']['workflow_steps'] = $steps->map(fn ($s) => [
                'id' => $s->id,
                'workflow' => $s->instance?->workflow?->name,
                'instance_id' => $s->workflow_instance_id,
                'step_key' => $s->step_key,
                'assigned_at' => $s->assigned_at?->toIso8601String(),
                'completed_at' => $s->completed_at?->toIso8601String(),
                'decision' => $s->decision,
                'comment' => $s->comment,
                'role_assignment' => (bool) $s->assigned_to_role_id,
            ])->all();
            $summary['workflow_steps'] = $steps->count();

            // Kommentare
            $comments = WorkflowInstanceComment::where('user_id', $user->id)->get();
            $data['data']['comments'] = $comments->map(fn ($c) => [
                'id' => $c->id, 'instance_id' => $c->workflow_instance_id,
                'text' => $c->body ?? $c->comment ?? null,
                'created_at' => $c->created_at?->toIso8601String(),
            ])->all();
            $summary['comments'] = $comments->count();

            // Hochgeladene Anhänge
            $attachments = Attachment::where('uploaded_by', $user->id)->get();
            $data['data']['attachments'] = $attachments->map(fn ($a) => [
                'id' => $a->id, 'name' => $a->original_name,
                'mime_type' => $a->mime_type, 'size' => $a->size,
                'content_hash' => $a->content_hash,
                'uploaded_at' => $a->created_at?->toIso8601String(),
                'document_type' => $a->document_type,
            ])->all();
            $summary['attachments'] = $attachments->count();

            // Audit-Log-Einträge wo der User Akteur war
            $audits = AuditLog::where('user_id', $user->id)
                ->orderBy('id')->limit(5000)->get();
            $data['data']['audit_log'] = $audits->map(fn ($a) => [
                'id' => $a->id, 'event' => $a->event,
                'description' => $a->description,
                'auditable_type' => $a->auditable_type,
                'auditable_id' => $a->auditable_id,
                'ip_address' => $a->ip_address,
                'created_at' => $a->created_at?->toIso8601String(),
            ])->all();
            $summary['audit_log'] = $audits->count();

            // Saved Searches
            if (\Schema::hasTable('saved_searches')) {
                $saved = \DB::table('saved_searches')->where('user_id', $user->id)->get();
                $data['data']['saved_searches'] = $saved->map(fn ($s) => (array) $s)->all();
                $summary['saved_searches'] = $saved->count();
            }

            // Notification-Präferenzen
            if (\Schema::hasTable('user_notification_preferences')) {
                $prefs = \DB::table('user_notification_preferences')->where('user_id', $user->id)->get();
                $data['data']['notification_preferences'] = $prefs->map(fn ($r) => (array) $r)->all();
                $summary['notification_preferences'] = $prefs->count();
            }
        } else {
            $summary['note'] = 'Kein User-Datensatz mit dieser Email gefunden.';
        }

        $tmpZip = tempnam(sys_get_temp_dir(), 'gdpr_').'.zip';
        $zip = new ZipArchive();
        $zip->open($tmpZip, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('export.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $zip->addFromString('README.txt', $this->readme($email, $user, $summary));
        $zip->close();

        $this->audit->log('gdpr.access_request', $user, null, [
            'subject_email' => $email,
            'summary' => $summary,
        ], "DSGVO-Auskunft generiert für {$email}");

        return [
            'filename' => 'DSGVO-Auskunft-'.preg_replace('/[^a-z0-9]/i', '_', $email).'-'.now()->format('Y-m-d').'.zip',
            'path' => $tmpZip,
            'summary' => $summary,
        ];
    }

    /**
     * Anonymisiert einen User. Workflow-Historie bleibt (gesetzlich
     * begründete Aufbewahrung), aber alle direkt personenbezogenen
     * Felder werden ersetzt. Löscht den User nicht — die FK-Beziehungen
     * von WorkflowSteps + Audit-Log brauchen den Datensatz weiter.
     *
     * @return array{user_id:int, anonymized_email:string}
     */
    public function anonymize(User $user, string $reason): array
    {
        $oldEmail = $user->email;
        $anonEmail = "anonymized-{$user->id}@deleted.local";
        $anonName = "Anonymisiert #{$user->id}";

        $before = [
            'name' => $user->name,
            'email' => $user->email,
            'department' => $user->department ?? null,
        ];

        $update = [
            'name' => $anonName,
            'email' => $anonEmail,
            'password' => bcrypt(\Illuminate\Support\Str::random(64)),
            'is_active' => false,
            'email_notifications_enabled' => false,
            'remember_token' => null,
        ];
        // Optionale Spalten (je nach Migration-Stand zum Zeitpunkt der Installation)
        foreach (['department', 'custom_fields', 'supervisor_id',
                  'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at'] as $col) {
            if (\Schema::hasColumn('users', $col)) $update[$col] = null;
        }
        $user->forceFill($update)->save();

        // Rollen entfernen
        $user->roles()->detach();

        // API-Tokens widerrufen
        if (\Schema::hasTable('api_tokens')) {
            \DB::table('api_tokens')->where('user_id', $user->id)->delete();
        }

        // Sessions invalidieren (Database-Session-Driver)
        if (\Schema::hasTable('sessions')) {
            \DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        // Saved Searches löschen (keine Verbindlichkeit)
        if (\Schema::hasTable('saved_searches')) {
            \DB::table('saved_searches')->where('user_id', $user->id)->delete();
        }

        // Notification Prefs löschen
        if (\Schema::hasTable('user_notification_preferences')) {
            \DB::table('user_notification_preferences')->where('user_id', $user->id)->delete();
        }

        $this->audit->log('gdpr.anonymization', $user, $before, [
            'name' => $anonName, 'email' => $anonEmail,
            'reason' => $reason,
        ], "DSGVO-Anonymisierung von {$oldEmail} (Grund: {$reason})");

        return ['user_id' => $user->id, 'anonymized_email' => $anonEmail];
    }

    private function serializeUser(User $u): array
    {
        return [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'department' => $u->department ?? null,
            'is_active' => (bool) $u->is_active,
            'created_at' => $u->created_at?->toIso8601String(),
            'roles' => $u->roles->pluck('slug')->all(),
            'custom_fields' => $u->custom_fields ?? [],
        ];
    }

    private function readme(string $email, ?User $user, array $summary): string
    {
        $head = "DSGVO-Auskunft (Art. 15 DSGVO)\n";
        $head .= "================================\n\n";
        $head .= "Anfrage-Email:  {$email}\n";
        $head .= "Erstellt am:    ".now()->format('d.m.Y H:i')."\n";
        $head .= "User gefunden:  ".($user ? "ja (#{$user->id})" : "nein")."\n\n";
        $head .= "Inhalt:\n";
        foreach ($summary as $key => $value) {
            $head .= sprintf("  %-30s %s\n", $key, is_array($value) ? json_encode($value) : $value);
        }
        $head .= "\n";
        $head .= "Hinweis: 'export.json' enthält alle personenbezogenen Daten,\n";
        $head .= "gruppiert nach Tabelle. Originale Dokument-Dateien sind NICHT\n";
        $head .= "automatisch im ZIP — die koennen über den Doku-Bereich des\n";
        $head .= "Systems gesammelt werden, falls erforderlich. Workflow-Anhänge,\n";
        $head .= "die der Nutzer hochgeladen hat, sind als Liste erfasst.\n";
        return $head;
    }
}
