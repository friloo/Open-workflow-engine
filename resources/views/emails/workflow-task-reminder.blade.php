@php
    $nodeLabel = data_get($instance->version->definition, "drawflow.Home.data.{$step->step_key}.data.label", 'Aufgabe');
@endphp
<x-mail::message-layout>
    <p>Hallo {{ $recipient->name }},</p>
    <p>du hast seit <strong>{{ $daysOpen }} Tag(en)</strong> eine offene Workflow-Aufgabe, auf die noch keine Reaktion erfolgt ist:</p>
    <table cellpadding="6" cellspacing="0" border="0" style="font-size:13px;background:#fffbeb;border:1px solid #fde68a;border-radius:8px;margin:12px 0;">
        <tr><td style="color:#92400e;width:120px;">Workflow</td><td><strong>{{ $workflow->name }}</strong></td></tr>
        <tr><td style="color:#92400e;">Schritt</td><td>{{ $nodeLabel }}</td></tr>
        <tr><td style="color:#92400e;">Antragsteller</td><td>{{ $instance->starter?->name ?? '—' }}</td></tr>
        @if($step->due_at)
        <tr><td style="color:#92400e;">Frist</td><td>{{ $step->due_at->format('d.m.Y H:i') }}</td></tr>
        @endif
    </table>
    <p style="margin:20px 0;">
        <a href="{{ $approveUrl }}" style="display:inline-block;background:#059669;color:white;text-decoration:none;padding:10px 18px;border-radius:8px;font-weight:600;margin-right:6px;">Genehmigen</a>
        <a href="{{ $rejectUrl }}" style="display:inline-block;background:#e11d48;color:white;text-decoration:none;padding:10px 18px;border-radius:8px;font-weight:600;">Ablehnen</a>
    </p>
    <p style="color:#64748b;font-size:13px;">Klick auf einen der Buttons leitet auf eine Bestaetigungs-Seite — die Entscheidung wird erst nach dem Klick auf „Bestaetigen" gespeichert.</p>
    <p style="margin:14px 0;">
        <a href="{{ $taskUrl }}" style="color:#6366f1;text-decoration:underline;font-size:13px;">Aufgabe in OWE oeffnen (mit Login)</a>
    </p>
    <p style="color:#94a3b8;font-size:12px;">Diese Mail kommt automatisch wenn eine Aufgabe laenger offen ist. Sobald du entscheidest, gibt's keine weiteren Erinnerungen.</p>
</x-mail::message-layout>
