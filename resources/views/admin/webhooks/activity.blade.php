<x-app-layout>
    <x-slot name="header">Webhook-Aktivität: {{ $webhook->name }}</x-slot>
    <x-slot name="subheader">
        Letzte 100 Auslieferungen an <code class="text-xs">{{ $webhook->url }}</code>
    </x-slot>

    <x-breadcrumbs :items="[
        ['title' => 'Webhooks', 'url' => route('admin.webhooks.index')],
        ['title' => $webhook->name],
    ]" />

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <x-stat-card label="Gesamt" :value="$stats['total']" />
        <x-stat-card label="Erfolgreich" :value="$stats['ok']" tone="emerald" />
        <x-stat-card label="Fehlgeschlagen" :value="$stats['failed']" tone="rose" />
        <x-stat-card label="Ø Antwortzeit"
            :value="$stats['avg_ms'] ? round($stats['avg_ms']).' ms' : '—'" tone="indigo" />
    </div>

    <x-card>
        @if($deliveries->isEmpty())
            <p class="text-sm text-slate-500">Noch keine Auslieferungen für diesen Webhook.</p>
        @else
            <div class="overflow-x-auto -mx-4 sm:mx-0">
                <table class="min-w-full text-sm">
                    <thead><tr class="text-left text-xs uppercase text-slate-500">
                        <th class="py-2 pr-4">Zeit</th>
                        <th class="py-2 pr-4">Event</th>
                        <th class="py-2 pr-4">Status</th>
                        <th class="py-2 pr-4 text-right">HTTP</th>
                        <th class="py-2 pr-4 text-right">Dauer</th>
                        <th class="py-2 pr-4">Antwort / Fehler</th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($deliveries as $d)
                            <tr>
                                <td class="py-2 pr-4 text-xs text-slate-500 whitespace-nowrap">
                                    <x-fmt-date :value="$d->sent_at" format="d.m.Y H:i:s" />
                                </td>
                                <td class="py-2 pr-4">
                                    <code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs">{{ $d->event }}</code>
                                </td>
                                <td class="py-2 pr-4">
                                    @if($d->ok)
                                        <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">✓ OK</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-rose-50 px-2 py-0.5 text-xs font-medium text-rose-700">✗ Fehler</span>
                                    @endif
                                </td>
                                <td class="py-2 pr-4 text-right font-mono text-xs text-slate-700">{{ $d->response_code ?? '—' }}</td>
                                <td class="py-2 pr-4 text-right text-xs text-slate-600 tabular-nums">{{ $d->duration_ms }} ms</td>
                                <td class="py-2 pr-4 text-xs text-slate-500 max-w-md truncate" title="{{ $d->error ?? $d->response_excerpt }}">
                                    {{ \Illuminate\Support\Str::limit($d->error ?? $d->response_excerpt ?? '', 80) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
</x-app-layout>
