<x-mail::message-layout>
    <p>Hallo {{ $share->creator?->name ?? '' }},</p>
    <p>die Freigabe für das folgende Dokument wurde automatisch widerrufen,
    weil du auf die letzte Prüfungs-Anfrage nicht reagiert hast:</p>

    <table cellpadding="6" cellspacing="0" border="0" style="font-size:13px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;margin:12px 0;">
        <tr><td style="color:#64748b;width:160px;">Datei</td><td><strong>{{ $attachment?->original_name ?? '—' }}</strong></td></tr>
        @if($share->note)
        <tr><td style="color:#64748b;">Notiz</td><td>{{ $share->note }}</td></tr>
        @endif
    </table>

    <p>Der Link funktioniert ab sofort nicht mehr. Du kannst bei Bedarf
    eine neue Freigabe anlegen.</p>
</x-mail::message-layout>
