<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\Mailbox;
use App\Models\MailboxMessage;
use App\Models\WorkflowInstance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Webklex\IMAP\Facades\Client;
use Webklex\PHPIMAP\Client as ImapClient;
use Webklex\PHPIMAP\Message;

/**
 * Holt Mails per IMAP, legt Anhaenge revisionssicher ab und startet
 * optional einen Workflow. Per Postfach konfigurierbar.
 */
class MailboxFetcher
{
    public function __construct(
        private readonly AttachmentStorage $storage,
        private readonly WorkflowEngine $engine,
        private readonly AuditLogger $audit,
    ) {}

    /** Liefert die Zahl der UNGELESENEN Nachrichten — nur fuer den Verbindungstest. */
    public function testConnection(Mailbox $mailbox): int
    {
        $client = $this->client($mailbox);
        $client->connect();
        try {
            $folder = $client->getFolderByPath($mailbox->folder);
            if (! $folder) {
                throw new \RuntimeException("Ordner '{$mailbox->folder}' nicht gefunden.");
            }
            $unseen = $folder->messages()->unseen()->get();
            return $unseen->count();
        } finally {
            $client->disconnect();
        }
    }

    /**
     * Holt alle ungelesenen Mails, verarbeitet sie und verschiebt sie ggf.
     *
     * @return array{fetched:int, processed:int, skipped:int, failed:int}
     */
    public function fetch(Mailbox $mailbox): array
    {
        $stats = ['fetched' => 0, 'processed' => 0, 'skipped' => 0, 'failed' => 0];

        $client = $this->client($mailbox);
        try {
            $client->connect();
            $folder = $client->getFolderByPath($mailbox->folder);
            if (! $folder) {
                throw new \RuntimeException("Ordner '{$mailbox->folder}' nicht gefunden.");
            }

            $processedFolder = null;
            if ($mailbox->move_processed && $mailbox->processed_folder) {
                $processedFolder = $client->getFolderByPath($mailbox->processed_folder)
                    ?: $client->createFolder($mailbox->processed_folder);
            }

            $messages = $folder->messages()->unseen()->setFetchBody(true)->get();
            $stats['fetched'] = $messages->count();

            foreach ($messages as $message) {
                try {
                    $result = $this->processMessage($mailbox, $message);
                    $stats[$result]++;
                    $message->setFlag('Seen');
                    if ($processedFolder && $result === 'processed') {
                        $message->move($processedFolder->path);
                    }
                } catch (\Throwable $e) {
                    $stats['failed']++;
                    Log::warning('Mailbox-Verarbeitung fehlgeschlagen', [
                        'mailbox_id' => $mailbox->id,
                        'uid' => $message->getUid(),
                        'error' => $e->getMessage(),
                    ]);
                    MailboxMessage::create([
                        'mailbox_id' => $mailbox->id,
                        'uid' => (string) $message->getUid(),
                        'message_id' => (string) $message->getMessageId(),
                        'from_email' => $this->fromEmail($message),
                        'from_name' => $this->fromName($message),
                        'subject' => (string) $message->getSubject(),
                        'received_at' => $message->getDate()?->toDate(),
                        'status' => 'failed',
                        'error' => substr($e->getMessage(), 0, 1000),
                    ]);
                }
            }

            $mailbox->forceFill([
                'last_fetch_at' => now(),
                'last_status' => sprintf('OK · %d/%d/%d/%d', $stats['fetched'], $stats['processed'], $stats['skipped'], $stats['failed']),
                'last_error' => null,
            ])->save();
        } catch (\Throwable $e) {
            $mailbox->forceFill([
                'last_fetch_at' => now(),
                'last_status' => 'FEHLER',
                'last_error' => substr($e->getMessage(), 0, 1000),
            ])->save();
            if ($mailbox->created_by) {
                \App\Models\AppNotification::send(
                    \App\Models\User::find($mailbox->created_by),
                    'mailbox.error',
                    "Postfach {$mailbox->name}: Fehler",
                    substr($e->getMessage(), 0, 200),
                    route('admin.mailboxes.edit', $mailbox),
                );
            }
            throw $e;
        } finally {
            try { $client->disconnect(); } catch (\Throwable) {}
        }

        return $stats;
    }

