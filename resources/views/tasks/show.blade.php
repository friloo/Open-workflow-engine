<x-app-layout>
    <x-slot name="header">{{ $instance->workflow->name }}</x-slot>
    <x-slot name="subheader">{{ data_get($node, 'data.label', 'Aufgabe') }} — eingegangen {{ $step->assigned_at?->diffForHumans() }}</x-slot>

    <x-breadcrumbs :items="[
        ['title' => 'Meine Aufgaben', 'url' => route('tasks.index')],
        ['title' => $instance->workflow->name],
        ['title' => data_get($node, 'data.label', 'Aufgabe')],
    ]" />

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            @php($attachments = $instance->attachments)
            @php($previewables = $attachments->filter(fn ($a) => $a->isPdf() || $a->isImage())->values())
            @if($attachments->isNotEmpty())
                {{-- Auf Desktop: grosse Preview mit Tabs zum Wechseln. Auf Mobile:
                     einfache Datei-Liste zum Antippen / Download. --}}
                <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden"
                     x-data="{ idx: 0 }">
                    <div class="border-b border-slate-200 px-6 py-4 flex items-baseline justify-between gap-3">
                        <div>
                            <h2 class="text-base font-semibold text-slate-900">Beleg zur Aufgabe</h2>
                            <p class="mt-1 text-sm text-slate-500">
                                @if($previewables->count() > 1)
                                    {{ $attachments->count() }} Dateien — klick eine fuer die Vorschau.
                                @else
                                    Direkt sehen, was du genehmigst.
                                @endif
                            </p>
                        </div>
                        <span class="text-xs text-slate-500 hidden lg:block">{{ $attachments->count() }} Datei(en)</span>
                    </div>

                    {{-- Tabs (Desktop). Wenn nur 1 Datei: kein Tab-Strip noetig. --}}
                    @if($attachments->count() > 1)
                        <div class="hidden lg:flex border-b border-slate-100 overflow-x-auto">
                            @foreach($attachments as $i => $a)
                                <button type="button" @click="idx = {{ $i }}"
                                    :class="idx === {{ $i }} ? 'border-indigo-500 text-indigo-700 bg-white' : 'border-transparent text-slate-500 hover:text-slate-700 hover:bg-slate-50'"
                                    class="whitespace-nowrap border-b-2 px-4 py-2 text-sm font-medium flex items-center gap-2">
                                    @if($a->isPdf())<span class="grid h-5 w-7 place-items-center rounded bg-rose-100 text-rose-700 text-[10px] font-bold">PDF</span>
                                    @elseif($a->isImage())<span class="grid h-5 w-7 place-items-center rounded bg-sky-100 text-sky-700 text-[10px] font-bold">IMG</span>
                                    @else<span class="grid h-5 w-7 place-items-center rounded bg-slate-100 text-slate-700 text-[10px] font-bold">DOC</span>
                                    @endif
                                    <span class="truncate max-w-[20ch]">{{ $a->original_name }}</span>
                                </button>
                            @endforeach
                        </div>
                    @endif

                    {{-- Preview-Bereiche (Desktop). Pro Attachment einer, x-show schaltet. --}}
                    <div class="hidden lg:block">
                        @foreach($attachments as $i => $a)
                            <div x-show="idx === {{ $i }}" {{ $i === 0 ? '' : 'style=display:none' }}>
                                <div class="flex items-center justify-between gap-3 border-b border-slate-100 bg-slate-50 px-4 py-2 text-xs">
                                    <div class="text-slate-700 truncate">
                                        <span class="font-medium">{{ $a->original_name }}</span>
                                        <span class="text-slate-400">·</span>
                                        <span class="text-slate-500">{{ $a->sizeFormatted() }}</span>
                                        @if($a->document_type)
                                            <span class="text-slate-400">·</span>
                                            <span class="text-slate-500">{{ $a->document_type }}</span>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-3 whitespace-nowrap">
                                        <a href="{{ route('documents.show', $a) }}" class="text-slate-600 hover:text-slate-900">Details</a>
                                        <span class="text-slate-300">·</span>
                                        <a href="{{ route('attachments.download', $a) }}" target="_blank" class="text-indigo-600 hover:text-indigo-500">Im Tab</a>
                                    </div>
                                </div>
                                @if($a->isPdf())
                                    <iframe src="{{ route('documents.preview', $a) }}#toolbar=1"
                                        class="w-full h-[65vh] bg-white" title="{{ $a->original_name }}"></iframe>
                                @elseif($a->isImage())
                                    <div class="flex items-center justify-center bg-slate-100 p-4 h-[65vh]">
                                        <img src="{{ route('documents.preview', $a) }}" alt="{{ $a->original_name }}"
                                             class="max-w-full max-h-full object-contain">
                                    </div>
                                @else
                                    <div class="flex flex-col items-center justify-center h-[40vh] text-center text-sm text-slate-600 bg-white p-6">
                                        <strong>{{ $a->original_name }}</strong>
                                        <p class="mt-1 text-slate-500">Dieser Dateityp wird im Browser nicht direkt angezeigt.</p>
                                        <a href="{{ route('attachments.download', $a) }}" target="_blank"
                                           class="mt-3 inline-flex rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-500">Herunterladen</a>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    {{-- Mobile: einfache Liste mit Download-Links --}}
                    <ul class="lg:hidden divide-y divide-slate-100">
                        @foreach($attachments as $a)
                            <li class="px-6 py-3 flex items-center justify-between gap-2 text-sm">
                                <div class="flex items-center gap-2 min-w-0">
                                    @if($a->isPdf())<span class="grid h-7 w-7 place-items-center rounded bg-rose-100 text-rose-700 text-xs font-bold">PDF</span>
                                    @elseif($a->isImage())<span class="grid h-7 w-7 place-items-center rounded bg-sky-100 text-sky-700 text-xs font-bold">IMG</span>
                                    @else<span class="grid h-7 w-7 place-items-center rounded bg-slate-100 text-slate-700 text-xs font-bold">DOC</span>
                                    @endif
                                    <div class="min-w-0">
                                        <a href="{{ route('attachments.download', $a) }}" target="_blank" class="font-medium text-indigo-600 hover:text-indigo-500 truncate block">{{ $a->original_name }}</a>
                                        <div class="text-xs text-slate-500">{{ $a->sizeFormatted() }}</div>
                                    </div>
                                </div>
                                <a href="{{ route('documents.show', $a) }}" class="text-xs text-slate-500 hover:text-slate-700 shrink-0">Details</a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <x-card title="Antragsdaten">
                @php($schema = $instance->version?->form_schema ?? [])
                @if(empty($schema) && empty($instance->data))
                    <p class="text-sm text-slate-500">Keine Antragsdaten.</p>
                @else
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                        @foreach($schema as $field)
                            @php($v = $instance->data[$field['key']] ?? null)
                            <div>
                                <dt class="text-xs font-medium text-slate-500">{{ $field['label'] ?? $field['key'] }}</dt>
                                <dd class="text-slate-900">
                                    @if(is_bool($v) || ($field['type'] ?? '')==='checkbox')
                                        {{ $v ? 'Ja' : 'Nein' }}
                                    @elseif(is_array($v))
                                        {{ implode(', ', $v) }}
                                    @else
                                        {{ $v ?? '—' }}
                                    @endif
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                @endif
            </x-card>

            <x-card title="Entscheidung">
                <form method="POST" action="{{ route('tasks.decide', $step) }}" class="space-y-4" x-data="{ decision: '' }">
                    @csrf
                    <div class="flex flex-wrap gap-3">
                        <label class="flex-1 min-w-[160px] cursor-pointer rounded-lg border border-slate-200 p-3 hover:border-emerald-400 has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-50">
                            <input type="radio" name="decision" value="approved" x-model="decision" class="sr-only">
                            <div class="text-sm font-semibold text-emerald-700">✓ Genehmigen</div>
                            <div class="text-xs text-slate-500">Workflow laeuft am Ausgang „Genehmigt" weiter.</div>
                        </label>
                        <label class="flex-1 min-w-[160px] cursor-pointer rounded-lg border border-slate-200 p-3 hover:border-rose-400 has-[:checked]:border-rose-500 has-[:checked]:bg-rose-50">
                            <input type="radio" name="decision" value="rejected" x-model="decision" class="sr-only">
                            <div class="text-sm font-semibold text-rose-700">✗ Ablehnen</div>
                            <div class="text-xs text-slate-500">Workflow folgt dem „Abgelehnt"-Pfad.</div>
                        </label>
                        @if(data_get($node, 'data.allow_forward'))
                            <label class="flex-1 min-w-[160px] cursor-pointer rounded-lg border border-slate-200 p-3 hover:border-amber-400 has-[:checked]:border-amber-500 has-[:checked]:bg-amber-50">
                                <input type="radio" name="decision" value="forwarded" x-model="decision" class="sr-only">
                                <div class="text-sm font-semibold text-amber-700">↪ Weiterleiten</div>
                                <div class="text-xs text-slate-500">Aufgabe an andere Person uebergeben.</div>
                            </label>
                        @endif
                    </div>

                    <div x-show="decision==='forwarded'" x-transition style="display:none;">
                        <label for="forward_user_id" class="block text-sm font-medium text-slate-700 mb-1">Weiterleiten an</label>
                        <select id="forward_user_id" name="forward_user_id"
                            class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">— bitte waehlen —</option>
                            @foreach($directory['users'] as $u)
                                <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('forward_user_id')" />
                    </div>

                    @php($reqApprove = (bool) data_get($node, 'data.require_comment_on_approval', false))
                    @php($reqReject = (bool) data_get($node, 'data.require_comment_on_rejection', false))
                    <div>
                        <label for="comment" class="block text-sm font-medium text-slate-700 mb-1">
                            <span x-show="decision === 'approved'">Kommentar @if($reqApprove)<span class="text-rose-600">*</span>@else(optional)@endif</span>
                            <span x-show="decision === 'rejected'">Begruendung @if($reqReject)<span class="text-rose-600">*</span>@else(optional)@endif</span>
                            <span x-show="decision !== 'approved' && decision !== 'rejected'">Kommentar (optional)</span>
                        </label>
                        <textarea id="comment" name="comment" rows="3"
                            x-bind:required="(decision === 'approved' && {{ $reqApprove ? 'true' : 'false' }}) || (decision === 'rejected' && {{ $reqReject ? 'true' : 'false' }})"
                            class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                        @error('comment')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="flex justify-end gap-3">
                        <a href="{{ route('tasks.index') }}"><x-secondary-button type="button">Spaeter</x-secondary-button></a>
                        <x-primary-button x-bind:disabled="!decision">Senden</x-primary-button>
                    </div>
                </form>
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="Workflow-Verlauf" description="Aktueller Schritt hervorgehoben.">
                <div id="workflow-viewer" x-data="workflowViewer()" x-init="boot()"
                     class="relative h-72 w-full rounded-lg border border-slate-200 bg-slate-50 overflow-hidden"></div>
                <div class="mt-3 flex flex-wrap gap-2 text-xs text-slate-600">
                    <span class="inline-flex items-center gap-1"><span class="inline-block h-2.5 w-2.5 rounded-full bg-emerald-500"></span>erledigt</span>
                    <span class="inline-flex items-center gap-1"><span class="inline-block h-2.5 w-2.5 rounded-full bg-indigo-500"></span>aktuell</span>
                    <span class="inline-flex items-center gap-1"><span class="inline-block h-2.5 w-2.5 rounded-full bg-slate-300"></span>offen</span>
                </div>
                <a href="{{ route('workflow-instances.show', $instance) }}" class="mt-3 inline-flex text-sm text-indigo-600 hover:text-indigo-500">Vollstaendigen Verlauf ansehen &rarr;</a>
            </x-card>

            <x-card title="Details">
                <dl class="space-y-3 text-sm">
                    <div>
                        <dt class="text-xs font-medium text-slate-500">Antragsteller</dt>
                        <dd class="text-slate-900">{{ $instance->starter?->name ?? 'oeffentlich' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-slate-500">Eingegangen</dt>
                        <dd class="text-slate-900">{{ $step->assigned_at?->format('d.m.Y H:i') }}</dd>
                    </div>
                    @if($step->due_at)
                        <div>
                            <dt class="text-xs font-medium text-slate-500">Frist</dt>
                            <dd class="{{ $step->due_at->isPast() ? 'text-rose-600 font-medium' : 'text-slate-900' }}">
                                {{ $step->due_at->format('d.m.Y H:i') }}
                            </dd>
                        </div>
                    @endif
                    @if($step->assignedRole)
                        <div>
                            <dt class="text-xs font-medium text-slate-500">Zugewiesen an Rolle</dt>
                            <dd class="text-slate-900">{{ $step->assignedRole->name }}</dd>
                        </div>
                    @endif
                </dl>
            </x-card>
        </div>
    </div>

    <script type="application/json" id="viewer-payload">
        {!! json_encode($viewerPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}
    </script>
    @vite('resources/js/viewer/index.js')
</x-app-layout>
