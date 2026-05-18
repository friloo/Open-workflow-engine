<x-app-layout>
    <x-slot name="header">Simulation: {{ $workflow->name }}</x-slot>
    <x-slot name="subheader">Trockenlauf mit Testdaten — keine echten Mails, HTTP-Calls, Webhooks oder Persistierung.</x-slot>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <x-card title="Testdaten">
            <form method="POST" action="{{ route('workflows.simulate.run', $workflow) }}" class="space-y-3">
                @csrf
                @if(empty($formSchema))
                    <p class="text-sm text-slate-500">Dieser Workflow hat kein Formular-Schema. Du kannst trotzdem freie Felder eintragen:</p>
                @endif
                @foreach($formSchema as $f)
                    <div>
                        <label class="block text-xs font-medium text-slate-600">
                            {{ $f['label'] ?? $f['key'] }}
                            <span class="font-mono text-slate-400">{{ $f['key'] }}</span>
                        </label>
                        @php($type = $f['type'] ?? 'text')
                        @if($type === 'textarea')
                            <textarea name="data[{{ $f['key'] }}]" rows="2" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ $inputData[$f['key']] ?? '' }}</textarea>
                        @elseif($type === 'select' && ! empty($f['options']))
                            <select name="data[{{ $f['key'] }}]" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">—</option>
                                @foreach($f['options'] as $opt)<option value="{{ $opt }}" @selected(($inputData[$f['key']] ?? '') === $opt)>{{ $opt }}</option>@endforeach
                            </select>
                        @else
                            <input type="{{ in_array($type, ['number','date']) ? $type : 'text' }}"
                                   name="data[{{ $f['key'] }}]" value="{{ $inputData[$f['key']] ?? '' }}"
                                   class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @endif
                    </div>
                @endforeach

                <details class="rounded border border-slate-200 p-2">
                    <summary class="cursor-pointer text-xs font-medium text-slate-600">Freie Felder hinzufuegen (z. B. doc_attachment_id)</summary>
                    <div class="mt-2 grid grid-cols-2 gap-2"
                         x-data='{ extras: @json(array_filter($inputData, fn($k) => ! collect($formSchema)->pluck("key")->contains($k), ARRAY_FILTER_USE_KEY)) }'>
                        <template x-for="(v, k) in extras" :key="k">
                            <div class="contents">
                                <input type="text" :name="`data[${k}]`" :value="v" :placeholder="k"
                                       class="rounded-md border-slate-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                        </template>
                        <button type="button" @click="let k = prompt('Feldname?'); if(k) extras[k] = ''"
                                class="col-span-2 rounded border border-dashed border-slate-300 px-2 py-1 text-xs text-slate-600 hover:bg-slate-50">+ Feld</button>
                    </div>
                </details>

                <x-primary-button>Simulieren</x-primary-button>
                <a href="{{ route('workflows.edit', $workflow) }}" class="ms-3 text-sm text-slate-600 hover:text-slate-900">Zurueck</a>
            </form>
        </x-card>

        <x-card title="Trace">
            @if($error)
                <div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800">{{ $error }}</div>
            @elseif($trace === null)
                <p class="text-sm text-slate-500">Noch nichts simuliert. Felder links ausfuellen und auf <strong>Simulieren</strong> klicken.</p>
            @elseif(empty($trace))
                <p class="text-sm text-slate-500">Lauf war leer.</p>
            @else
                <ol class="relative border-s border-slate-200 ms-3 space-y-3">
                    @foreach($trace as $t)
                        @php($colors = [
                            'start' => ['bg-emerald-100','text-emerald-700'],
                            'end' => ['bg-slate-100','text-slate-700'],
                            'condition' => ['bg-amber-100','text-amber-700'],
                            'approval' => ['bg-indigo-100','text-indigo-700'],
                            'notify' => ['bg-sky-100','text-sky-700'],
                            'http' => ['bg-violet-100','text-violet-700'],
                            'pdf_render' => ['bg-rose-100','text-rose-700'],
                        ][$t['class'] ?? ''] ?? ['bg-slate-100','text-slate-600'])
                        <li class="ms-4">
                            <span class="absolute -start-2 mt-1 inline-block h-3 w-3 rounded-full {{ $colors[0] }} ring-4 ring-white"></span>
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center rounded-md {{ $colors[0] }} {{ $colors[1] }} px-2 py-0.5 text-xs font-medium">{{ $t['class'] ?? '?' }}</span>
                                <span class="font-medium text-slate-900">{{ $t['label'] ?? '—' }}</span>
                            </div>
                            <div class="mt-0.5 text-sm text-slate-700">{{ $t['action'] ?? '' }}</div>
                            @if(! empty($t['note']))
                                <div class="mt-1 text-xs text-slate-500 italic">{{ $t['note'] }}</div>
                            @endif
                        </li>
                    @endforeach
                </ol>
                <p class="mt-4 text-xs text-slate-500">Hinweis: bei Approval-Knoten nimmt die Simulation immer den „Genehmigt"-Pfad. Fuer „Abgelehnt"-Tests verwendet du am besten Bedingungs-Knoten auf Testfelder.</p>
            @endif
        </x-card>
    </div>
</x-app-layout>
