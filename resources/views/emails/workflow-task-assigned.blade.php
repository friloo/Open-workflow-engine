@php
    $nodeLabel = data_get($instance->version->definition, "drawflow.Home.data.{$step->step_key}.data.label", 'Aufgabe');
@endphp
<x-mail::message-layout>
    <p>Hallo {{ $recipient->name }},</p>
    <p>du hast eine neue Workflow-Aufgabe:</p>
    <table cellpadding="6" cellspacing="0" border="0" style="font-size:13px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;margin:12px 0;">
        <tr><td style="color:#64748b;width:120px;">Workflow</td><td><strong>{{ $workflow->name }}</strong></td></tr>
        <tr><td style="color:#64748b;">Schritt</td><td>{{ $nodeLabel }}</td></tr>
        <tr><td style="color:#64748b;">Antragsteller</td><td>{{ $instance->starter?->name ?? '—' }}</td></tr>
        @if($step->due_at)
        <tr><td style="color:#64748b;">Frist</td><td>{{ $step->due_at->format('d.m.Y H:i') }}</td></tr>
        @endif
    </table>
    <p style="margin:20px 0;">
        <a href="{{ $approveUrl }}" style="display:inline-block;background:#059669;color:white;text-decoration:none;padding:10px 18px;border-radius:8px;font-weight:600;margin-right:6px;">Genehmigen</a>
        <a href="{{ $rejectUrl }}" style="display:inline-block;background:#e11d48;color:white;text-decoration:none;padding:10px 18px;border-radius:8px;font-weight:600;">Ablehnen</a>
    </p>
    <p style="color:#64748b;font-size:13px;">Der Link führt auf eine Bestätigungs-Seite — die Entscheidung wird erst nach dem Klick auf „Bestätigen" gespeichert. Kein Login nötig.</p>
    <p style="margin:14px 0;">
        <a href="{{ $taskUrl }}" style="color:#6366f1;text-decoration:underline;font-size:13px;">Aufgabe in OWE öffnen (mit Login, für Weiterleitung)</a>
    </p>
    <p style="color:#64748b;font-size:12px;">Wenn du nicht reagierst, wird die Aufgabe nach Ablauf der Frist automatisch eskaliert.</p>
</x-mail::message-layout>
