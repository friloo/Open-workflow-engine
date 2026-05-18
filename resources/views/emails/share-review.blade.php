<x-mail::message-layout>
    <p>Hallo {{ $share->creator?->name ?? '' }},</p>
    <p>du hast die folgende Datei per Sharing-Link freigegeben:</p>

    <table cellpadding="6" cellspacing="0" border="0" style="font-size:13px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;margin:12px 0;">
        <tr><td style="color:#64748b;width:160px;">Datei</td><td><strong>{{ $attachment?->original_name ?? '—' }}</strong></td></tr>
        @if($share->note)
        <tr><td style="color:#64748b;">Notiz</td><td>{{ $share->note }}</td></tr>
        @endif
        @if($share->expires_at)
        <tr><td style="color:#64748b;">Laeuft ab</td><td>{{ $share->expires_at->format('d.m.Y H:i') }}</td></tr>
        @endif
        <tr><td style="color:#64748b;">Zugriffe</td><td>{{ $share->download_count }}@if($share->max_downloads) von max. {{ $share->max_downloads }}@endif</td></tr>
    </table>

    <p>Bitte bestaetige, ob die Freigabe weiter aktiv bleiben soll. Wenn du
    bis <strong>{{ $autoRevokeAt->format('d.m.Y H:i') }}</strong> nicht reagierst,
    wird die Freigabe automatisch widerrufen.</p>

    <p style="margin:24px 0;">
        <a href="{{ $confirmUrl }}" style="display:inline-block;background:#10b981;color:white;text-decoration:none;padding:10px 18px;border-radius:8px;font-weight:600;margin-right:6px;">Freigabe behalten</a>
        <a href="{{ $revokeUrl }}" style="display:inline-block;background:#f43f5e;color:white;text-decoration:none;padding:10px 18px;border-radius:8px;font-weight:600;">Jetzt widerrufen</a>
    </p>

    <p style="color:#64748b;font-size:12px;">Beim Bestaetigen wirst du gebeten, einen Grund anzugeben (z. B. „laufende Pruefung mit Anwalt").</p>
</x-mail::message-layout>
