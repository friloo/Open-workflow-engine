<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Berechtigungs-Report · {{ config('branding.app_name', config('app.name')) }}</title>
    <style>
        @page { margin: 18mm 16mm 24mm 16mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9.5pt; color: #1f2937; line-height: 1.45; }

        /* Titelseite */
        .cover { page-break-after: always; padding-top: 40mm; text-align: center; }
        .cover .brand { font-size: 11pt; letter-spacing: 0.18em; text-transform: uppercase; color: #4f46e5; margin-bottom: 12mm; }
        .cover h1 { font-size: 28pt; color: #0f172a; margin: 0 0 6mm 0; font-weight: 700; }
        .cover .subtitle { font-size: 13pt; color: #475569; margin-bottom: 24mm; font-weight: 400; }
        .cover .summary {
            display: inline-block; padding: 8mm 14mm; border: 1px solid #d1d5db;
            border-radius: 6px; background: #f8fafc; text-align: left;
            color: #334155; font-size: 10pt; line-height: 1.8;
        }
        .cover .summary strong { color: #0f172a; }
        .cover .meta { margin-top: 18mm; font-size: 9pt; color: #6b7280; line-height: 1.7; }
        .cover .seal {
            margin-top: 14mm; display: inline-block; padding: 6mm 10mm;
            border: 2px solid #4f46e5; color: #4f46e5; border-radius: 4px;
            font-size: 9pt; font-weight: 700; letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        /* Inhaltsseiten */
        h2 {
            font-size: 13pt; color: #0f172a; margin: 18px 0 4px 0;
            padding-bottom: 4px; border-bottom: 2px solid #4f46e5;
        }
        h2 .num { color: #4f46e5; margin-right: 6px; }
        h3 {
            font-size: 10.5pt; color: #1e293b; margin: 14px 0 4px 0;
            padding: 4px 8px; background: #f1f5f9; border-left: 3px solid #4f46e5;
        }
        h3 .meta { font-weight: 400; font-size: 8.5pt; color: #64748b; float: right; }
        p { margin: 0 0 6px 0; }

        table { width: 100%; border-collapse: collapse; margin: 4px 0 10px 0; }
        th {
            background: #1e293b; color: #f8fafc; text-align: left;
            padding: 5px 7px; font-size: 8.5pt; font-weight: 600;
            letter-spacing: 0.03em;
        }
        td { padding: 5px 7px; border-bottom: 1px solid #e5e7eb; font-size: 9pt; vertical-align: top; }
        tr:nth-child(even) td { background: #fafbfc; }
        td.idx { color: #94a3b8; width: 22px; text-align: right; }

        .pill {
            display: inline-block; padding: 2px 7px; border-radius: 3px;
            font-size: 8pt; margin: 0 3px 3px 0;
            background: #e0e7ff; color: #3730a3;
        }
        .pill-grey { background: #e5e7eb; color: #374151; }
        .pill-amber { background: #fef3c7; color: #92400e; }
        .pill-rose  { background: #fee2e2; color: #991b1b; }
        .pill-green { background: #d1fae5; color: #065f46; }

        .group-label {
            color: #4f46e5; font-size: 7.5pt; text-transform: uppercase;
            letter-spacing: 0.06em; margin-top: 6px; font-weight: 700;
        }

        .footer {
            position: fixed; bottom: 8mm; left: 16mm; right: 16mm;
            font-size: 7pt; color: #94a3b8;
            border-top: 1px solid #e5e7eb; padding-top: 4px;
            display: table; width: calc(100% - 32mm);
        }
        .footer .l, .footer .r { display: table-cell; vertical-align: middle; }
        .footer .r { text-align: right; }
        .page-num:before { content: counter(page); }

        .hash-box {
            margin-top: 12mm; padding: 5mm 8mm; background: #f8fafc;
            border: 1px solid #e5e7eb; border-radius: 4px;
            font-family: monospace; font-size: 8pt; color: #475569;
            word-break: break-all;
        }
        .hash-box strong { color: #0f172a; font-family: DejaVu Sans, sans-serif; }
    </style>
</head>
<body>

{{-- ========= TITELSEITE ========= --}}
<div class="cover">
    <div class="brand">{{ config('branding.app_name', config('app.name', 'Open Workflow Engine')) }}</div>
    <h1>Berechtigungs-Report</h1>
    <p class="subtitle">Audit-Auszug: Benutzer, Rollen und Permissions</p>

    <div class="summary">
        <strong>Stand:</strong> {{ $generatedAt->format('d.m.Y H:i:s') }}<br>
        <strong>Generiert von:</strong> {{ $generator }}<br>
        <strong>Benutzer im System:</strong> {{ $users->count() }}<br>
        <strong>Definierte Rollen:</strong> {{ $roles->count() }}<br>
        <strong>Definierte Permissions:</strong> {{ $totalPermissions }}
    </div>

    <div class="seal">Vertraulich · Audit-Beleg</div>

    <p class="meta">
        Erstellt durch {{ config('branding.app_name', config('app.name')) }} ·
        Audit-Event: report.permissions.exported<br>
        Bei Rueckfragen: Administrator dieser Instanz
    </p>
</div>

{{-- ========= ABSCHNITT 1: USER → ROLLEN ========= --}}
<h2><span class="num">1</span> Benutzer und zugewiesene Rollen</h2>
<p style="color:#64748b; font-size:8.5pt; margin-bottom:8px;">
    Pro Benutzer alle aktuell zugewiesenen Rollen.
    Status-Spalte unterscheidet aktive, deaktivierte und Service-Konten.
</p>
<table>
    <thead>
        <tr>
            <th style="width:24px">#</th>
            <th style="width:30%">Benutzer</th>
            <th style="width:30%">E-Mail</th>
            <th style="width:30%">Rollen</th>
            <th style="width:10%">Status</th>
        </tr>
    </thead>
    <tbody>
        @foreach($users as $u)
            <tr>
                <td class="idx">{{ $loop->iteration }}</td>
                <td><strong>{{ $u->name }}</strong></td>
                <td>{{ $u->email }}</td>
                <td>
                    @forelse($u->roles as $r)
                        <span class="pill">{{ $r->name }}</span>
                    @empty
                        <span style="color:#94a3b8;">—</span>
                    @endforelse
                </td>
                <td>
                    @if($u->is_service_account)
                        <span class="pill pill-amber">Service</span>
                    @elseif(! $u->is_active)
                        <span class="pill pill-grey">inaktiv</span>
                    @else
                        <span class="pill pill-green">aktiv</span>
                    @endif
                </td>
            </tr>
        @endforeach
    </tbody>
</table>

{{-- ========= ABSCHNITT 2: ROLLEN → PERMISSIONS ========= --}}
<h2><span class="num">2</span> Rollen und ihre Permissions</h2>
<p style="color:#64748b; font-size:8.5pt; margin-bottom:8px;">
    Pro Rolle alle Berechtigungen, gruppiert nach Bereich. „User"-Zahl
    rechts oben gibt an, wie viele Benutzer aktuell diese Rolle haben.
</p>
@foreach($roles as $r)
    <h3>
        {{ $r->name }}
        <span class="meta">
            {{ $r->permissions->count() }} Permissions · {{ $r->users_count }} User · <code>{{ $r->slug }}</code>
        </span>
    </h3>
    @if($r->description)
        <p style="font-size:8.5pt; color:#64748b; margin-bottom:4px;">{{ $r->description }}</p>
    @endif
    @php($byGroup = $r->permissions->groupBy(fn ($p) => $p->group ?: 'Sonstige'))
    @forelse($byGroup as $group => $perms)
        <div class="group-label">{{ $group }}</div>
        <div style="margin-bottom:4px;">
            @foreach($perms as $p)
                <span class="pill pill-grey" title="{{ $p->slug }}">{{ $p->name }}</span>
            @endforeach
        </div>
    @empty
        <p style="font-size:8.5pt; color:#94a3b8; margin-left:4px;">— keine Permissions zugewiesen —</p>
    @endforelse
@endforeach

{{-- Audit-Trailer mit Hash --}}
<div class="hash-box">
    <strong>Audit-Trailer:</strong><br>
    Erzeugt: {{ $generatedAt->format('Y-m-d H:i:s') }} UTC · Generator: {{ $generator }}<br>
    SHA-256-Fingerprint (Daten): <code>{{ $dataHash ?? '—' }}</code><br>
    Audit-Event: <code>report.permissions.exported</code>
</div>

<div class="footer">
    <span class="l">
        {{ config('branding.app_name', config('app.name')) }} · Berechtigungs-Report
        · {{ $generatedAt->format('d.m.Y H:i') }}
    </span>
    <span class="r">Seite <span class="page-num"></span></span>
</div>

</body>
</html>
