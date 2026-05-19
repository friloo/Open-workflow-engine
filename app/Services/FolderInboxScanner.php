<?php

namespace App\Services;

use App\Models\FolderInbox;
use App\Models\Workflow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

/**
 * Scannt einen lokalen Ordner (z. B. Scanner-Folder vom Drucker) auf
 * neue Dateien, archiviert sie als Attachments (mit Doku-Typ-Schema
 * + OCR) und startet optional einen Workflow je Datei.
 *
 * Nach erfolgreicher Verarbeitung: Datei loeschen ODER in Unterordner
 * verschieben (per Konfiguration).
 */
class FolderInboxScanner
{
    public function __construct(
        private readonly AttachmentStorage $storage,
        private readonly WorkflowEngine $engine,
        private readonly AuditLogger $audit,
    ) {}

    /** @return array{found:int, imported:int, failed:int} */
    public function scan(FolderInbox $inbox): array
    {
        $stats = ['found' => 0, 'imported' => 0, 'failed' => 0];
        $dir = $inbox->absolutePath();
        if (! is_dir($dir)) {
            $inbox->forceFill([
                'last_scan_at' => now(),
                'last_status' => 'FEHLER',
                'last_error' => "Ordner nicht gefunden: {$dir}",
            ])->save();
            throw new \RuntimeException("Folder-Inbox-Pfad existiert nicht: {$dir}");
        }
        if (! is_readable($dir)) {
            $inbox->forceFill([
                'last_scan_at' => now(),
                'last_status' => 'FEHLER',
                'last_error' => "Ordner nicht lesbar: {$dir}",
            ])->save();
            throw new \RuntimeException("Folder-Inbox-Pfad nicht lesbar: {$dir}");
        }

        $allowedExt = $inbox->extensions
            ?: ['pdf', 'png', 'jpg', 'jpeg', 'webp', 'heic', 'heif'];
        $allowedExt = array_map('strtolower', $allowedExt);

        $processedDir = null;
        if ($inbox->after_import === 'move') {
            $processedDir = $dir.DIRECTORY_SEPARATOR.($inbox->processed_subfolder ?: 'verarbeitet');
            if (! is_dir($processedDir)) @mkdir($processedDir, 0775, true);
        }

        try {
            foreach (scandir($dir) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..') continue;
                $full = $dir.DIRECTORY_SEPARATOR.$entry;
                if (! is_file($full)) continue;
                $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
                if (! in_array($ext, $allowedExt, true)) continue;
                $stats['found']++;
                try {
                    $this->importOne($inbox, $full, $entry, $processedDir);
                    $stats['imported']++;
                } catch (\Throwable $e) {
                    Log::warning('Folder-Inbox-Import fehlgeschlagen', [
                        'inbox' => $inbox->id, 'file' => $entry, 'error' => $e->getMessage(),
                    ]);
                    $stats['failed']++;
                }
            }

            $inbox->forceFill([
                'last_scan_at' => now(),
                'last_status' => sprintf('OK · gefunden=%d, importiert=%d, fehler=%d',
                    $stats['found'], $stats['imported'], $stats['failed']),
                'last_error' => null,
            ])->save();
        } catch (\Throwable $e) {
            $inbox->forceFill([
                'last_scan_at' => now(),
                'last_status' => 'FEHLER',
                'last_error' => substr($e->getMessage(), 0, 1000),
            ])->save();
            throw $e;
        }

        return $stats;
    }

    private function importOne(FolderInbox $inbox, string $fullPath, string $name, ?string $processedDir): void
    {
        $bytes = (string) file_get_contents($fullPath);
        if ($bytes === '') {
            throw new \RuntimeException("Leere Datei: {$name}");
        }
        $mime = mime_content_type($fullPath) ?: 'application/octet-stream';

        $instance = null;
        if ($inbox->workflow_id) {
            $workflow = $inbox->workflow()->with('currentVersion')->first();
            if ($workflow && $workflow->status === Workflow::STATUS_ACTIVE) {
                $instance = $this->engine->start($workflow, [
                    'source' => 'folder_inbox',
                    'source_inbox_id' => $inbox->id,
                    'source_filename' => $name,
                ], null);
            }
        }

        $att = $this->storage->storeBytes(
            $bytes, $name, $mime,
            $instance ?: null,
            null, $inbox->created_by, $inbox->document_type,
        );

        if ($instance) {
            // Wenn Workflow gestartet, doc.* im Kontext setzen
            $data = $instance->data ?? [];
            $data['doc_attachment_id'] = $att->id;
            $data['doc_original_name'] = $att->original_name;
            $data['doc_document_type'] = $att->document_type;
            // Indexed Fields uebernehmen, falls schon vorhanden
            foreach ((array) ($att->indexed_fields ?? []) as $k => $v) {
                if (! array_key_exists($k, $data)) $data[$k] = $v;
            }
            $instance->update(['data' => $data]);
        }

        $this->audit->log('folder_inbox.imported', $att, null, [
            'inbox' => $inbox->name, 'file' => $name,
            'workflow_instance_id' => $instance?->id,
        ], "Datei {$name} aus Folder {$inbox->name} importiert");

        // Aufraeumen
        if ($inbox->after_import === 'move' && $processedDir) {
            $target = $processedDir.DIRECTORY_SEPARATOR.date('Y-m-d_His').'_'.$name;
            @rename($fullPath, $target);
        } else {
            @unlink($fullPath);
        }
    }
}
