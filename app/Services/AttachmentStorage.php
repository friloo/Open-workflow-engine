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

    public function store(UploadedFile $file, Model $attachable, ?string $label, ?int $userId): Attachment
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

        $dir = 'attachments/'.now()->format('Y/m');
        $name = Str::ulid().'.'.Str::lower($file->getClientOriginalExtension() ?: 'bin');
        $path = $file->storeAs($dir, $name, 'local');
        if (! $path) {
            throw new \RuntimeException('Datei konnte nicht gespeichert werden.');
        }

        return Attachment::create([
            'attachable_type' => $attachable->getMorphClass(),
            'attachable_id' => $attachable->getKey(),
            'original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
            'disk' => 'local',
            'path' => $path,
            'mime_type' => $mime,
            'size' => $file->getSize(),
            'content_hash' => $hash,
            'label' => $label,
            'uploaded_by' => $userId,
        ]);
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