    /** @return 'processed'|'skipped' */
    private function processMessage(Mailbox $mailbox, Message $message): string
    {
        $uid = (string) $message->getUid();
        $existing = MailboxMessage::where('mailbox_id', $mailbox->id)->where('uid', $uid)->first();
        if ($existing) {
            return 'skipped';
        }

        $fromEmail = $this->fromEmail($message);
        $fromName = $this->fromName($message);
        $subject = (string) $message->getSubject();
        $body = $message->hasTextBody() ? (string) $message->getTextBody() : strip_tags((string) $message->getHTMLBody());

        return DB::transaction(function () use ($mailbox, $message, $uid, $fromEmail, $fromName, $subject, $body) {
            $log = MailboxMessage::create([
                'mailbox_id' => $mailbox->id,
                'uid' => $uid,
                'message_id' => (string) $message->getMessageId(),
                'from_email' => $fromEmail,
                'from_name' => $fromName,
                'subject' => $subject,
                'received_at' => $message->getDate()?->toDate(),
                'attachment_count' => 0,
                'status' => 'processed',
            ]);

            $instance = null;
            if ($mailbox->workflow_id) {
                $instance = $this->startWorkflow($mailbox, $subject, $body, $fromEmail, $fromName);
                if ($instance) {
                    $log->workflow_instance_id = $instance->id;
                }
            }

            $attachable = $instance ?: null;
            $attCount = 0;

            // Erst alle Bytes sammeln, dann XRechnung/ZUGFeRD-XML einem
            // PDF-Anhang derselben Mail zuordnen.
            $files = [];
            foreach ($message->getAttachments() as $att) {
                $bytes = $att->getContent();
                if (! is_string($bytes) || $bytes === '') continue;
                $files[] = [
                    'name' => $att->getName() ?: 'anhang-'.(count($files) + 1),
                    'mime' => $att->getMimeType() ?: 'application/octet-stream',
                    'bytes' => $bytes,
                ];
            }

            // ZUGFeRD-XML separat erkennen?
            $zugferdXml = null;
            foreach ($files as $f) {
                $isXml = str_contains(strtolower($f['mime']), 'xml')
                    || str_ends_with(strtolower($f['name']), '.xml');
                if (! $isXml) continue;
                $lower = strtolower($f['name']);
                $looksLikeInvoice = str_contains($lower, 'factur-x')
                    || str_contains($lower, 'zugferd')
                    || str_contains($lower, 'xrechnung')
                    || str_contains($lower, 'invoice')
                    || str_contains($lower, 'rechnung');
                if (! $looksLikeInvoice) {
                    // Heuristik: trotzdem versuchen zu parsen — wenn ein CII/UBL-Root rauskommt, zaehlt's.
                    if (! str_contains($f['bytes'], 'CrossIndustryInvoice') && ! str_contains($f['bytes'], 'UBLDocument') && ! str_contains($f['bytes'], '<Invoice')) {
                        continue;
                    }
                }
                $parsed = app(\App\Services\ZugferdParser::class)->parseXmlBytes($f['bytes']);
                if ($parsed) {
                    $zugferdXml = ['file' => $f, 'data' => $parsed];
                    break;
                }
            }

            foreach ($files as $f) {
                // XML, das wir als ZUGFeRD-Partner identifiziert haben, NICHT
                // separat ablegen — die Daten gehen aufs PDF.
                if ($zugferdXml && $f['name'] === $zugferdXml['file']['name']) continue;

                try {
                    $stored = $this->storage->storeBytes(
                        $f['bytes'], $f['name'], $f['mime'], $attachable, $subject, null, $mailbox->document_type,
                    );
                    // Wenn dies das PDF ist und wir ZUGFeRD-Daten haben:
                    // an indexed_fields._zugferd kleben (Viewer und Workflow
                    // sehen die Daten dann ohne neuen PDF-Parse).
                    if ($zugferdXml && str_contains(strtolower($f['mime']), 'pdf')) {
                        $fields = (array) ($stored->indexed_fields ?? []);
                        $fields['_zugferd'] = $zugferdXml['data'];
                        // Standard-ZUGFeRD-Felder als first-class indexed_fields anheften,
                        // damit Workflow-Bedingungen sie direkt sehen.
                        foreach ($zugferdXml['data'] as $k => $v) {
                            if (! array_key_exists($k, $fields)) $fields[$k] = $v;
                        }
                        $stored->forceFill(['indexed_fields' => $fields, 'indexed_at' => now()])->save();
                    }
                    $attCount++;
                } catch (\Throwable $e) {
                    Log::info('Mail-Anhang verworfen', ['name' => $f['name'], 'mime' => $f['mime'], 'reason' => $e->getMessage()]);
                }
            }

            $log->attachment_count = $attCount;
            $log->save();

            $this->audit->log('mailbox.message.received', $log, null, [
                'mailbox' => $mailbox->name,
                'from' => $fromEmail,
                'subject' => $subject,
                'attachments' => $attCount,
                'workflow_instance_id' => $instance?->id,
            ], "Mail von {$fromEmail}: {$subject}");

            return 'processed';
        });
    }

    private function startWorkflow(Mailbox $mailbox, string $subject, string $body, ?string $fromEmail, ?string $fromName): ?WorkflowInstance
    {
        $workflow = $mailbox->workflow()->with('currentVersion')->first();
        if (! $workflow) return null;

        $form = [];
        if ($mailbox->subject_field) $form[$mailbox->subject_field] = $subject;
        if ($mailbox->body_field) $form[$mailbox->body_field] = substr($body, 0, 8000);
        if ($mailbox->from_field) $form[$mailbox->from_field] = $fromEmail;
        $form['mail_from_name'] = $fromName;
        $form['mail_received_at'] = now()->toIso8601String();

        try {
            return $this->engine->start($workflow, $form, null);
        } catch (\Throwable $e) {
            Log::warning('Mail->Workflow-Start fehlgeschlagen', [
                'mailbox_id' => $mailbox->id, 'workflow_id' => $workflow->id, 'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function fromEmail(Message $message): ?string
    {
        $from = $message->getFrom();
        $first = is_iterable($from) ? collect($from)->first() : null;
        return $first?->mail ? (string) $first->mail : null;
    }

    private function fromName(Message $message): ?string
    {
        $from = $message->getFrom();
        $first = is_iterable($from) ? collect($from)->first() : null;
        return $first?->personal ? (string) $first->personal : null;
    }

    private function client(Mailbox $mailbox): ImapClient
    {
        return Client::make([
            'host' => $mailbox->host,
            'port' => $mailbox->port,
            'encryption' => $mailbox->encryption === 'none' ? false : $mailbox->encryption,
            'validate_cert' => (bool) $mailbox->validate_cert,
            'username' => $mailbox->username,
            'password' => (string) $mailbox->password,
            'protocol' => 'imap',
            'authentication' => null,
        ]);
    }
}
