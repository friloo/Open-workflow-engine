<x-app-layout>
    <x-slot name="header">Versions-Vergleich · {{ $workflow->name }}</x-slot>
    <x-slot name="subheader">Welche Knoten haben sich zwischen zwei Versionen geaendert?</x-slot>

    <x-breadcrumbs :items="[
        ['title' => 'Workflows', 'url' => route('workflows.index')],
        ['title' => $workflow->name, 'url' => route('workflows.design', $workflow)],
        ['title' => 'Versionen', 'url' => route('workflows.versions', $workflow)],
        ['title' => 'Vergleich'],
    ]" />

    <x-card>
        <form method="GET" class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
            <div>
                <x-input-label for="a" value="Version A (Basis)" />
                <select id="a" name="a" required
                        class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">— bitte waehlen —</option>
                    @foreach($versionsList as $v)
                        <option value="{{ $v->id }}" @selected($verA?->id === $v->id)>
                            v{{ $v->version_number }} · {{ $v->created_at->format('d.m.Y') }} ·
                            {{ \Illuminate\Support\Str::limit($v->change_summary ?: '—', 40) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-input-label for="b" value="Version B (Vergleich)" />
                <select id="b" name="b" required
                        class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">— bitte waehlen —</option>
                    @foreach($versionsList as $v)
                        <option value="{{ $v->id }}" @selected($verB?->id === $v->id)>
                            v{{ $v->version_number }} · {{ $v->created_at->format('d.m.Y') }} ·
                            {{ \Illuminate\Support\Str::limit($v->change_summary ?: '—', 40) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-primary-button>Vergleichen</x-primary-button>
            </div>
        </form>
    </x-card>

    @if($diff)
        <x-card title="Zusammenfassung">
            <div class="flex flex-wrap gap-2 text-sm">
                <span class="rounded-full bg-emerald-50 px-3 py-1 text-emerald-700">+ {{ $diff['counts']['added'] }} neu</span>
                <span class="rounded-full bg-rose-50 px-3 py-1 text-rose-700">− {{ $diff['counts']['removed'] }} entfernt</span>
                <span class="rounded-full bg-amber-50 px-3 py-1 text-amber-700">~ {{ $diff['counts']['modified'] }} geaendert</span>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-slate-600">= {{ $diff['counts']['unchanged'] }} unveraendert</span>
            </div>
        </x-card>

        <x-card title="Knoten-Aenderungen">
            <ul class="divide-y divide-slate-100">
                @foreach($diff['nodes'] as $n)
                    @if($n['status'] === 'unchanged') @continue @endif
                    <li class="py-3">
                        <div class="flex items-baseline justify-between gap-3">
                            <div>
                                <span class="font-mono text-xs text-slate-500">{{ $n['step_key'] }}</span>
                                <span class="font-medium text-slate-900 ms-2">{{ $n['label_b'] ?? $n['label_a'] ?? '—' }}</span>
                                <span class="ms-2 text-xs text-slate-500">{{ $n['class'] }}</span>
                            </div>
                            <div>
                                @switch($n['status'])
                                    @case('added')<span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">neu</span>@break
                                    @case('removed')<span class="rounded-full bg-rose-50 px-2 py-0.5 text-xs font-medium text-rose-700">entfernt</span>@break
                                    @case('modified')<span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">geaendert</span>@break
                                @endswitch
                            </div>
                        </div>

                        @if(! empty($n['fields']))
                            <table class="mt-2 w-full text-xs">
                                <thead>
                                    <tr class="text-left text-[10px] uppercase text-slate-500">
                                        <th class="py-1 pr-3 w-1/4">Feld</th>
                                        <th class="py-1 pr-3 w-3/8">Vorher (A)</th>
                                        <th class="py-1 w-3/8">Nachher (B)</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-50">
                                    @foreach($n['fields'] as $f)
                                        <tr>
                                            <td class="py-1 pr-3 align-top"><code>{{ $f['key'] }}</code></td>
                                            <td class="py-1 pr-3 align-top text-rose-700">
                                                <pre class="whitespace-pre-wrap break-all text-[10px]">{{ is_scalar($f['before']) ? (string) $f['before'] : json_encode($f['before'], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre>
                                            </td>
                                            <td class="py-1 align-top text-emerald-700">
                                                <pre class="whitespace-pre-wrap break-all text-[10px]">{{ is_scalar($f['after']) ? (string) $f['after'] : json_encode($f['after'], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </li>
                @endforeach
            </ul>
        </x-card>

        @if(! empty($diff['form_changes']))
            <x-card title="Formular-Schema-Aenderungen">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="text-left text-[10px] uppercase text-slate-500">
                            <th class="py-1 pr-3">Feld</th>
                            <th class="py-1 pr-3">Vorher</th>
                            <th class="py-1">Nachher</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        @foreach($diff['form_changes'] as $f)
                            <tr>
                                <td class="py-1 pr-3 align-top"><code>{{ $f['key'] }}</code></td>
                                <td class="py-1 pr-3 align-top text-rose-700"><pre class="text-[10px] whitespace-pre-wrap break-all">{{ json_encode($f['before'], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre></td>
                                <td class="py-1 align-top text-emerald-700"><pre class="text-[10px] whitespace-pre-wrap break-all">{{ json_encode($f['after'], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-card>
        @endif
    @endif
</x-app-layout>
