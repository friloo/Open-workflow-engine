<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Audit-Zertifikat · Hash-Chain-Pruefung</title>
<style>
    @page { margin: 20mm 18mm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #1f2937; line-height: 1.5; }
    .cover { text-align: center; padding-top: 30mm; }
    .brand { font-size: 11pt; letter-spacing: 0.18em; text-transform: uppercase; color: #4f46e5; margin-bottom: 10mm; }
    h1 { font-size: 26pt; color: #0f172a; margin: 0 0 8mm 0; }
    .status-ok {
        display: inline-block; padding: 8mm 18mm; margin-top: 6mm;
        background: #d1fae5; color: #065f46;
        border: 3px solid #065f46; border-radius: 6px;
        font-size: 18pt; font-weight: 700; letter-spacing: 0.05em;
    }
    .status-broken {
        display: inline-block; padding: 8mm 18mm; margin-top: 6mm;
        background: #fee2e2; color: #991b1b;
        border: 3px solid #991b1b; border-radius: 6px;
        font-size: 18pt; font-weight: 700; letter-spacing: 0.05em;
    }
    .summary {
        margin: 16mm auto 0; padding: 8mm 12mm; max-width: 130mm;
        border: 1px solid #d1d5db; border-radius: 6px;
        background: #f8fafc; text-align: left; line-height: 1.8; font-size: 9.5pt;
    }
    .summary strong { color: #0f172a; }
    .meta { margin-top: 12mm; font-size: 8.5pt; color: #6b7280; }
    .footer { position: fixed; bottom: 8mm; left: 18mm; right: 18mm;
              font-size: 7pt; color: #94a3b8; text-align: center;
              border-top: 1px solid #e5e7eb; padding-top: 4px; }
    table.broken-detail { margin: 8mm auto; border-collapse: collapse; width: 90%; font-size: 9pt; }
    table.broken-detail td { padding: 5px 8px; border: 1px solid #d1d5db; }
    table.broken-detail td:first-child { background: #fef2f2; font-weight: 600; width: 30%; }
</style>
</head>
<body>
<div class="cover">
    <div class="brand">{{ config('branding.app_name', config('app.name', 'Open Workflow Engine')) }}</div>
    <h1>Audit-Zertifikat</h1>
    <p style="font-size: 13pt; color: #475569;">Hash-Chain-Verifikation</p>

    @if($broken === null)
        <div class="status-ok">✓ KETTE INTAKT</div>
        <div class="summary">
            <strong>Pruefungs-Datum:</strong> {{ $verifiedAt->format('d.m.Y H:i:s') }}<br>
            <strong>Geprueft durch:</strong> {{ $verifiedBy }}<br>
            <strong>Audit-Eintraege total:</strong> {{ number_format($total, 0, ',', '.') }}<br>
            @if($firstEntry)
                <strong>Erster Eintrag:</strong> {{ $firstEntry->created_at->format('d.m.Y H:i:s') }}
                (Event: <code>{{ $firstEntry->event }}</code>)<br>
                <strong>Letzter Eintrag:</strong> {{ $lastEntry->created_at->format('d.m.Y H:i:s') }}
                (Event: <code>{{ $lastEntry->event }}</code>)<br>
            @endif
            <strong>Algorithmus:</strong> SHA-256 (prev_hash → payload → hash)
        </div>
        <p class="meta">
            Jeder Audit-Eintrag wurde geprueft: stored_prev_hash + event +
            user_id + alte/neue Werte + Zeitstempel ergeben den
            gespeicherten Hash. Wuerde jemand einen Eintrag manipulieren,
            wuerde diese Pruefung das erkennen.
        </p>
    @else
        <div class="status-broken">✗ KETTE GEBROCHEN</div>
        <table class="broken-detail">
            <tr><td>Bruch bei Eintrag-ID</td><td><code>{{ $broken['broken_at_id'] }}</code></td></tr>
            <tr><td>Erwarteter prev_hash</td><td><code>{{ \Illuminate\Support\Str::limit($broken['expected_prev'] ?? '—', 60) }}</code></td></tr>
            <tr><td>Gespeicherter prev_hash</td><td><code>{{ \Illuminate\Support\Str::limit($broken['stored_prev'] ?? '—', 60) }}</code></td></tr>
            <tr><td>Erwarteter hash</td><td><code>{{ \Illuminate\Support\Str::limit($broken['expected_hash'] ?? '—', 60) }}</code></td></tr>
            <tr><td>Gespeicherter hash</td><td><code>{{ \Illuminate\Support\Str::limit($broken['stored_hash'] ?? '—', 60) }}</code></td></tr>
        </table>
        <p class="meta" style="color:#991b1b; max-width: 140mm; margin: 6mm auto;">
            <strong>Achtung:</strong> Die Audit-Kette ist nicht mehr konsistent. Mögliche Ursachen:
            direkter Datenbank-Zugriff, Migration mit Datenverlust, oder bewusste Manipulation.
            Sofort untersuchen. Letzten Backup pruefen.
        </p>
    @endif
</div>

<div class="footer">
    {{ config('branding.app_name', config('app.name')) }} ·
    Audit-Zertifikat erzeugt {{ $verifiedAt->format('d.m.Y H:i') }} ·
    Audit-Event: audit.chain_verified
</div>
</body>
</html>
