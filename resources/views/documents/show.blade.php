<x-app-layout>
    <x-slot name="header">{{ $attachment->original_name }}</x-slot>
    <x-slot name="subheader">
        {{ $attachment->document_type ?: 'Ohne Dokumenttyp' }}
        · v{{ $attachment->version_number }}{{ $attachment->is_current_version ? ' (aktuell)' : ' (ueberholt)' }}
        · {{ $attachment->sizeFormatted() }} · {{ $attachment->created_at->format('d.m.Y H:i') }}
    </x-slot>

    <div class="mb-4"><a href="{{ route('documents.index') }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; Dokumente</a></div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            @if($attachment->isPdf() || $attachment->isImage())
                <x-card title="Vorschau" description="Direkt im Browser geoeffnet (kein Download).">
                    @if($attachment->isPdf())
                        <iframe src="{{ route('documents.preview', $attachment) }}#toolbar=1" class="w-full h-[70vh] rounded-lg border border-slate-200" title="PDF-Vorschau"></iframe>
                    @else
                        <img src="{{ route('documents.preview', $attachment) }}" class="max-h-[70vh] mx-auto rounded-lg border border-slate-200" alt="{{ $attachment->original_name }}">
                    @endif
                    <div class="mt-2 flex gap-2 text-xs">
                        <a href="{{ route('documents.preview', $attachment) }}" target="_blank" class="text-indigo-600 hover:text-indigo-500">In neuem Tab oeffnen</a>
                        <span class="text-slate-400">·</span>
                        <a href="{{ route('attachments.download', $attachment) }}" class="text-slate-600 hover:text-slate-900">Herunterladen</a>
                    </div>
                </x-card>
            @endif

            <x-card title="Extrahierter Text" description="OCR-Inhalt fuer Volltextsuche.">
                @if($attachment->ocr_text)
                    <pre class="max-h-72 overflow-auto rounded-lg bg-slate-50 p-4 text-xs text-slate-800 whitespace-pre-wrap">{{ $attachment->ocr_text }}</pre>
                @else
                    <p class="text-sm text-slate-500">Kein Text extrahiert. Status: <strong>{{ $attachment->ocr_status }}</strong></p>
                @endif
                @if(in_array($attachment->ocr_status, ['pending','failed','skipped']))
                    <form method="POST" action="{{ route('documents.reindex', $attachment) }}" class="mt-3">
                        @csrf
                        <x-secondary-button>OCR erneut versuchen</x-secondary-button>
                    </form>
                @endif
            </x-card>

            <x-card title="Versionen ({{ $versions->count() }})">
                <ul class="divide-y divide-slate-100">
                    @foreach($versions->sortByDesc('version_number') as $v)
                        <li class="py-2 flex items-start justify-between gap-3 text-sm">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <a href="{{ route('documents.show', $v) }}" class="font-medium text-slate-900 hover:text-indigo-600">v{{ $v->version_number }}</a>
                                    @if($v->is_current_version)<span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">aktuell</span>@endif
                                    @if($v->id === $attachment->id)<span class="text-xs text-slate-400">(aktuell angezeigt)</span>@endif
                                </div>
                                <div class="text-xs text-slate-500">
                                    {{ $v->original_name }} · {{ $v->sizeFormatted() }} ·
                                    {{ $v->created_at->format('d.m.Y H:i') }}@if($v->uploader) · {{ $v->uploader->name }}@endif
                                </div>
                            </div>
                            <a href="{{ route('attachments.download', $v) }}" class="text-xs text-indigo-600 hover:text-indigo-500 shrink-0">Download</a>
                        </li>
                    @endforeach
                </ul>
                @if(auth()->user()->hasPermission('documents.search'))
                    <form method="POST" enctype="multipart/form-data" action="{{ route('documents.new_version', $attachment) }}" class="mt-4 border-t border-slate-200 pt-4 space-y-2">
                        @csrf
                        <label class="block text-xs font-medium text-slate-600">Neue Version hochladen</label>
                        <input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png,.webp,.heic,.heif,.doc,.docx,.xls,.xlsx,.txt,.csv" required
                            class="block w-full text-sm text-slate-700 file:mr-4 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100">
                        <p class="text-xs text-slate-500">Alte Versionen bleiben dauerhaft erhalten und sind weiterhin abrufbar.</p>
                        <x-input-error :messages="$errors->get('file')" />
                        <button class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Neue Version speichern</button>
                    </form>
                @endif
            </x-card>
        </div>

        <div class="space-y-6">
            @if(auth()->user()->hasPermission('shares.create') && $attachment->is_current_version)
                <x-card title="Link teilen" description="Externer Zugriff ohne Login. Cap: {{ (int) \App\Support\Settings::get('shares.max_expiry_days', 90) }} Tage.">
                    @if(session('shareCreated'))
                        @php($sc = session('shareCreated'))
                        <div class="mb-3 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-xs text-emerald-800">
                            <strong>Link erstellt:</strong>
                            <div class="mt-1 flex items-center gap-2">
                                <input type="text" value="{{ $sc['url'] }}" readonly class="flex-1 rounded border-slate-200 text-xs bg-white" onclick="this.select()">
                                <button type="button" onclick="navigator.clipboard.writeText('{{ $sc['url'] }}'); this.textContent='Kopiert'" class="text-xs text-emerald-700 hover:text-emerald-900">Kopieren</button>
                            </div>
                            @if($sc['expires'])<div class="mt-1">Laeuft ab: {{ $sc['expires'] }}</div>@endif
                        </div>
                    @endif
                    <form method="POST" action="{{ route('shares.store', $attachment) }}" class="space-y-2 text-sm">
                        @csrf
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-xs font-medium text-slate-600">Gueltig (Tage)</label>
                                <input type="number" name="expires_in_days" min="1" max="{{ (int) \App\Support\Settings::get('shares.max_expiry_days', 90) }}"
                                    value="{{ (int) \App\Support\Settings::get('shares.default_expiry_days', 14) }}"
                                    class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-600">Max. Zugriffe</label>
                                <input type="number" name="max_downloads" min="1" placeholder="unbegrenzt" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600">Passwort (optional)</label>
                            <input type="password" name="password" autocomplete="new-password" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600">Notiz (intern)</label>
                            <input type="text" name="note" placeholder="z. B. fuer Anwalt Mueller" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <label class="inline-flex items-center gap-2 text-xs text-slate-700">
                            <input type="hidden" name="follow_versions" value="0">
                            <input type="checkbox" name="follow_versions" value="1" checked class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                            Immer die aktuelle Version freigeben
                        </label>
                        <button class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Link erstellen</button>
                    </form>
                    <p class="mt-3 text-xs text-slate-500">Du bekommst alle {{ (int) \App\Support\Settings::get('shares.review_interval_days', 7) }} Tage eine Mail zur Bestaetigung. Reagierst du {{ (int) \App\Support\Settings::get('shares.review_grace_days', 3) }} Tage lang nicht, wird automatisch widerrufen.</p>
                </x-card>
            @endif

            <x-card title="Datei">
                <a href="{{ route('attachments.download', $attachment) }}" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Herunterladen</a>
                <dl class="mt-4 space-y-2 text-xs">
                    <div><dt class="text-slate-500">Original-Name</dt><dd class="text-slate-900">{{ $attachment->original_name }}</dd></div>
                    <div><dt class="text-slate-500">Mime-Type</dt><dd class="text-slate-900">{{ $attachment->mime_type }}</dd></div>
                    <div><dt class="text-slate-500">Groesse</dt><dd class="text-slate-900">{{ $attachment->sizeFormatted() }}</dd></div>
                    <div><dt class="text-slate-500">Hochgeladen</dt><dd class="text-slate-900">{{ $attachment->created_at->format('d.m.Y H:i:s') }} von {{ $attachment->uploader?->name ?? 'System' }}</dd></div>
                    <div><dt class="text-slate-500">Version</dt><dd class="text-slate-900">v{{ $attachment->version_number }} in Chain <code class="text-xs">{{ \Illuminate\Support\Str::limit($attachment->version_chain_id, 8, '') }}</code></dd></div>
                    <div><dt class="text-slate-500">OCR</dt><dd class="text-slate-900">{{ $attachment->ocr_status }}@if($attachment->ocr_tool) · {{ $attachment->ocr_tool }}@endif</dd></div>
                    <div><dt class="text-slate-500">SHA-256</dt><dd class="text-slate-900 font-mono text-xs break-all">{{ $attachment->content_hash }}</dd></div>
                </dl>
            </x-card>

            <x-card title="Kontext">
                @if($attachment->attachable_type)
                    <p class="text-sm text-slate-700">Gehoert zu <code class="bg-slate-100 rounded px-1">{{ class_basename($attachment->attachable_type) }}#{{ $attachment->attachable_id }}</code></p>
                    @php($att = $attachment->attachable)
                    @if($att instanceof \App\Models\Asset)
                        <a href="{{ route('assets.edit', $att) }}" class="mt-2 inline-flex text-sm text-indigo-600 hover:text-indigo-500">Asset oeffnen: {{ $att->name }}</a>
                    @elseif($att instanceof \App\Models\WorkflowInstance)
                        <a href="{{ route('workflow-instances.show', $att) }}" class="mt-2 inline-flex text-sm text-indigo-600 hover:text-indigo-500">Vorgang #{{ $att->id }}</a>
                    @endif
                @else
                    <p class="text-sm text-slate-500">Stand-Alone-Dokument (kein verknuepftes Objekt).</p>
                @endif
            </x-card>
        </div>
    </div>
</x-app-layout>
