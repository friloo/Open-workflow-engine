<x-app-layout>
    <x-slot name="header">Folder-Inboxen</x-slot>
    <x-slot name="subheader">Lokale Ordner (z. B. Scanner-Output, FTP-Drop-Ordner) werden automatisch eingelesen und als Dokumente archiviert.</x-slot>

    <div class="mb-4 flex justify-end">
        <a href="{{ route('admin.folder-inboxes.create') }}"><x-primary-button type="button">Neue Folder-Inbox</x-primary-button></a>
    </div>

    <x-card>
        @if($inboxes->isEmpty())
            <x-empty-state icon="document" title="Noch keine Folder-Inboxen"
                description="Lege einen Folder an, z. B. den Scanner-Output-Ordner deines Druckers oder einen FTP-Drop-Ordner." />
        @else
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead><tr class="text-left text-xs font-semibold uppercase text-slate-500">
                    <th class="py-2 pr-4">Name</th>
                    <th class="py-2 pr-4">Pfad</th>
                    <th class="py-2 pr-4">Doku-Typ</th>
                    <th class="py-2 pr-4">Workflow</th>
                    <th class="py-2 pr-4">Status</th>
                    <th class="py-2 pr-4">Zuletzt</th>
                    <th class="py-2"></th>
                </tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($inboxes as $i)
                        <tr>
                            <td class="py-3 pr-4 font-medium text-slate-900">{{ $i->name }}</td>
                            <td class="py-3 pr-4 text-xs font-mono">{{ \Illuminate\Support\Str::limit($i->absolutePath(), 60) }}</td>
                            <td class="py-3 pr-4 text-xs">{{ $i->document_type ?: '—' }}</td>
                            <td class="py-3 pr-4 text-xs">{{ $i->workflow?->name ?: '—' }}</td>
                            <td class="py-3 pr-4">
                                @if($i->is_active)
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">aktiv</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">pausiert</span>
                                @endif
                                @if($i->last_error)<div class="text-xs text-rose-700 mt-1" title="{{ $i->last_error }}">{{ \Illuminate\Support\Str::limit($i->last_error, 60) }}</div>@endif
                            </td>
                            <td class="py-3 pr-4 text-xs text-slate-500">{{ $i->last_scan_at?->diffForHumans() ?? '—' }}<div>{{ $i->last_status }}</div></td>
                            <td class="py-3 text-right space-x-3">
                                <form method="POST" action="{{ route('admin.folder-inboxes.scan', $i) }}" class="inline">@csrf<button class="text-sm text-slate-600 hover:text-slate-900">Jetzt scannen</button></form>
                                <a href="{{ route('admin.folder-inboxes.edit', $i) }}" class="text-sm text-indigo-600 hover:text-indigo-500">Bearbeiten</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-card>
</x-app-layout>
