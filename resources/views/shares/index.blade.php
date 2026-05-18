<x-app-layout>
    <x-slot name="header">{{ $isAdmin ? 'Alle Freigaben' : 'Meine Freigaben' }}</x-slot>
    <x-slot name="subheader">Pruefung alle {{ $reviewDays }} Tage per Mail. Max. Ablauf: {{ $maxDays }} Tage.</x-slot>

    <x-card>
        @if($shares->isEmpty())
            <x-empty-state icon="document"
                title="Noch keine Freigaben"
                description="Auf der Detail-Seite eines Dokuments findest du den Button zum Teilen.">
                <a href="{{ route('documents.index') }}"><x-secondary-button type="button">Zu Dokumenten</x-secondary-button></a>
            </x-empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead><tr class="text-left text-xs font-semibold uppercase text-slate-500">
                        <th class="py-2 pr-4">Dokument</th>
                        @if($isAdmin)<th class="py-2 pr-4">Geteilt von</th>@endif
                        <th class="py-2 pr-4">Status</th>
                        <th class="py-2 pr-4">Laeuft ab</th>
                        <th class="py-2 pr-4">Zugriffe</th>
                        <th class="py-2 pr-4">Naechste Pruefung</th>
                        <th class="py-2"></th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($shares as $s)
                            <tr>
                                <td class="py-3 pr-4">
                                    <div class="font-medium text-slate-900">{{ $s->attachment?->original_name ?? 'geloescht' }}</div>
                                    <div class="text-xs text-slate-500">{{ $s->note }}</div>
                                </td>
                                @if($isAdmin)<td class="py-3 pr-4 text-slate-700 text-xs">{{ $s->creator?->name ?? '—' }}</td>@endif
                                <td class="py-3 pr-4">
                                    @if($s->isActive())
                                        <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">aktiv</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">{{ $s->statusLabel() }}</span>
                                    @endif
                                    @if($s->password_hash)<span class="ms-1 inline-flex items-center rounded-md bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">PW</span>@endif
                                </td>
                                <td class="py-3 pr-4 text-xs text-slate-700">{{ $s->expires_at?->format('d.m.Y H:i') ?? '—' }}</td>
                                <td class="py-3 pr-4 text-xs">{{ $s->download_count }}@if($s->max_downloads)/{{ $s->max_downloads }}@endif · {{ $s->accesses_count }} Zugriffe</td>
                                <td class="py-3 pr-4 text-xs text-slate-500">
                                    @if($s->last_review_response_at)
                                        bestaetigt {{ $s->last_review_response_at->diffForHumans() }}
                                    @elseif($s->last_review_sent_at)
                                        gesendet {{ $s->last_review_sent_at->diffForHumans() }}
                                    @else
                                        steht aus
                                    @endif
                                </td>
                                <td class="py-3 text-right">
                                    @if($s->isActive())
                                        <button type="button" onclick="navigator.clipboard.writeText('{{ route('share.show', $s->token) }}')" class="text-xs text-indigo-600 hover:text-indigo-500" title="Link kopieren">Link</button>
                                        <form method="POST" action="{{ route('shares.revoke', $s) }}" class="inline ms-2" onsubmit="return confirm('Freigabe wirklich widerrufen?')">
                                            @csrf
                                            <button class="text-xs text-rose-600 hover:text-rose-500">widerrufen</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                            @if($s->review_response && $s->last_review_response_at)
                                <tr><td colspan="{{ $isAdmin ? 7 : 6 }}" class="pb-3 ps-4 text-xs text-slate-500">
                                    <em>Letzte Begruendung:</em> {{ $s->review_response }}
                                </td></tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $shares->links() }}</div>
        @endif
    </x-card>
</x-app-layout>
