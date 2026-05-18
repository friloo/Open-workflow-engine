<x-app-layout>
    <x-slot name="header">E-Mail-Postfaecher</x-slot>
    <x-slot name="subheader">IMAP-Postfaecher, deren Anhaenge als Dokumente archiviert und optional in einen Workflow geleitet werden.</x-slot>

    <div class="mb-4 flex justify-end">
        <a href="{{ route('admin.mailboxes.create') }}"><x-primary-button type="button">Neues Postfach</x-primary-button></a>
    </div>

    <x-card>
        @if($mailboxes->isEmpty())
            <x-empty-state icon="mail"
                title="Noch keine Postfaecher"
                description="Lege ein IMAP-Postfach an. Eingehende Mails werden alle 5 Minuten verarbeitet, Anhaenge revisionssicher gespeichert.">
                <a href="{{ route('admin.mailboxes.create') }}"><x-primary-button type="button">Postfach anlegen</x-primary-button></a>
            </x-empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead><tr class="text-left text-xs font-semibold uppercase text-slate-500">
                        <th class="py-2 pr-4">Name</th>
                        <th class="py-2 pr-4">Host</th>
                        <th class="py-2 pr-4">Dokumenttyp</th>
                        <th class="py-2 pr-4">Workflow</th>
                        <th class="py-2 pr-4">Status</th>
                        <th class="py-2 pr-4">Zuletzt</th>
                        <th class="py-2"></th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($mailboxes as $m)
                            <tr>
                                <td class="py-3 pr-4 font-medium text-slate-900">{{ $m->name }}<div class="text-xs text-slate-500">{{ $m->username }}</div></td>
                                <td class="py-3 pr-4 text-xs font-mono text-slate-700">{{ $m->host }}:{{ $m->port }} ({{ $m->encryption }})</td>
                                <td class="py-3 pr-4 text-xs text-slate-700">{{ $m->document_type ?: '—' }}</td>
                                <td class="py-3 pr-4 text-xs text-slate-700">{{ $m->workflow?->name ?: '—' }}</td>
                                <td class="py-3 pr-4">
                                    @if($m->is_active)
                                        <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">aktiv</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">pausiert</span>
                                    @endif
                                    @if($m->last_error)
                                        <div class="mt-1 text-xs text-rose-700" title="{{ $m->last_error }}">{{ \Illuminate\Support\Str::limit($m->last_error, 60) }}</div>
                                    @endif
                                </td>
                                <td class="py-3 pr-4 text-xs text-slate-500">{{ $m->last_fetch_at?->diffForHumans() ?? '—' }}<div>{{ $m->last_status }}</div></td>
                                <td class="py-3 text-right space-x-3">
                                    <form method="POST" action="{{ route('admin.mailboxes.test', $m) }}" class="inline">@csrf<button class="text-sm text-slate-600 hover:text-slate-900">Test</button></form>
                                    <form method="POST" action="{{ route('admin.mailboxes.fetch', $m) }}" class="inline">@csrf<button class="text-sm text-slate-600 hover:text-slate-900">Jetzt abrufen</button></form>
                                    <a href="{{ route('admin.mailboxes.edit', $m) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">Bearbeiten</a>
                                    <form method="POST" action="{{ route('admin.mailboxes.destroy', $m) }}" class="inline" onsubmit="return confirm('Postfach loeschen?')">
                                        @csrf @method('DELETE')
                                        <button class="text-sm text-rose-600 hover:text-rose-500">Loeschen</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $mailboxes->links() }}</div>
        @endif
    </x-card>
</x-app-layout>
