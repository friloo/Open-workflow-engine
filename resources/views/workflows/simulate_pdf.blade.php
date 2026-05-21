<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Pre-Production-Check · {{ $workflow->name }}</title>
<style>
    @page { margin: 18mm 16mm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 9.5pt; color: #1f2937; line-height: 1.5; }
    h1 { font-size: 18pt; color: #0f172a; margin: 0 0 4mm 0; }
    h2 { font-size: 11pt; color: #1e293b; margin: 8mm 0 3mm 0; padding-bottom: 1mm; border-bottom: 1px solid #cbd5e1; }
    .brand { font-size: 9pt; letter-spacing: 0.18em; text-transform: uppercase; color: #4f46e5; }
    .header-meta { font-size: 8.5pt; color: #64748b; }
    .header-meta td { padding: 1px 12px 1px 0; }
    .status-ok {
        display: inline-block; padding: 3mm 8mm; margin: 4mm 0;
        background: #d1fae5; color: #065f46;
        border: 2px solid #065f46; border-radius: 4px;
        font-size: 11pt; font-weight: 700; letter-spacing: 0.05em;
    }
    .status-error {
        display: inline-block; padding: 3mm 8mm; margin: 4mm 0;
        background: #fee2e2; color: #991b1b;
        border: 2px solid #991b1b; border-radius: 4px;
        font-size: 11pt; font-weight: 700; letter-spacing: 0.05em;
    }
    table.kv { width: 100%; border-collapse: collapse; font-size: 9pt; }
    table.kv td { padding: 3px 6px; border: 1px solid #e2e8f0; vertical-align: top; }
    table.kv td:first-child { background: #f8fafc; font-weight: 600; width: 35%; color: #475569; }
    table.trace { width: 100%; border-collapse: collapse; font-size: 8.5pt; margin-top: 2mm; }
    table.trace th { background: #f1f5f9; color: #334155; text-align: left; padding: 4px 6px; border-bottom: 1.5px solid #94a3b8; font-size: 8pt; }
    table.trace td { padding: 4px 6px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
    .pill {
        display: inline-block; padding: 1px 6px; border-radius: 8px;
        font-size: 7.5pt; font-weight: 600; letter-spacing: 0.02em;
    }
    .pill-start { background: #d1fae5; color: #065f46; }
    .pill-end { background: #e2e8f0; color: #475569; }
    .pill-condition { background: #fef3c7; color: #92400e; }
    .pill-approval { background: #e0e7ff; color: #3730a3; }
    .pill-notify { background: #cffafe; color: #155e75; }
    .pill-http { background: #ede9fe; color: #6b21a8; }
    .pill-pdf_render { background: #fee2e2; color: #991b1b; }
    .pill-default { background: #e2e8f0; color: #475569; }
    .note { font-size: 8pt; color: #64748b; font-style: italic; margin-top: 1px; }
    .footer { position: fixed; bottom: 6mm; left: 16mm; right: 16mm;
              font-size: 7pt; color: #94a3b8; text-align: center;
              border-top: 1px solid #e5e7eb; padding-top: 3px; }
    .empty { color: #94a3b8; font-style: italic; }
    code { font-family: DejaVu Sans Mono, monospace; font-size: 8.5pt; background: #f8fafc; padding: 0 3px; border-radius: 2px; }
</style>
</head>
<body>

<div class="brand">{{ config('branding.app_name', config('app.name', 'Open Workflow Engine')) }} · Pre-Production-Check</div>
<h1>Workflow-Simulation</h1>

<table class="header-meta">
    <tr><td><strong>Workflow:</strong></td><td>{{ $workflow->name }} (ID {{ $workflow->id }})</td></tr>
    <tr><td><strong>Version:</strong></td><td>{{ $version?->version ?? '—' }} @if($version) · gespeichert {{ $version->created_at->format('d.m.Y H:i') }}@endif</td></tr>
    <tr><td><strong>Erzeugt:</strong></td><td>{{ $generatedAt->format('d.m.Y H:i:s') }} von {{ $generatedBy }}</td></tr>
</table>

@if($error)
    <div class="status-error">✗ FEHLER: {{ $error }}</div>
@else
    <div class="status-ok">✓ SIMULATION ERFOLGREICH</div>
@endif

<h2>Eingabedaten (Testfall)</h2>
@if(empty($inputData))
    <p class="empty">Keine Eingabedaten — Trockenlauf mit leerem Formular.</p>
@else
    <table class="kv">
        @foreach($inputData as $k => $v)
            <tr>
                <td>{{ $k }}</td>
                <td>@if(is_array($v) || is_object($v)){{ json_encode($v, JSON_UNESCAPED_UNICODE) }}@else{{ $v }}@endif</td>
            </tr>
        @endforeach
    </table>
@endif

<h2>Ausführungs-Trace ({{ count($trace) }} Schritte)</h2>
@if(empty($trace))
    <p class="empty">Lauf hat keine Schritte produziert.</p>
@else
    <table class="trace">
        <thead>
            <tr>
                <th style="width: 10mm;">#</th>
                <th style="width: 26mm;">Typ</th>
                <th style="width: 40mm;">Knoten</th>
                <th>Aktion / Ergebnis</th>
            </tr>
        </thead>
        <tbody>
            @foreach($trace as $idx => $t)
                @php($cls = $t['class'] ?? 'default')
                @php($pillClass = in_array($cls, ['start','end','condition','approval','notify','http','pdf_render']) ? "pill-{$cls}" : 'pill-default')
                <tr>
                    <td>{{ $idx + 1 }}</td>
                    <td><span class="pill {{ $pillClass }}">{{ $cls }}</span></td>
                    <td>{{ $t['label'] ?? '—' }}</td>
                    <td>
                        {{ $t['action'] ?? $t['message'] ?? '' }}
                        @if(! empty($t['note']))<div class="note">{{ $t['note'] }}</div>@endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

<h2>Hinweise zum Pre-Production-Check</h2>
<p style="font-size: 8.5pt; color: #475569;">
Dieser Bericht dokumentiert den Trockenlauf des Workflows mit den oben angegebenen Testdaten.
In der Simulation werden keine Mails versendet, keine HTTP-/Webhook-Calls ausgeführt und
keine Datenbank-Schreiboperationen persistiert. Bei <code>approval</code>-Knoten nimmt die
Simulation automatisch den Genehmigt-Pfad — für Abgelehnt-Pfade muss der Fall über
Bedingungs-Knoten auf Testfeldern modelliert werden.
</p>

<div class="footer">
    {{ config('branding.app_name', config('app.name')) }} ·
    Pre-Production-Check {{ $generatedAt->format('d.m.Y H:i') }} ·
    Audit-Event: workflow.simulation_exported
</div>

</body>
</html>
