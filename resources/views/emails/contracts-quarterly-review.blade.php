<!doctype html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; color: #1f2937;">
<h2>Quartals-Prüfung Verträge</h2>
<p>Hallo {{ $owner->name }},</p>
<p>du bist als <strong>Verantwortliche/r</strong> für {{ $contracts->count() }} Verträge eingetragen.
Bitte sichten — Stand: {{ $date->format('d.m.Y') }}.</p>

<table cellpadding="6" cellspacing="0" border="1" style="border-collapse: collapse; width: 100%; font-size: 13px;">
    <thead style="background: #f3f4f6;">
        <tr>
            <th align="left">Vertrag</th>
            <th align="left">Partner</th>
            <th align="left">Art</th>
            <th align="left">Status</th>
            <th align="left">Ende</th>
            <th align="left">Frist erreicht</th>
        </tr>
    </thead>
    <tbody>
        @foreach($contracts as $c)
            <tr>
                <td><a href="{{ url('/contracts/'.$c->id) }}">{{ $c->name }}</a></td>
                <td>{{ $c->party }}</td>
                <td>{{ $c->type?->name }}</td>
                <td>
                    @switch($c->status)
                        @case('active')<span style="color:#065f46;">aktiv</span>@break
                        @case('notice_due')<span style="color:#92400e;">Frist erreicht</span>@break
                        @case('expired')<span style="color:#991b1b;">abgelaufen</span>@break
                    @endswitch
                </td>
                <td>{{ $c->end_date?->format('d.m.Y') ?: '—' }}</td>
                <td>{{ $c->noticeDeadline()?->format('d.m.Y') ?: '—' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<p style="margin-top: 16px;">
    <a href="{{ url('/contracts') }}" style="background: #4f46e5; color: white; padding: 8px 14px; text-decoration: none; border-radius: 6px;">Alle Verträge öffnen</a>
</p>

<p style="margin-top: 24px; font-size: 12px; color: #6b7280;">
    Diese Mail ist eine Quartals-Selbstkontrolle und kommt automatisch zum
    Jahres-Anfang sowie zum 1. April / Juli / Oktober.
    Du kannst sie unter Profil → Benachrichtigungen deaktivieren.
</p>
</body>
</html>
