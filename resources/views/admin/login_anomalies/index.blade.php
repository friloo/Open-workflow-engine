<x-app-layout>
    <x-slot name="header">Login-Anomalien</x-slot>
    <x-slot name="subheader">
        Übersicht über fehlgeschlagene Logins, ungewöhnliche IPs und blockierte Accounts der letzten {{ $data['window_hours'] }} Stunden.
    </x-slot>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <x-stat-card label="Erfolgreich" :value="$data['ok_24h']" tone="emerald" />
        <x-stat-card label="Fehlgeschlagen" :value="$data['failed_24h']" tone="amber" />
        <x-stat-card label="Blockiert (inaktiv)" :value="$data['blocked_24h']" tone="rose" />
        <x-stat-card label="Neue IPs (verdächtig)" :value="count($data['suspicious_users'])" tone="indigo" />
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <x-card title="Top-Emails mit fehlgeschlagenem Login" description="Wenn ein Account mehrfach probiert wird, taucht er hier auf.">
            @if(empty($data['top_failed_emails']))
                <p class="text-sm text-slate-500">Keine fehlgeschlagenen Logins.</p>
            @else
                <ul class="divide-y divide-slate-100">
                    @foreach($data['top_failed_emails'] as $row)
                        <li class="py-2 flex items-center justify-between">
                            <span class="font-mono text-sm text-slate-700">{{ $row['email'] ?: '—' }}</span>
                            <span class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">{{ $row['count'] }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        <x-card title="Top-IPs mit fehlgeschlagenem Login" description="Auffällige Quellen — kein Block, aber ein Hinweis fürs Firewall-Regelwerk.">
            @if(empty($data['top_failed_ips']))
                <p class="text-sm text-slate-500">Keine fehlgeschlagenen Logins.</p>
            @else
                <ul class="divide-y divide-slate-100">
                    @foreach($data['top_failed_ips'] as $row)
                        <li class="py-2 flex items-center justify-between">
                            <span class="font-mono text-sm text-slate-700">{{ $row['ip'] ?: '—' }}</span>
                            <span class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">{{ $row['count'] }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>

    <x-card title="Verdächtige neue IPs"
            description="User hat sich erfolgreich eingeloggt — die Source-IP wurde in den letzten 30 Tagen für diesen User aber noch nie gesehen.">
        @if(empty($data['suspicious_users']))
            <p class="text-sm text-slate-500">Keine neuen IPs in diesem Zeitfenster.</p>
        @else
            <ul class="divide-y divide-slate-100">
                @foreach($data['suspicious_users'] as $row)
                    <li class="py-2 flex items-center gap-3 text-sm">
                        <span class="inline-flex items-center rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700">neu</span>
                        <span class="font-medium text-slate-900">{{ $row['name'] }}</span>
                        <span class="font-mono text-xs text-slate-500">{{ $row['ip'] }}</span>
                        <span class="ms-auto text-xs text-slate-500">
                            <x-fmt-date :value="$row['at']" format="d.m.Y H:i" />
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>

    <x-card title="Letzte fehlgeschlagene Logins">
        @if(empty($data['recent_failures']))
            <p class="text-sm text-slate-500">Keine.</p>
        @else
            <div class="overflow-x-auto -mx-4 sm:mx-0">
                <table class="min-w-full text-sm">
                    <thead><tr class="text-left text-xs uppercase text-slate-500">
                        <th class="py-2 pr-4">Zeit</th>
                        <th class="py-2 pr-4">Email</th>
                        <th class="py-2 pr-4">IP</th>
                        <th class="py-2 pr-4">User-Agent</th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($data['recent_failures'] as $row)
                            <tr>
                                <td class="py-2 pr-4 text-xs text-slate-500"><x-fmt-date :value="$row['at']" format="d.m.Y H:i:s" /></td>
                                <td class="py-2 pr-4 font-mono text-xs">{{ $row['email'] }}</td>
                                <td class="py-2 pr-4 font-mono text-xs text-slate-600">{{ $row['ip'] }}</td>
                                <td class="py-2 pr-4 text-xs text-slate-500">{{ \Illuminate\Support\Str::limit($row['ua'] ?? '', 80) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
</x-app-layout>
