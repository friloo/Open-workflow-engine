@php($isNew = ! $asset->exists)
<x-app-layout>
    <x-slot name="header">{{ $isNew ? 'Neues Asset' : $asset->name }}</x-slot>

    <div class="mb-4"><a href="{{ route('assets.index') }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; Assets</a></div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2">
    <x-card>
        <form method="POST" action="{{ $isNew ? route('assets.store') : route('assets.update', $asset) }}">
            @csrf
            @if(! $isNew) @method('PUT') @endif
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="name" value="Name" />
                    <x-text-input id="name" name="name" value="{{ old('name', $asset->name) }}" required />
                    <x-input-error :messages="$errors->get('name')" />
                </div>
                <div>
                    <x-input-label for="type" value="Typ" />
                    <x-text-input id="type" name="type" value="{{ old('type', $asset->type ?? '') }}" placeholder="z. B. Fuehrerschein" required />
                </div>
                <div>
                    <x-input-label for="user_id" value="Inhaber" />
                    <select id="user_id" name="user_id" required class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">— waehlen —</option>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}" @selected(old('user_id', $asset->user_id) == $u->id)>{{ $u->name }} ({{ $u->email }})</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('user_id')" />
                </div>
                <div>
                    <x-input-label for="valid_until" value="Gueltig bis" />
                    <x-text-input id="valid_until" name="valid_until" type="date" value="{{ old('valid_until', $asset->valid_until?->format('Y-m-d')) }}" />
                </div>
                <div>
                    <x-input-label for="lead_time_days" value="Vorlauffrist (Tage)" />
                    <x-text-input id="lead_time_days" name="lead_time_days" type="number" min="0" max="365" value="{{ old('lead_time_days', $asset->lead_time_days ?? 30) }}" required />
                    <p class="mt-1 text-xs text-slate-500">Workflow startet so viele Tage vor dem Ablaufdatum.</p>
                </div>
                <div>
                    <x-input-label for="workflow_id" value="Pruef-Workflow" />
                    <select id="workflow_id" name="workflow_id" class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">— keiner (nur Anzeige) —</option>
                        @foreach($workflows as $wf)
                            <option value="{{ $wf->id }}" @selected(old('workflow_id', $asset->workflow_id) == $wf->id)>{{ $wf->name }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-slate-500">Nur aktive Workflows mit Trigger „Wiederkehrend".</p>
                </div>
                <div>
                    <x-input-label for="status" value="Status" />
                    <select id="status" name="status" class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @php($st = old('status', $asset->status ?? 'active'))
                        <option value="active" @selected($st==='active')>aktiv</option>
                        <option value="expired" @selected($st==='expired')>abgelaufen</option>
                        <option value="archived" @selected($st==='archived')>archiviert</option>
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <x-input-label for="notes" value="Notizen" />
                    <textarea id="notes" name="notes" rows="3" class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes', $asset->notes) }}</textarea>
                </div>
            </div>

            <div class="mt-6 flex justify-between">
                @if(! $isNew)
                    <form method="POST" action="{{ route('assets.destroy', $asset) }}" onsubmit="return confirm('Asset wirklich loeschen?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="inline-flex items-center justify-center rounded-lg border border-rose-300 bg-white px-4 py-2 text-sm font-semibold text-rose-700 shadow-sm hover:bg-rose-50">Loeschen</button>
                    </form>
                @else <span></span>
                @endif
                <div class="flex gap-3">
                    <a href="{{ route('assets.index') }}"><x-secondary-button type="button">Abbrechen</x-secondary-button></a>
                    <x-primary-button>{{ $isNew ? 'Anlegen' : 'Speichern' }}</x-primary-button>
                </div>
            </div>
        </form>
    </x-card>
    </div>

    <div class="space-y-6">
        @if(! $isNew)
            <x-card title="Dateien" description="Scans und Belege (z. B. Fuehrerschein-Foto).">
                @if($asset->attachments->isEmpty())
                    <p class="text-sm text-slate-500">Noch keine Dateien.</p>
                @else
                    <ul class="divide-y divide-slate-100">
                        @foreach($asset->attachments as $a)
                            <li class="py-2 flex items-center justify-between gap-2 text-sm">
                                <div class="min-w-0">
                                    <a href="{{ route('attachments.download', $a) }}" class="font-medium text-indigo-600 hover:text-indigo-500 truncate block" target="_blank">{{ $a->original_name }}</a>
                                    <div class="text-xs text-slate-500">{{ $a->sizeFormatted() }} · {{ $a->mime_type }} · {{ $a->created_at->format('d.m.Y H:i') }}</div>
                                </div>
                                <form method="POST" action="{{ route('attachments.destroy', $a) }}" onsubmit="return confirm('Datei wirklich loeschen?')">
                                    @csrf @method('DELETE')
                                    <button class="text-xs text-rose-600 hover:text-rose-500">loeschen</button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if(auth()->user()->hasPermission('assets.manage'))
                    <form method="POST" enctype="multipart/form-data" action="{{ route('attachments.store', ['type'=>'asset', 'id'=>$asset->id]) }}" class="mt-4 border-t border-slate-200 pt-4 space-y-2">
                        @csrf
                        <label class="block text-xs font-medium text-slate-600">Datei hochladen (PDF, JPG, PNG, DOCX, max. 15 MB)</label>
                        <input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png,.webp,.heic,.heif,.doc,.docx,.xls,.xlsx,.txt,.csv" required
                            class="block w-full text-sm text-slate-700 file:mr-4 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100">
                        <input type="text" name="label" placeholder="Beschriftung (optional)" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Hochladen</button>
                        <x-input-error :messages="$errors->get('file')" />
                    </form>
                @endif
            </x-card>
        @endif
    </div>
    </div>
</x-app-layout>
