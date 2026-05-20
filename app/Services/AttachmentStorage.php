<?php

namespace App\Services;

use App\Models\Attachment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AttachmentStorage
{
    /** Allowed MIME prefixes / exact types. */
    public const ALLOWED = [
        'application/pdf',
        'image/png', 'image/jpeg', 'image/webp', 'image/heic', 'image/heif',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain', 'text/csv',
    ];
    public const MAX_BYTES = 15 * 1024 * 1024; // 15 MB

    public function __construct(
        private readonly OcrExtractor $ocr,
        private readonly FieldExtractor $fields,
    ) {}

    /**
     * Sucht ein bestehendes Attachment mit demselben SHA-256-Inhalt.
     * Optional: eine version_chain ausschliessen (z. B. wenn explizit
     * eine neue Version derselben Datei hochgeladen wird).
     */
    /**
     * Welcher Disk fuer neue Anhaenge benutzt wird. Konfiguriert ueber
     * config/filesystems.php 'attachments_disk' bzw. ATTACHMENTS_DISK.
     * Default: 'local'. Wer S3 / MinIO / Wasabi nutzen will, setzt
     * ATTACHMENTS_DISK=s3.
     *
     * Bestehende Attachments behalten ihre 'disk'-Spalte — sie werden
     * vom alten Disk gelesen, neue landen auf dem neuen. So ist der
     * Wechsel ohne sofortige Migration moeglich; alte Dateien laesst
     * man entweder liegen oder migriert sie via
     *   php artisan attachments:migrate-disk <ziel-disk>
     */
    public function disk(): string
    {
        return config('filesystems.attachments_disk', 'local');
    }

    public function findDuplicate(string $hash, ?string $excludeChainId = null): ?Attachment
    {
        $q = Attachment::query()
            ->where('content_hash', $hash)
            ->orderBy('id'); // aeltestes zuerst — das Original
        if ($excludeChainId) $q->where('version_chain_id', '!=', $excludeChainId);
        return $q->with('uploader')->first();
    }

    public function store(UploadedFile $file, ?Model $attachable, ?string $label, ?int $userId, ?string $documentType = null, ?Attachment $newVersionOf = null, bool $allowDuplicate = false): Attachment
    {
        if ($file->getSize() > self::MAX_BYTES) {
            throw new \RuntimeException('Datei zu gross (max. 15 MB).');
        }
        $mime = $file->getMimeType() ?: 'application/octet-stream';
        if (! $this->mimeAllowed($mime)) {
            throw new \RuntimeException("Dateityp nicht erlaubt ({$mime}).");
        }

        // Hash vor Speicherung berechnen (revisionssicher).
        $hash = hash_file('sha256', $file->getRealPath());
        if ($hash === false) {
            throw new \RuntimeException('Hash der Datei konnte nicht berechnet werden.');
        }

        // Duplikat-Pruefung: gleicher Hash bereits irgendwo? Bei einer neuen
        // Version derselben Datei (gleiche Chain) erlauben wir es bewusst.
        if (! $allowDuplicate) {
            $dup = $this->findDuplicate($hash, $newVersionOf?->version_chain_id);
            if ($dup) {
                throw new \App\Exceptions\DuplicateAttachmentException($dup);
            }
        }

        $dir = 'attachments/'.now()->format('Y/m');
        $name = Str::ulid().'.'.Str::lower($file->getClientOriginalExtension() ?: 'bin');
        $disk = $this->disk();
        $path = $file->storeAs($dir, $name, $disk);
        if (! $path) {
            throw new \RuntimeException('Datei konnte nicht gespeichert werden.');
        }

        // Versionierung: neues Dokument startet eine Chain, neue Version
        // haengt sich in eine bestehende Chain ein.
        if ($newVersionOf) {
            $chainId = $newVersionOf->version_chain_id;
            $versionNumber = ($newVersionOf->versions()->max('version_number') ?? 0) + 1;
            // Bisherige current-Markierung in der Chain entfernen
            Attachment::where('version_chain_id', $chainId)->update(['is_current_version' => false]);
        } else {
            $chainId = (string) Str::uuid();
            $versionNumber = 1;
        }

        $att = Attachment::create([
            'attachable_type' => $attachable?->getMorphClass(),
            'attachable_id' => $attachable?->getKey(),
            'original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
            'disk' => $disk,
            'path' => $path,
            'mime_type' => $mime,
            'size' => $file->getSize(),
            'content_hash' => $hash,
            'label' => $label,
            'uploaded_by' => $userId,
            'document_type' => $documentType ?? $newVersionOf?->document_type,
            'ocr_status' => 'pending',
            'version_chain_id' => $chainId,
            'version_number' => $versionNumber,
            'is_current_version' => true,
        ]);

        $this->triggerOcrAndIndex($att);

        return $att;
    }

    /**
     * Pruefe Integritaet aller Attachments. Liefert Liste mit verdaechtigen
     * Eintraegen (Hash stimmt nicht oder Datei fehlt).
     *
     * @return array{checked:int, broken:array<int, array{id:int, name:string, reason:string}>}
     */
    public function verifyAll(?int $limit = null): array
    {
        $broken = [];
        $checked = 0;
        $q = Attachment::query()->orderBy('id');
        if ($limit) $q->limit($limit);
        foreach ($q->cursor() as $att) {
            $checked++;
            if (! $att->content_hash) {
                $broken[] = ['id' => $att->id, 'name' => $att->original_name, 'reason' => 'kein Hash hinterlegt'];
                continue;
            }
            if (! $att->verifyContent()) {
                $disk = \Illuminate\Support\Facades\Storage::disk($att->disk);
                $reason = $disk->exists($att->path) ? 'Hash stimmt nicht' : 'Datei fehlt';
                $broken[] = ['id' => $att->id, 'name' => $att->original_name, 'reason' => $reason];
            }
        }
        return ['checked' => $checked, 'broken' => $broken];
    }

    /**
     * Speichert rohen Byte-String als Attachment. Wird vom PDF-Render-Knoten
     * benutzt, der das PDF im Workflow erzeugt und revisionssicher anhaengt.
     */
    public function storeBytes(string $bytes, string $filename, string $mime, ?Model $attachable, ?string $label, ?int $userId, ?string $documentType = null, bool $allowDuplicate = false): Attachment
    {
        if (strlen($bytes) > self::MAX_BYTES) {
            throw new \RuntimeException('Datei zu gross (max. 15 MB).');
        }
        if (! $this->mimeAllowed($mime)) {
            throw new \RuntimeException("Dateityp nicht erlaubt ({$mime}).");
        }

        $hash = hash('sha256', $bytes);

        if (! $allowDuplicate) {
            $dup = $this->findDuplicate($hash);
            if ($dup) {
                throw new \App\Exceptions\DuplicateAttachmentException($dup);
            }
        }

        $disk = $this->disk();
        $ext = Str::lower(pathinfo($filename, PATHINFO_EXTENSION) ?: 'bin');
        $dir = 'attachments/'.now()->format('Y/m');
        $name = Str::ulid().'.'.$ext;
        $path = $dir.'/'.$name;
        if (! Storage::disk($disk)->put($path, $bytes)) {
            throw new \RuntimeException('Datei konnte nicht gespeichert werden.');
        }

        $chainId = (string) Str::uuid();

        $att = Attachment::create([
            'attachable_type' => $attachable?->getMorphClass(),
            'attachable_id' => $attachable?->getKey(),
            'original_name' => Str::limit($filename, 250, ''),
            'disk' => $disk,
            'path' => $path,
            'mime_type' => $mime,
            'size' => strlen($bytes),
            'content_hash' => $hash,
            'label' => $label,
            'uploaded_by' => $userId,
            'document_type' => $documentType,
            'ocr_status' => 'pending',
            'version_chain_id' => $chainId,
            'version_number' => 1,
            'is_current_version' => true,
        ]);

        $this->triggerOcrAndIndex($att);

        return $att;
    }

    /**
     * Triggert OCR + Feld-Indexierung. Default synchron (alter
     * Verhalten). Per Setting QUEUE_OCR=true wandert das in einen
     * Queue-Job — wer ein 'queue:work' am Laufen hat, bekommt sofortige
     * Uploads ohne OCR-Wartezeit.
     */
    private function triggerOcrAndIndex(Attachment $att): void
    {
        if ((bool) config('app.queue_ocr', false)) {
            \App\Jobs\ProcessAttachmentOcr::dispatch($att->id);
            return;
        }
        try { $this->ocr->extract($att); } catch (\Throwable) {}
        try { $this->fields->extractFor($att->refresh()); } catch (\Throwable) {}
    }

    public function streamDownload(Attachment $attachment)
    {
        $disk = Storage::disk($attachment->disk);
        if (! $disk->exists($attachment->path)) {
            abort(404, 'Datei nicht gefunden.');
        }
        return $disk->download($attachment->path, $attachment->original_name, [
            'Content-Type' => $attachment->mime_type ?: 'application/octet-stream',
        ]);
    }

    private function mimeAllowed(string $mime): bool
    {
        return in_array($mime, self::ALLOWED, true);
    }
}
