<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Audit-Log · Export</title>
<style>
    @page { margin: 14mm 12mm 20mm 12mm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 8.5pt; color: #1f2937; line-height: 1.35; }
    h1 { font-size: 15pt; color: #0f172a; margin: 0 0 4px 0; }
    .meta { font-size: 8pt; color: #6b7280; margin-bottom: 8px; }
    table { width: 100%; border-collapse: collapse; }
    th { background: #1e293b; color: #f8fafc; padding: 4px 6px; font-size: 7.5pt; text-align: left; }
    td { padding: 3px 6px; border-bottom: 1px solid #e5e7eb; font-size: 7.5pt; vertical-align: top; }
    tr:nth-child(even) td { background: #fafbfc; }
    code { font-family: monospace; font-size: 7pt; color: #475569; }
    .pill { padding: 1px 4px; background: #e0e7ff; color: #3730a3; border-radius: 2px; font-size: 7pt; }
    .footer { position: fixed; bottom: 6mm; left: 12mm; right: 12mm;
              font-size: 7pt; color: #94a3b8; border-top: 1px solid #e5e7eb; padding-top: 3px;
              display: table; width: calc(100% - 24mm); }
    .footer .r { display: table-cell; text-align: right; }
    .footer .l { display: table-cell; }
    .page-num:before { content: counter(page); }
</style>
</head>
<body>
<h1>Audit-Log · Export</h1>
<p class="meta">
    Zeitraum: <strong>{{ $from->format('d.m.Y H:i') }}</strong> bis
    <strong>{{ $to->format('d.m.Y H:i') }}</strong>
    @if($event) · Event-Filter: <code>{{ $event }}*</code>@endif
    · {{ $count }} Einträge · Generiert {{ $generatedAt->format('d.m.Y H:i') }} von {{ $generator }}
</p>

<table>
    <thead>
        <tr>
            <th style="width:3%">#</th>
            <th style="width:11%">Zeitpunkt</th>
            <th style="width:14%">Event</th>
            <th style="width:12%">User</th>
            <th style="width:8%">Subjekt</th>
            <th style="width:38%">Beschreibung</th>
            <th style="width:14%">Hash</th>
        </tr>
    </thead>
    <tbody>
        @foreach($entries as $e)
            <tr>
                <td>{{ $e->id }}</td>
                <td>{{ $e->created_at?->format('d.m.Y H:i:s') }}</td>
                <td><span class="pill">{{ $e->event }}</span></td>
                <td>{{ $e->user?->name ?? '—' }}</td>
                <td>
                    @if($e->auditable_type)
                        <code>{{ class_basename($e->auditable_type) }}#{{ $e->auditable_id }}</code>
                    @else
                        <code style="color:#94a3b8">—</code>
                    @endif
                </td>
                <td>{{ \Illuminate\Support\Str::limit($e->description, 180) }}</td>
                <td><code>{{ substr($e->hash, 0, 10) }}…</code></td>
            </tr>
        @endforeach
    </tbody>
</table>

<div class="footer">
    <span class="l">{{ config('branding.app_name', config('app.name')) }} · Audit-Export · {{ $count }} Einträge</span>
    <span class="r">Seite <span class="page-num"></span></span>
</div>
</body>
</html>
