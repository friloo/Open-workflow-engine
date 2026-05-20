<?php

namespace App\Exceptions;

use App\Models\Attachment;

/**
 * Wird beim Upload geworfen wenn der Inhalt (Hash) bereits in der
 * Datenbank existiert. Tragt das Original-Attachment mit, damit der
 * Caller dem User einen Hinweis und einen Link anzeigen kann.
 */
class DuplicateAttachmentException extends \RuntimeException
{
    public function __construct(public readonly Attachment $original, string $message = '')
    {
        parent::__construct($message !== '' ? $message : "Datei bereits hochgeladen am "
            .$original->created_at->format('d.m.Y H:i')
            .(($original->uploader?->name) ? ' von '.$original->uploader->name : '')
            .'.');
    }
}
