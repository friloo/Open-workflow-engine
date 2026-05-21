<x-app-layout>
    <x-slot name="header">Versions-Vergleich: {{ $left->original_name }}</x-slot>
    <x-slot name="subheader">
        Text-für-Text-Diff zwischen zwei Versionen des gleichen Dokuments.
    </x-slot>

    <x-breadcrumbs :items="[
        ['title' => 'Dokumente', 'url' => route('documents.index')],
        ['title' => $left->original_name, 'url' => route('documents.show', $left)],
        ['title' => 'Versions-Vergleich'],
    ]" />

    {{-- Versions-Picker --}}
    <x-card>
        <form method="GET" class="flex flex-wrap items-end gap-4">
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Alte Version</label>
                <select name="_left" onchange="window.location = this.value"
                        class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @foreach($allVersions as $v)
                        <option value="{{ route('documents.diff', ['attachment' => $v, 'other' => $right]) }}" @selected($v->id === $left->id)>
                            v{{ $v->version_number }} · {{ optional($v->created_at)->format('d.m.Y H:i') }}@if($v->uploader) · {{ $v->uploader->name }}@endif
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="text-slate-400 pb-2">↔</div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Neue Version</label>
                <select name="_right" onchange="window.location = this.value"
                        class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @foreach($allVersions as $v)
                        <option value="{{ route('documents.diff', ['attachment' => $left, 'other' => $v]) }}" @selected($v->id === $right->id)>
                            v{{ $v->version_number }} · {{ optional($v->created_at)->format('d.m.Y H:i') }}@if($v->uploader) · {{ $v->uploader->name }}@endif
                        </option>
                    @endforeach
                </select>
            </div>
            @if(! empty($result['stats']))
                <div class="ms-auto flex items-center gap-3 text-sm">
                    <span class="inline-flex items-center gap-1.5 rounded-md bg-emerald-50 px-2 py-1 text-emerald-700"><strong>+{{ $result['stats']['added'] }}</strong> hinzugefügt</span>
                    <span class="inline-flex items-center gap-1.5 rounded-md bg-rose-50 px-2 py-1 text-rose-700"><strong>−{{ $result['stats']['removed'] }}</strong> entfernt</span>
                    <span class="inline-flex items-center gap-1.5 rounded-md bg-slate-100 px-2 py-1 text-slate-700">{{ $result['stats']['unchanged'] }} unverändert</span>
                </div>
            @endif
        </form>
    </x-card>

    @if(! $result['supported'])
        <x-card>
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                <strong>Vergleich nicht möglich:</strong> {{ $result['reason'] }}
            </div>
        </x-card>
    @else
        <x-card>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                {{-- Linke Seite --}}
                <div>
                    <div class="mb-2 flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-slate-900">{{ $result['left_label'] }}</h3>
                        <a href="{{ route('attachments.download', $left) }}" class="text-xs text-indigo-600 hover:text-indigo-500">Download</a>
                    </div>
                    <pre class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-xs leading-relaxed whitespace-pre-wrap overflow-x-auto max-h-[70vh]">@foreach($result['hunks'] as $h)@switch($h['type'])
@case('unchanged')<span class="text-slate-700">{{ $h['left'] }}</span>
@break
@case('removed')<span class="bg-rose-100 text-rose-900 rounded px-0.5">{{ $h['left'] }}</span>
@break
@case('change')<span class="bg-rose-100 text-rose-900 rounded px-0.5">{{ $h['left'] }}</span>
@break
@case('added')<span class="bg-slate-100 text-slate-400 italic">·</span>
@break
@endswitch
@endforeach</pre>
                </div>

                {{-- Rechte Seite --}}
                <div>
                    <div class="mb-2 flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-slate-900">{{ $result['right_label'] }}</h3>
                        <a href="{{ route('attachments.download', $right) }}" class="text-xs text-indigo-600 hover:text-indigo-500">Download</a>
                    </div>
                    <pre class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-xs leading-relaxed whitespace-pre-wrap overflow-x-auto max-h-[70vh]">@foreach($result['hunks'] as $h)@switch($h['type'])
@case('unchanged')<span class="text-slate-700">{{ $h['right'] }}</span>
@break
@case('added')<span class="bg-emerald-100 text-emerald-900 rounded px-0.5">{{ $h['right'] }}</span>
@break
@case('change')<span class="bg-emerald-100 text-emerald-900 rounded px-0.5">{{ $h['right'] }}</span>
@break
@case('removed')<span class="bg-slate-100 text-slate-400 italic">·</span>
@break
@endswitch
@endforeach</pre>
                </div>
            </div>
            <p class="mt-3 text-xs text-slate-500">
                Hinweis: Vergleich erfolgt auf extrahiertem Text. Layout-Änderungen (Schriftarten, Tabellen-Spalten,
                Bilder) werden nicht als Diff dargestellt. Für eine 1:1-Pixel-Ansicht der Versionen die jeweilige
                Datei herunterladen oder im Tab öffnen.
            </p>
        </x-card>
    @endif
</x-app-layout>
