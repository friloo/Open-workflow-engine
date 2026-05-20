<x-app-layout>
    <x-slot name="header">Workflows</x-slot>
    <x-slot name="subheader">Entwerfe und verwalte deine Automatisierungen.</x-slot>

    @if(auth()->user()->hasPermission('workflows.design'))
        <div x-data="{ status: null, busy: false, result: null }" class="mb-4 flex flex-wrap items-center gap-3 rounded-lg border border-violet-200 bg-violet-50 px-4 py-3 text-sm">
            <svg class="h-5 w-5 text-violet-700" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z"/></svg>
            <div>
                <strong class="text-violet-900">KI-Assistenz</strong>
                <div class="text-xs text-violet-800">
                    @if($aiConfigured)
                        Aktiv ({{ $aiProvider }}) — kann im Designer einen Workflow-Entwurf generieren.
                    @else
                        Nicht konfiguriert. Hinterlege Anbieter und Modell unter Systemeinstellungen.
                    @endif
                </div>
            </div>
            <div class="ms-auto flex items-center gap-2">
                <button type="button" :disabled="busy || !{{ $aiConfigured ? 'true' : 'false' }}"
                    @click="busy=true; result=null;
                        fetch('{{ route('admin.ai.ping') }}', {
                            method:'POST',
                            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
                        }).then(async r => {
                            const j = await r.json().catch(() => ({ok: r.ok, message: 'HTTP '+r.status}));
                            result = { ok: r.ok, text: j.message || (r.ok ? 'OK' : 'Fehler') };
                        }).catch(e => result = { ok: false, text: e.message }).finally(() => busy = false)"
                    class="inline-flex items-center justify-center rounded-lg bg-violet-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-violet-500 disabled:opacity-50">
                    <span x-show="!busy">Verbindung testen</span>
                    <span x-show="busy">Teste…</span>
                </button>
                <a href="{{ route('admin.settings.index') }}" class="text-xs text-violet-700 hover:text-violet-900">KI-Einstellungen &rarr;</a>
            </div>
            <div x-show="result" x-transition class="basis-full text-xs"
                :class="result?.ok ? 'text-emerald-700' : 'text-rose-700'"
                style="display:none;" x-text="result?.text"></div>
        </div>
    @endif

    <div class="mb-4 flex flex-col sm:flex-row gap-3 sm:items-center sm:justify-between">
        <form method="GET" class="flex gap-2 max-w-md w-full">
            <x-text-input name="q" value="{{ $search }}" placeholder="Workflow suchen..." />
            <select name="status" class="block rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Alle Status</option>
                <option value="draft" @selected($status === 'draft')>Entwurf</option>
                <option value="active" @selected($status === 'active')>Aktiv</option>
                <option value="archived" @selected($status === 'archived')>Archiviert</option>
            </select>
            <x-secondary-button type="submit">Filtern</x-secondary-button>
        </form>
        <a href="{{ route('workflows.stats.index') }}" class="text-sm text-indigo-600 hover:text-indigo-500">Statistik</a>
        @if(auth()->user()->hasPermission('workflows.design'))
            <a href="{{ route('workflows.templates.index') }}" class="text-sm text-indigo-600 hover:text-indigo-500">Vorlagen</a>
            <a href="{{ route('workflows.create') }}"><x-primary-button type="button">Neuer Workflow</x-primary-button></a>
        @endif
    </div>

    <x-card>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead>
                    <tr class="text-left text-xs font-semibold uppercase text-slate-500">
                        <th class="py-2 pr-4">Name</th>
                        <th class="py-2 pr-4">Trigger</th>
                        <th class="py-2 pr-4">Status</th>
                        <th class="py-2 pr-4">Aktuelle Version</th>
                        <th class="py-2 pr-4">Geaendert</th>
                        <th class="py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($workflows as $w)
                        <tr>
                            <td class="py-3 pr-4">
                                <div class="font-medium text-slate-900">{{ $w->name }}</div>
                                <div class="text-xs text-slate-500">{{ $w->description }}</div>
                            </td>
                            <td class="py-3 pr-4 text-slate-700">
                                @switch($w->trigger_type)
                                    @case('form')<span class="inline-flex items-center rounded-md bg-sky-50 px-2 py-0.5 text-xs font-medium text-sky-700">Formular</span>@break
                                    @case('manual')<span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700">Manuell</span>@break
                                    @case('recurring')<span class="inline-flex items-center rounded-md bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Wiederkehrend</span>@break
                                @endswitch
                            </td>
                            <td class="py-3 pr-4">
                                @switch($w->status)
                                    @case('draft')<span class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">Entwurf</span>@break
                                    @case('active')<span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Aktiv</span>@break
                                    @case('archived')<span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">Archiviert</span>@break
                                @endswitch
                            </td>
                            <td class="py-3 pr-4 text-slate-700">
                                @if($w->currentVersion)
                                    v{{ $w->currentVersion->version_number }}
                                @else
                                    <span class="text-slate-400">noch keine</span>
                                @endif
                            </td>
                            <td class="py-3 pr-4 text-xs text-slate-500">
                                {{ $w->updated_at?->diffForHumans() }}
                                @if($w->updater)
                                    <div>von {{ $w->updater->name }}</div>
                                @endif
                            </td>
                            <td class="py-3 text-right space-x-3 whitespace-nowrap">
                                @if($w->status==='active' && auth()->user()->hasPermission('workflows.run'))
                                    <a href="{{ route('workflows.start', $w) }}" class="text-sm font-semibold text-emerald-700 hover:text-emerald-600">Starten</a>
                                @endif
                                <a href="{{ route('workflows.design', $w) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">Designer</a>
                                <a href="{{ route('workflows.process_doc', $w) }}" class="text-sm text-slate-600 hover:text-slate-900" title="Prozessbeschreibung als PDF herunterladen">PDF-Doku</a>
                                @if(auth()->user()->hasPermission('workflows.design'))
                                    <a href="{{ route('workflows.edit', $w) }}" class="text-sm text-slate-600 hover:text-slate-900">Metadaten</a>
                                    <a href="{{ route('workflows.versions', $w) }}" class="text-sm text-slate-600 hover:text-slate-900">Versionen</a>
                                    <a href="{{ route('workflows.instances', $w) }}" class="text-sm text-slate-600 hover:text-slate-900">Vorgaenge ({{ $w->instances_count ?? 0 }})</a>
                                    @if($w->trigger_type==='recurring')
                                        <a href="{{ route('workflows.schedules.index', $w) }}" class="text-sm text-slate-600 hover:text-slate-900">Wiedervorlagen</a>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6">
                            <x-empty-state icon="workflow"
                                title="Noch keine Workflows angelegt"
                                description="Starte mit einer Vorlage (Bestellantrag, Krankmeldung, Fuehrerschein-Pruefung) oder lass die KI dir einen Entwurf bauen.">
                                @if(auth()->user()->hasPermission('workflows.design'))
                                    <a href="{{ route('workflows.create') }}"><x-primary-button type="button">Neuer Workflow</x-primary-button></a>
                                    <a href="{{ route('help.show', 'workflows') }}" class="text-sm text-slate-600 hover:text-slate-900">Anleitung lesen</a>
                                @endif
                            </x-empty-state>
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $workflows->links() }}</div>
    </x-card>
</x-app-layout>
