<x-app-layout>
    <x-slot name="header">
        Vertragsmanagement
        <x-help-hint topic="contracts" label="Anleitung Vertragsmanagement" />
    </x-slot>
    <x-slot name="subheader">Verträge mit Laufzeit, Kündigungsfrist und automatischer Wiedervorlage.</x-slot>

    <form method="GET" class="mb-4 flex flex-wrap items-center gap-2">
        @php
            $chips = [
                ['key' => 'all', 'label' => 'Alle', 'tone' => 'slate'],
                ['key' => 'active', 'label' => 'Aktiv', 'tone' => 'emerald'],
                ['key' => 'notice_due', 'label' => 'Kündigungsfrist erreicht', 'tone' => 'amber'],
                ['key' => 'expired', 'label' => 'Abgelaufen', 'tone' => 'rose'],
            ];
        @endphp
        @foreach($chips as $chip)
            @php
                $active = $filter === $chip['key'];
                $count = $counts[$chip['key']] ?? 0;
                $cls = match($chip['tone']) {
                    'emerald' => $active ? 'bg-emerald-600 text-white' : 'bg-white text-emerald-700 border border-emerald-200 hover:bg-emerald-50',
                    'amber' => $active ? 'bg-amber-500 text-white' : 'bg-white text-amber-700 border border-amber-200 hover:bg-amber-50',
                    'rose' => $active ? 'bg-rose-600 text-white' : 'bg-white text-rose-700 border border-rose-200 hover:bg-rose-50',
                    default => $active ? 'bg-slate-700 text-white' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-50',
                };
            @endphp
            <a href="{{ route('contracts.index', ['filter' => $chip['key'], 'q' => $q ?: null]) }}"
               class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium transition {{ $cls }}">
                {{ $chip['label'] }}
                <span class="rounded-full px-1.5 py-0.5 text-[10px] {{ $active ? 'bg-white/20' : 'bg-slate-100 text-slate-600' }}">{{ $count }}</span>
            </a>
        @endforeach
        <div class="ms-auto flex items-center gap-2">
            <input type="hidden" name="filter" value="{{ $filter }}">
            <select name="type" class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    onchange="this.form.submit()">
                <option value="0">Alle Arten</option>
                @foreach($types as $t)
                    <option value="{{ $t->id }}" @selected($typeFilter === $t->id)>{{ $t->name }}</option>
                @endforeach
            </select>
            <input type="text" name="q" value="{{ $q }}" placeholder="Vertrag suchen …"
                   class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            @if(auth()->user()->hasPermission('contracts.manage'))
                <a href="{{ route('contract-types.index') }}" class="text-sm text-indigo-600 hover:text-indigo-500">Arten</a>
                <a href="{{ route('contract-templates.index') }}" class="text-sm text-indigo-600 hover:text-indigo-500">Vorlagen</a>
                <a href="{{ route('contracts.create') }}"><x-primary-button type="button">Neuer Vertrag</x-primary-button></a>
            @endif
        </div>
    </form>

    @php($canManage = auth()->user()->hasPermission('contracts.manage'))

    <x-card>
        @if($canManage && $contracts->isNotEmpty())
            <form id="contracts-bulk-form" method="POST" action="{{ route('contracts.bulk') }}"
                  x-data="{ action: '', count: 0, refreshCount() { this.count = document.querySelectorAll('input[name=&quot;contract_ids[]&quot;]:checked').length; } }"
                  @change="refreshCount"
                  class="mb-4 flex flex-wrap items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 p-2 text-xs">
                @csrf
                <span class="text-slate-700"><strong x-text="count"></strong> ausgewählt</span>
                <select name="action" x-model="action"
                        class="rounded border-slate-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">— Bulk-Aktion —</option>
                    <option value="set_owner">Verantwortlichen setzen</option>
                    <option value="attach_case">An Akte heften</option>
                    <option value="detach_case">Aus Akte entfernen</option>
                    <option value="recompute_status">Status neu berechnen</option>
                    <option value="delete">Löschen (Soft-Delete)</option>
                </select>

                <select name="owner_user_id" x-show="action === 'set_owner'" x-cloak
                        class="rounded border-slate-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">— Niemand —</option>
                    @foreach(\App\Models\User::humans()->where('is_active', true)->orderBy('name')->get(['id', 'name']) as $u)
                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                    @endforeach
                </select>

                <select name="document_case_id" x-show="action === 'attach_case' || action === 'detach_case'" x-cloak
                        class="rounded border-slate-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">— Akte wählen —</option>
                    @foreach(\App\Models\DocumentCase::whereNull('closed_at')->orderBy('name')->limit(200)->get(['id', 'name']) as $a)
                        <option value="{{ $a->id }}">{{ $a->name }}</option>
                    @endforeach
                </select>

                <button type="submit" :disabled="count === 0 || ! action"
                        class="rounded-lg bg-indigo-600 px-3 py-1 font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50">
                    Ausführen
                </button>
            </form>
        @endif

        @if($contracts->isEmpty())
            <x-empty-state icon="document" title="Noch keine Verträge"
                description="Lege Wartungs-, Miet- und Versicherungsverträge an — OWE erinnert dich rechtzeitig vor Ablauf der Kündigungsfrist.">
                @if(auth()->user()->hasPermission('contracts.manage'))
                    <a href="{{ route('contracts.create') }}"><x-primary-button type="button">Ersten Vertrag anlegen</x-primary-button></a>
                @endif
                <a href="{{ route('help.show', 'contracts') }}" class="text-sm text-slate-600 hover:text-slate-900">Anleitung lesen</a>
            </x-empty-state>
        @else
            <div class="overflow-x-auto -mx-4 sm:mx-0">
            <table class="min-w-full text-sm divide-y divide-slate-200">
                <thead>
                    <tr class="text-left text-xs uppercase text-slate-500">
                        @if($canManage)
                            <th class="py-2 pr-2 w-8">
                                <input type="checkbox"
                                       @click="document.querySelectorAll('input[name=&quot;contract_ids[]&quot;]').forEach(c => c.checked = $event.target.checked); $event.target.dispatchEvent(new Event('change', {bubbles:true}))"
                                       class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                            </th>
                        @endif
                        <th class="py-2 pr-4">Vertrag</th>
                        <th class="py-2 pr-4">Partner</th>
                        <th class="py-2 pr-4">Status</th>
                        <th class="py-2 pr-4">Ende</th>
                        <th class="py-2 pr-4">Frist erreicht</th>
                        <th class="py-2 pr-4">Verantwortlich</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($contracts as $c)
                        <tr class="hover:bg-slate-50">
                            @if($canManage)
                                <td class="py-3 pr-2">
                                    <input type="checkbox" name="contract_ids[]" value="{{ $c->id }}" form="contracts-bulk-form"
                                           class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                </td>
                            @endif
                            <td class="py-3 pr-4">
                                <a href="{{ route('contracts.show', $c) }}" class="font-medium text-slate-900 hover:text-indigo-600">{{ $c->name }}</a>
                                @if($c->type)
                                    <div class="text-xs text-slate-500 flex items-center gap-1">
                                        <span class="inline-block h-2 w-2 rounded-full" style="background:{{ $c->type->color }}"></span>
                                        {{ $c->type->name }}
                                    </div>
                                @elseif($c->category)
                                    <div class="text-xs text-slate-500">{{ $c->category }}</div>
                                @endif
                            </td>
                            <td class="py-3 pr-4 text-slate-700">{{ $c->party }}</td>
                            <td class="py-3 pr-4">
                                @switch($c->status)
                                    @case('active')<span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Aktiv</span>@break
                                    @case('notice_due')<span class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">Frist erreicht</span>@break
                                    @case('expired')<span class="inline-flex items-center rounded-full bg-rose-50 px-2 py-0.5 text-xs font-medium text-rose-700">Abgelaufen</span>@break
                                @endswitch
                            </td>
                            <td class="py-3 pr-4 text-slate-700">
                                <x-fmt-date :value="$c->end_date" />
                            </td>
                            <td class="py-3 pr-4 text-slate-700 text-xs">
                                <x-fmt-date :value="$c->noticeDeadline()" />
                            </td>
                            <td class="py-3 pr-4 text-xs text-slate-600">{{ $c->owner?->name ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        @endif
    </x-card>

    <div class="mt-4">{{ $contracts->links() }}</div>
</x-app-layout>
