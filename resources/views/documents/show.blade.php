<x-app-layout>
    <x-slot name="header">{{ $attachment->original_name }}</x-slot>
    <x-slot name="subheader">
        {{ $attachment->document_type ?: 'Ohne Dokumenttyp' }}
        · v{{ $attachment->version_number }}{{ $attachment->is_current_version ? ' (aktuell)' : ' (überholt)' }}
        · {{ $attachment->sizeFormatted() }} · <x-fmt-date :value="$attachment->created_at" format="d.m.Y H:i" />
    </x-slot>

    <x-breadcrumbs :items="[
        ['title' => 'Dokumente', 'url' => route('documents.index')],
        ['title' => $attachment->document_type ?: 'Unklassifiziert',
         'url' => $attachment->document_type
             ? route('documents.index', ['type' => $attachment->document_type])
             : route('documents.index', ['type' => '__unclassified__'])],
        ['title' => $attachment->original_name],
    ]" />

    {{-- Action-Toolbar: was kann ich JETZT mit dem Dokument machen --}}
    <div class="flex flex-wrap items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 shadow-sm"
         x-data="{ workflowOpen: false, versionOpen: false, moreOpen: false }">
        {{-- Download (primary) --}}
        <a href="{{ route('attachments.download', $attachment) }}"
           class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
            Herunterladen
        </a>

        @if($attachment->isPdf() || $attachment->isImage())
            <a href="{{ route('documents.preview', $attachment) }}" target="_blank"
               class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
                Im Tab öffnen
            </a>
        @endif

        {{-- Workflow starten --}}
        @if($availableWorkflows->isNotEmpty() && auth()->user()->hasAnyPermission(['workflows.run', 'workflows.design']))
            <div class="relative" @click.outside="workflowOpen = false">
                <button type="button" @click="workflowOpen = !workflowOpen"
                    class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5 10.5 21l3-3 3 3 6.75-7.5M3.75 5.25h17m-17 4.5h17"/></svg>
                    Workflow starten
                    <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 12 12"><path d="M3 4.5 6 8l3-3.5"/></svg>
                </button>
                <div x-show="workflowOpen" x-transition class="absolute left-0 z-30 mt-1 w-72 rounded-lg bg-white shadow-lg ring-1 ring-slate-200" style="display:none;">
                    <div class="border-b border-slate-100 px-3 py-2 text-xs font-semibold uppercase text-slate-500">Aktive Workflows</div>
                    <ul class="max-h-72 overflow-y-auto py-1">
                        @foreach($availableWorkflows as $wf)
                            <li>
                                <form method="POST" action="{{ route('documents.start_workflow', $attachment) }}">
                                    @csrf
                                    <input type="hidden" name="workflow_id" value="{{ $wf->id }}">
                                    <button type="submit" class="block w-full text-left px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">{{ $wf->name }}</button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        {{-- Neue Version --}}
        @if(auth()->user()->hasPermission('documents.search'))
            <div class="relative" @click.outside="versionOpen = false">
                <button type="button" @click="versionOpen = !versionOpen"
                    class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0 3 3m-3-3-3 3M6.75 19.5a4.5 4.5 0 0 1-1.41-8.775 5.25 5.25 0 0 1 10.233-2.33 3 3 0 0 1 3.758 3.848A3.752 3.752 0 0 1 18 19.5H6.75Z"/></svg>
                    Neue Version
                </button>
                <div x-show="versionOpen" x-transition class="absolute left-0 z-30 mt-1 w-80 rounded-lg bg-white shadow-lg ring-1 ring-slate-200 p-3" style="display:none;">
                    <form method="POST" enctype="multipart/form-data" action="{{ route('documents.new_version', $attachment) }}" class="space-y-2">
                        @csrf
                        <p class="text-xs text-slate-500">Alte Versionen bleiben dauerhaft erhalten.</p>
                        <input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png,.webp,.heic,.heif,.doc,.docx,.xls,.xlsx,.txt,.csv" required
                            class="block w-full text-sm text-slate-700 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-indigo-700 hover:file:bg-indigo-100">
                        <x-input-error :messages="$errors->get('file')" />
                        <button class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Hochladen</button>
                    </form>
                </div>
            </div>
        @endif

        {{-- Mehr-Dropdown: weniger häufige Aktionen --}}
        <div class="relative" @click.outside="moreOpen = false">
            <button type="button" @click="moreOpen = !moreOpen"
                class="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 12a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM12.75 12a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM18.75 12a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z"/></svg>
            </button>
            <div x-show="moreOpen" x-transition class="absolute right-0 z-30 mt-1 w-56 rounded-lg bg-white shadow-lg ring-1 ring-slate-200 py-1" style="display:none;">
                @if(in_array($attachment->ocr_status, ['pending','failed','skipped','done']))
                    <form method="POST" action="{{ route('documents.reindex', $attachment) }}">
                        @csrf
                        <button type="submit" class="block w-full text-left px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">OCR neu indexieren</button>
                    </form>
                @endif
                <a href="{{ route('attachments.download', $attachment) }}" class="block px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">Original-Datei herunterladen</a>
                @if(auth()->user()->hasPermission('audit.view'))
                    <a href="{{ route('admin.audit.index', ['model_type' => 'attachment', 'model_id' => $attachment->id]) }}"
                       class="block px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">Audit-Log für dieses Dokument</a>
                @endif
            </div>
        </div>

        <div class="ms-auto text-xs text-slate-500">
            v{{ $attachment->version_number }}{{ $attachment->is_current_version ? '' : ' (alt)' }}
            · {{ $attachment->sizeFormatted() }}
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            @if($attachment->isPdf() || $attachment->isImage())
                <x-card title="Vorschau" description="Direkt im Browser geöffnet (kein Download).">
                    @if($attachment->isPdf())
                        <iframe src="{{ route('documents.preview', $attachment) }}#toolbar=1" class="w-full h-[70vh] rounded-lg border border-slate-200" title="PDF-Vorschau"></iframe>
                    @else
                        <img src="{{ route('documents.preview', $attachment) }}" class="max-h-[70vh] mx-auto rounded-lg border border-slate-200" alt="{{ $attachment->original_name }}">
                    @endif
                    <div class="mt-2 flex gap-2 text-xs">
                        <a href="{{ route('documents.preview', $attachment) }}" target="_blank" class="text-indigo-600 hover:text-indigo-500">In neuem Tab öffnen</a>
                        <span class="text-slate-400">·</span>
                        <a href="{{ route('attachments.download', $attachment) }}" class="text-slate-600 hover:text-slate-900">Herunterladen</a>
                    </div>
                </x-card>
            @endif

            @php($schema = \App\Support\DocumentFieldSchema::forType((string) $attachment->document_type))
            @if(! empty($schema))
                <x-card title="Erkannte Felder" description="Automatisch aus dem OCR-Text extrahiert. Korrekturen werden gespeichert (Audit-Log).">
                    <form method="POST" action="{{ route('documents.fields.update', $attachment) }}" class="space-y-3">
                        @csrf
                        @php($current = (array) ($attachment->indexed_fields ?? []))
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            @foreach($schema as $f)
                                <div class="rounded-md border border-slate-200 bg-slate-50 p-2">
                                    <label class="block text-xs font-medium text-slate-500">
                                        <span class="font-mono">{{ $f['key'] }}</span>
                                        <span class="text-slate-400"> · {{ $f['label'] }}</span>
                                    </label>
                                    <input type="{{ in_array($f['type'], ['date'], true) ? 'date' : 'text' }}"
                                           name="fields[{{ $f['key'] }}]"
                                           value="{{ $current[$f['key']] ?? '' }}"
                                           class="mt-1 block w-full rounded-md border-slate-300 bg-white text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                            @endforeach
                        </div>
                        <div class="flex items-center justify-between">
                            @if($attachment->indexed_at)
                                <p class="text-xs text-slate-500">Indexiert <x-fmt-date :value="$attachment->indexed_at" format="relative" />.</p>
                            @else
                                <span></span>
                            @endif
                            <x-primary-button>Felder speichern</x-primary-button>
                        </div>
                    </form>
                </x-card>
            @endif

            <x-card title="Extrahierter Text" description="OCR-Inhalt für Volltextsuche.">
                @if($attachment->ocr_text)
                    <pre class="max-h-72 overflow-auto rounded-lg bg-slate-50 p-4 text-xs text-slate-800 whitespace-pre-wrap">{{ $attachment->ocr_text }}</pre>
                @else
                    <p class="text-sm text-slate-500">Kein Text extrahiert. Status: <strong>{{ $attachment->ocr_status }}</strong></p>
                @endif
                @if(in_array($attachment->ocr_status, ['pending','failed','skipped']))
                    <p class="mt-3 text-xs text-slate-500">OCR steckt — nimm das <em>Mehr</em>-Menü oben für „OCR neu indexieren".</p>
                @endif
            </x-card>

            @include('documents._zugferd-card', ['zugferdData' => $zugferdData])

            {{-- Notizen + Stempel auf dem Dokument. Bewusst leichtgewichtig:
                 keine pixel-genaue PDF-Overlay-Annotation, sondern eine
                 Liste mit Author/Datum/Seitenzahl + 'Stempel'-Buttons für
                 schnelle Markierungen wie 'Geprüft', 'Genehmigt'. --}}
            <x-card title="Notizen & Stempel" description="Markiere das Dokument mit einem Stempel oder hinterlasse eine Notiz für Kollegen.">
                <form method="POST" action="{{ route('documents.annotations.store', $attachment) }}"
                      class="space-y-3 mb-4" x-data="{ kind: 'note', text: '', color: 'slate' }">
                    @csrf
                    <input type="hidden" name="kind" :value="kind">
                    <input type="hidden" name="color" :value="color">

                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-xs text-slate-500">Stempel:</span>
                        @foreach([
                            'Geprüft' => 'emerald',
                            'Genehmigt' => 'indigo',
                            'Bezahlt' => 'sky',
                            'Storniert' => 'rose',
                            'Rückfrage' => 'amber',
                            'Archiviert' => 'slate',
                        ] as $label => $colorChip)
                            <button type="button"
                                @click="kind = 'stamp'; text = @js($label); color = @js($colorChip); $refs.t.focus()"
                                class="inline-flex items-center gap-1 rounded-md border border-{{ $colorChip }}-200 bg-{{ $colorChip }}-50 px-2 py-0.5 text-xs font-medium text-{{ $colorChip }}-700 hover:bg-{{ $colorChip }}-100">
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-[1fr_auto_auto] gap-2">
                        <input type="text" name="text" x-ref="t" x-model="text" required maxlength="500"
                            placeholder='Notiz oder Stempel-Text …'
                            class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <input type="number" name="page" min="1" max="9999" placeholder="Seite"
                            class="w-20 rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <button type="submit" class="rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-indigo-500">Hinzufügen</button>
                    </div>
                </form>

                @if($annotations->isEmpty())
                    <p class="text-xs text-slate-500">Noch keine Notizen oder Stempel.</p>
                @else
                    <ul class="space-y-2">
                        @foreach($annotations as $a)
                            @php($tone = $a->color ?: 'slate')
                            @php($kindLabel = $a->kind === 'stamp' ? 'Stempel' : ($a->kind === 'highlight' ? 'Markierung' : 'Notiz'))
                            <li class="flex items-start gap-3 rounded-lg border border-{{ $tone }}-200 bg-{{ $tone }}-50 p-2">
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm font-medium text-{{ $tone }}-900">
                                        @if($a->kind === 'stamp')
                                            <span class="inline-flex items-center gap-1 rounded border border-{{ $tone }}-400 bg-white px-1.5 py-0.5 text-xs font-bold uppercase text-{{ $tone }}-700">{{ $a->text }}</span>
                                        @else
                                            {{ $a->text }}
                                        @endif
                                    </div>
                                    <div class="text-[11px] text-slate-500 mt-1">
                                        {{ $kindLabel }}
                                        @if($a->page) · Seite {{ $a->page }} @endif
                                        @if($a->creator) · {{ $a->creator->name }} @endif
                                        · <x-fmt-date :value="$a->created_at" format="relative" />
                                    </div>
                                </div>
                                @if(auth()->id() === $a->created_by || auth()->user()->hasAnyPermission(['workflows.design']))
                                    <form method="POST" action="{{ route('documents.annotations.destroy', $a) }}" onsubmit="return confirm('Notiz wirklich entfernen?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs text-slate-400 hover:text-rose-600" title="Löschen">×</button>
                                    </form>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>

            @php($attTags = $attachment->tags ?? collect())
            @php($attCases = $attachment->cases ?? collect())
            <x-card title="Tags & Akten">
                <div class="space-y-3">
                    <div>
                        <div class="text-xs font-medium text-slate-500 mb-1">Tags</div>
                        @if($attTags->isEmpty())
                            <p class="text-xs text-slate-500">— keine Tags —</p>
                        @else
                            <div class="flex flex-wrap gap-1.5">
                                @foreach($attTags as $tag)
                                    <span class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-xs font-medium" style="background:{{ $tag->color }}22; color:{{ $tag->color }};">
                                        <span class="inline-block h-1.5 w-1.5 rounded-full" style="background:{{ $tag->color }}"></span>
                                        {{ $tag->name }}
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <div>
                        <div class="text-xs font-medium text-slate-500 mb-1">In Akten</div>
                        @if($attCases->isEmpty())
                            <p class="text-xs text-slate-500">— keiner Akte zugeordnet —</p>
                        @else
                            <ul class="space-y-1">
                                @foreach($attCases as $case)
                                    <li class="text-sm">
                                        <a href="{{ route('cases.show', $case) }}" class="text-indigo-600 hover:text-indigo-500">{{ $case->name }}</a>
                                        @if($case->reference)<code class="ms-1 text-xs text-slate-500">{{ $case->reference }}</code>@endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
                <p class="mt-3 text-xs text-slate-500">Tags und Akten weist du am bequemsten über die Bulk-Aktion in der Dokumenten-Liste zu.</p>
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
                                    <x-fmt-date :value="$v->created_at" format="d.m.Y H:i" />@if($v->uploader) · {{ $v->uploader->name }}@endif
                                </div>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                @if($v->id !== $attachment->id)
                                    <a href="{{ route('documents.diff', ['attachment' => $v, 'other' => $attachment]) }}"
                                       class="text-xs text-slate-600 hover:text-slate-900" title="Mit aktuell angezeigter Version vergleichen">Vergleichen</a>
                                @endif
                                <a href="{{ route('attachments.download', $v) }}" class="text-xs text-indigo-600 hover:text-indigo-500">Download</a>
                            </div>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-3 text-xs text-slate-500">Eine neue Version lädst du oben über den <em>Neue Version</em>-Button hoch. <em>Vergleichen</em> zeigt Text-Unterschiede zwischen zwei Versionen.</p>
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
                                <label class="block text-xs font-medium text-slate-600">Gültig (Tage)</label>
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
                            <input type="text" name="note" placeholder="z. B. für Anwalt Mueller" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <label class="inline-flex items-center gap-2 text-xs text-slate-700">
                            <input type="hidden" name="follow_versions" value="0">
                            <input type="checkbox" name="follow_versions" value="1" checked class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                            Immer die aktuelle Version freigeben
                        </label>
                        <button class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Link erstellen</button>
                    </form>
                    <p class="mt-3 text-xs text-slate-500">Du bekommst alle {{ (int) \App\Support\Settings::get('shares.review_interval_days', 7) }} Tage eine Mail zur Bestätigung. Reagierst du {{ (int) \App\Support\Settings::get('shares.review_grace_days', 3) }} Tage lang nicht, wird automatisch widerrufen.</p>
                </x-card>
            @endif

            <x-card title="Datei">
                <dl class="space-y-2 text-xs">
                    <div><dt class="text-slate-500">Original-Name</dt><dd class="text-slate-900">{{ $attachment->original_name }}</dd></div>
                    <div><dt class="text-slate-500">Mime-Type</dt><dd class="text-slate-900">{{ $attachment->mime_type }}</dd></div>
                    <div><dt class="text-slate-500">Größe</dt><dd class="text-slate-900">{{ $attachment->sizeFormatted() }}</dd></div>
                    <div><dt class="text-slate-500">Hochgeladen</dt><dd class="text-slate-900"><x-fmt-date :value="$attachment->created_at" format="d.m.Y H:i:s" /> von {{ $attachment->uploader?->name ?? 'System' }}</dd></div>
                    <div><dt class="text-slate-500">Version</dt><dd class="text-slate-900">v{{ $attachment->version_number }} in Chain <code class="text-xs">{{ \Illuminate\Support\Str::limit($attachment->version_chain_id, 8, '') }}</code></dd></div>
                    <div><dt class="text-slate-500">OCR</dt><dd class="text-slate-900">{{ $attachment->ocr_status }}@if($attachment->ocr_tool) · {{ $attachment->ocr_tool }}@endif</dd></div>
                    <div><dt class="text-slate-500">SHA-256</dt><dd class="text-slate-900 font-mono text-xs break-all">{{ $attachment->content_hash }}</dd></div>
                </dl>
            </x-card>

            <x-card title="Kontext">
                @if($attachment->attachable_type)
                    <p class="text-sm text-slate-700">Gehört zu <code class="bg-slate-100 rounded px-1">{{ class_basename($attachment->attachable_type) }}#{{ $attachment->attachable_id }}</code></p>
                    @php($att = $attachment->attachable)
                    @if($att instanceof \App\Models\Asset)
                        <a href="{{ route('assets.edit', $att) }}" class="mt-2 inline-flex text-sm text-indigo-600 hover:text-indigo-500">Asset öffnen: {{ $att->name }}</a>
                    @elseif($att instanceof \App\Models\WorkflowInstance)
                        <a href="{{ route('workflow-instances.show', $att) }}" class="mt-2 inline-flex text-sm text-indigo-600 hover:text-indigo-500">Vorgang #{{ $att->id }}</a>
                    @endif
                @else
                    <p class="text-sm text-slate-500">Stand-Alone-Dokument (kein verknüpftes Objekt).</p>
                @endif
            </x-card>
        </div>
    </div>
</x-app-layout>
