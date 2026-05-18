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
            'label' => $label,
            'uploaded_by' => $userId,
        ]);
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
