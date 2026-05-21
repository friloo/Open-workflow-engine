<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Berechtigungs-Report</title>
    <style>
        @page { margin: 18mm 15mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #1f2937; line-height: 1.4; }
        h1 { font-size: 16pt; margin: 0 0 4px 0; }
        h2 { font-size: 11pt; margin: 16px 0 6px 0; border-bottom: 1px solid #d1d5db; padding-bottom: 3px; }
        .meta { font-size: 8pt; color: #6b7280; }
        table { width: 100%; border-collapse: collapse; margin: 4px 0 10px 0; }
        th { background: #f3f4f6; text-align: left; padding: 4px 5px; font-size: 8pt; border: 1px solid #d1d5db; }
        td { padding: 4px 5px; border: 1px solid #d1d5db; font-size: 8pt; vertical-align: top; }
        .pill { display: inline-block; padding: 1px 5px; border-radius: 3px; font-size: 7pt; background: #e5e7eb; color: #374151; margin: 0 2px 2px 0; }
        .role-pill { background: #e0e7ff; color: #3730a3; }
        .group { color: #6b7280; font-size: 7pt; text-transform: uppercase; letter-spacing: 0.04em; margin-top: 4px; }
        .footer { position: fixed; bottom: 5mm; left: 15mm; right: 15mm; font-size: 7pt; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 3px; }
    </style>
</head>
<body>
    <h1>Berechtigungs-Report</h1>
    <p class="meta">
        Generiert am {{ $generatedAt->format('d.m.Y H:i:s') }}
        von {{ $generator }} ·
        {{ $users->count() }} Benutzer · {{ $roles->count() }} Rollen · {{ $totalPermissions }} Permissions
    </p>

    <h2>1. Benutzer-Liste mit zugewiesenen Rollen</h2>
    <table>
        <thead>
            <tr>
                <th style="width:32%">Benutzer</th>
                <th style="width:48%">Rollen</th>
                <th style="width:20%">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($users as $u)
                <tr>
                    <td>
                        <strong>{{ $u->name }}</strong><br>
                        <span class="meta">{{ $u->email }}</span>
                    </td>
                    <td>
                        @forelse($u->roles as $r)
                            <span class="pill role-pill">{{ $r->name }}</span>
                        @empty
                            <span class="meta">— keine —</span>
                        @endforelse
                    </td>
                    <td>
                        @if($u->is_service_account)Service-Account
                        @elseif(! $u->is_active)inaktiv
                        @else aktiv
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>2. Rollen mit Permissions</h2>
    @foreach($roles as $r)
        <p style="margin: 8px 0 2px 0;">
            <strong>{{ $r->name }}</strong>
            <span class="meta">({{ $r->slug }}) · {{ $r->users_count }} User · {{ $r->permissions->count() }} Permissions</span>
        </p>
        @php($byGroup = $r->permissions->groupBy(fn ($p) => $p->group ?: 'Sonstige'))
        @forelse($byGroup as $group => $perms)
            <div class="group">{{ $group }}</div>
            @foreach($perms as $p)
                <span class="pill" title="{{ $p->slug }}">{{ $p->name }}</span>
            @endforeach
        @empty
            <p class="meta">Keine Permissions.</p>
        @endforelse
    @endforeach

    <h2>3. Vollstaendige Matrix User x Rolle x Permission</h2>
    <table>
        <thead>
            <tr>
                <th style="width:25%">User</th>
                <th style="width:20%">Rolle</th>
                <th style="width:15%">Gruppe</th>
                <th style="width:40%">Permission</th>
            </tr>
        </thead>
        <tbody>
            @foreach($users as $u)
                @forelse($u->roles as $r)
                    @php($byGroup = $r->permissions->groupBy(fn ($p) => $p->group ?: 'Sonstige'))
                    @forelse($byGroup as $group => $perms)
                        @foreach($perms as $p)
                            <tr>
                                <td>{{ $u->name }}</td>
                                <td>{{ $r->name }}</td>
                                <td>{{ $group }}</td>
                                <td>{{ $p->slug }} — {{ $p->name }}</td>
                            </tr>
                        @endforeach
                    @empty
                        <tr><td>{{ $u->name }}</td><td>{{ $r->name }}</td><td colspan="2" class="meta">— Rolle hat keine Permissions —</td></tr>
                    @endforelse
                @empty
                    <tr><td>{{ $u->name }}</td><td colspan="3" class="meta">— keine Rolle —</td></tr>
                @endforelse
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Open Workflow Engine · Berechtigungs-Report · Generiert {{ $generatedAt->format('d.m.Y H:i') }} ·
        Audit-Event: report.permissions.exported (PDF)
    </div>
</body>
</html>
