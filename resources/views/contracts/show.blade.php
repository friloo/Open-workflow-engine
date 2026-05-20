<x-app-layout>
    <x-slot name="header">{{ $contract->name }}</x-slot>
    <x-slot name="subheader">{{ $contract->party ?: 'Vertrag' }} · {{ $contract->category ?: '—' }}</x-slot>

    <x-breadcrumbs :items="[
        ['title' => 'Vertraege', 'url' => route('contracts.index')],
        ['title' => $contract->name],
    ]" />

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-4">
            <x-card>
                <div class="flex items-start justify-between gap-4">
                    <div>
                        @switch($contract->status)
                            @case('active')<span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Aktiv</span>@break
                            @case('notice_due')<span class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">Kuendigungsfrist erreicht</span>@break
                            @case('expired')<span class="inline-flex items-center rounded-full bg-rose-50 px-2 py-0.5 text-xs font-medium text-rose-700">Abgelaufen</span>@break
                        @endswitch
                        @if($contract->auto_renew)
                            <span class="ms-2 inline-flex items-center rounded-full bg-sky-50 px-2 py-0.5 text-xs font-medium text-sky-700">Auto-Verlaengerung {{ $contract->auto_renew_months }} Monate</span>
                        @endif
                    </div>
                    @if($canManage)
                        <div class="flex gap-2">
                            <a href="{{ route('contracts.edit', $contract) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Bearbeiten</a>
                            <form method="POST" action="{{ route('contracts.destroy', $contract) }}" onsubmit="return confirm('Vertrag wirklich loeschen?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="rounded-lg border border-rose-300 bg-white px-3 py-1.5 text-sm font-medium text-rose-700 hover:bg-rose-50">Loeschen</button>
                            </form>
                        </div>
                    @endif
                </div>

                @if($contract->description)
                    <p class="mt-4 text-sm text-slate-700 whitespace-pre-wrap">{{ $contract->description }}</p>
                @endif
            </x-card>

            <x-card title="Eckdaten">
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                    <div>
                        <dt class="text-xs uppercase text-slate-500">Vertragspartner</dt>
                        <dd class="text-slate-900">{{ $contract->party ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase text-slate-500">Vertragsart</dt>
                        <dd class="text-slate-900">
                            @if($contract->type)
                                <span class="inline-flex items-center gap-1">
                                    <span class="inline-block h-2 w-2 rounded-full" style="background:{{ $contract->type->color }}"></span>
                                    {{ $contract->type->name }}
                                </span>
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase text-slate-500">Beginn</dt>
                        <dd class="text-slate-900">{{ $contract->start_date?->format('d.m.Y') ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase text-slate-500">Ende</dt>
                        <dd class="text-slate-900">{{ $contract->end_date?->format('d.m.Y') ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase text-slate-500">Kuendigungsfrist</dt>
                        <dd class="text-slate-900">{{ $contract->notice_period_days }} Tage</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase text-slate-500">Frist erreicht am</dt>
                        <dd class="text-slate-900">{{ $contract->noticeDeadline()?->format('d.m.Y') ?: '—' }}</dd>
                    </div>
                </dl>
            </x-card>

            <x-card title="Akten" description="Aktendeckel in denen dieser Vertrag enthalten ist.">
                @if($canManage && $availableCases->isNotEmpty())
                    <form method="POST" action="{{ route('contracts.cases.attach', $contract) }}" class="mb-3 flex flex-wrap items-end gap-2">
                        @csrf
                        <div class="flex-1 min-w-[220px]">
                            <x-input-label for="document_case_id" value="Akte waehlen" />
                            <select id="document_case_id" name="document_case_id" required
                                    class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">— Akte auswaehlen —</option>
                                @foreach($availableCases as $a)
                                    <option value="{{ $a->id }}">{{ $a->name }}@if($a->reference) ({{ $a->reference }})@endif</option>
                                @endforeach
                            </select>
                        </div>
                        <x-secondary-button type="submit">Zu Akte heften</x-secondary-button>
                    </form>
                @elseif($canManage)
                    <p class="text-xs text-slate-500 mb-3">Keine offenen Akten verfuegbar.
                        <a href="{{ route('cases.create') }}" class="text-indigo-600 hover:text-indigo-500">Neue Akte anlegen</a>.</p>
                @endif

                @if($contract->cases->isEmpty())
                    <p class="text-sm text-slate-500">Dieser Vertrag ist noch keiner Akte zugeordnet.</p>
                @else
                    <ul class="divide-y divide-slate-100">
                        @foreach($contract->cases as $a)
                            <li class="py-2 flex items-center justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <a href="{{ route('cases.show', $a) }}" class="font-medium text-slate-900 hover:text-indigo-600">{{ $a->name }}</a>
                                    @if($a->reference)
                                        <div class="text-xs text-slate-500"><code>{{ $a->reference }}</code></div>
                                    @endif
                                </div>
                                @if($canManage)
                                    <form method="POST" action="{{ route('contracts.cases.detach', [$contract, $a->id]) }}"
                                          onsubmit="return confirm('Vertrag aus dieser Akte loesen?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-xs text-rose-600 hover:text-rose-500">Entfernen</button>
                                    </form>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>

            <x-card title="Dokumente" description="Vertragsdatei, Anlagen, Schriftverkehr.">
                @if($canManage)
                    <form method="POST" action="{{ route('attachments.store', ['type' => 'contract', 'id' => $contract->id]) }}"
                          enctype="multipart/form-data" class="mb-4 flex flex-wrap items-end gap-2">
                        @csrf
                        <div class="flex-1 min-w-[200px]">
                            <x-input-label for="file" value="Datei (PDF / DOCX / Bild, max. 15 MB)" />
                            <input id="file" type="file" name="file" required
                                   accept="application/pdf,image/png,image/jpeg,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                                   class="block w-full text-sm text-slate-700">
                            <x-input-error :messages="$errors->get('file')" />
                        </div>
                        <div class="flex-1 min-w-[180px]">
                            <x-input-label for="label" value="Beschriftung (optional)" />
                            <x-text-input id="label" name="label" placeholder="z. B. Hauptvertrag, Anlage 1, AGB" maxlength="128" />
                        </div>
                        <x-secondary-button type="submit">Hochladen</x-secondary-button>
                    </form>
                @endif

                @if($contract->attachments->isEmpty())
                    <p class="text-sm text-slate-500">
                        Noch keine Dokumente angehaengt.
                        @if($canManage)Lade die Vertrags-PDF + Anlagen hier hoch — sie werden revisionssicher gespeichert (SHA-256-Hash, OCR-Volltext).@endif
                    </p>
                @else
                    <ul class="divide-y divide-slate-100">
                        @foreach($contract->attachments as $a)
                            <li class="py-2 flex items-center justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <a href="{{ route('attachments.download', $a) }}"
                                       class="font-medium text-slate-900 hover:text-indigo-600 truncate block">
                                        {{ $a->label ?: $a->original_name }}
                                    </a>
                                    <div class="text-xs text-slate-500">
                                        {{ $a->original_name }} · {{ number_format($a->size / 1024, 0, ',', '.') }} kB
                                        · {{ strtoupper(explode('/', $a->mime_type)[1] ?? $a->mime_type) }}
                                        · {{ $a->created_at?->format('d.m.Y') }}
                                        @if($a->uploader) · von {{ $a->uploader->name }} @endif
                                    </div>
                                </div>
                                <a href="{{ route('attachments.download', $a) }}"
                                   class="rounded-lg border border-slate-300 bg-white px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                    Download
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>

        <div class="space-y-4">
            <x-card title="Verantwortung">
                <p class="text-sm">
                    @if($contract->owner)
                        <span class="font-medium text-slate-900">{{ $contract->owner->name }}</span>
                        <span class="text-slate-500">({{ $contract->owner->email }})</span>
                    @else
                        <span class="text-slate-500">Niemand zugewiesen</span>
                    @endif
                </p>
                <p class="mt-3 text-xs text-slate-500">
                    Angelegt {{ $contract->created_at?->diffForHumans() }}
                    @if($contract->creator) von {{ $contract->creator->name }} @endif
                </p>
            </x-card>

            @if($contract->last_reminder_at)
                <x-card title="Letzte Erinnerung">
                    <p class="text-sm text-slate-700">
                        {{ $contract->last_reminder_at->format('d.m.Y H:i') }}
                        <span class="text-slate-500">({{ $contract->last_reminder_at->diffForHumans() }})</span>
                    </p>
                </x-card>
            @endif

            <x-card title="Zugriffsrechte" description="Wer kann diesen Vertrag sehen?">
                <div class="text-sm space-y-2">
                    <div>
                        <div class="text-xs uppercase text-slate-500 mb-1">Ueber Vertragsart</div>
                        @if($contract->type && $contract->type->roles->isNotEmpty())
                            @foreach($contract->type->roles as $r)
                                <span class="inline-flex items-center rounded bg-slate-100 px-1.5 py-0.5 text-xs me-1 mb-1">
                                    {{ $r->name }}
                                    @if($r->pivot->can_manage)
                                        <span class="ms-1 text-[10px] font-semibold text-indigo-600">+M</span>
                                    @endif
                                </span>
                            @endforeach
                        @else
                            <p class="text-xs text-slate-400">Keine Vertragsart oder keine Rollen ueber Typ freigeschaltet.</p>
                        @endif
                    </div>
                    <div>
                        <div class="text-xs uppercase text-slate-500 mb-1">Zusaetzlich nur fuer diesen Vertrag</div>
                        @if($contract->roles->isNotEmpty())
                            @foreach($contract->roles as $r)
                                <span class="inline-flex items-center rounded bg-indigo-50 px-1.5 py-0.5 text-xs text-indigo-700 me-1 mb-1">
                                    {{ $r->name }}
                                    @if($r->pivot->can_manage)
                                        <span class="ms-1 text-[10px] font-semibold">+M</span>
                                    @endif
                                </span>
                            @endforeach
                        @else
                            <p class="text-xs text-slate-400">Keine zusaetzlichen Rollen.</p>
                        @endif
                    </div>
                    <p class="text-xs text-slate-500 pt-2 border-t border-slate-100">
                        <strong>+M</strong> = darf auch bearbeiten/loeschen. Admins haben immer vollen Zugriff.
                    </p>
                </div>
            </x-card>
        </div>
    </div>
</x-app-layout>
