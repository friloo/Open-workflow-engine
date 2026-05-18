<x-app-layout>
    <x-slot name="header">Assets</x-slot>
    <x-slot name="subheader">Fuehrerscheine, Unterweisungen, Zertifikate — jeweils mit Ablaufdatum und zugehoerigem Workflow.</x-slot>

    <div class="mb-4 flex flex-col sm:flex-row gap-3 sm:items-center sm:justify-between">
        <form method="GET" class="flex gap-2">
            <x-text-input name="q" value="{{ $search }}" placeholder="Name oder Typ..." />
            <select name="type" class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Alle Typen</option>
                @foreach($types as $t)<option value="{{ $t }}" @selected($type===$t)>{{ $t }}</option>@endforeach
            </select>
            <select name="status" class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Alle Status</option>
                <option value="active" @selected($status==='active')>aktiv</option>
                <option value="expired" @selected($status==='expired')>abgelaufen</option>
                <option value="archived" @selected($status==='archived')>archiviert</option>
            </select>
            <x-secondary-button type="submit">Filtern</x-secondary-button>
        </form>
        @if(auth()->user()->hasPermission('assets.manage'))
            <div class="flex gap-2">
                <form method="POST" enctype="multipart/form-data" action="{{ route('assets.import') }}" class="inline-flex items-center gap-2">
                    @csrf
                    <input type="file" name="csv" accept=".csv" required class="text-xs file:mr-2 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100">
                    <button class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">CSV-Import</button>
                </form>
                <a href="{{ route('assets.create') }}"><x-primary-button type="button">Neues Asset</x-primary-button></a>
            </div>
        @endif
    </div>

    @if(session('importErrors'))
        <div class="mb-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">
            <strong>Hinweise:</strong>
            <ul class="list-disc ps-4 mt-1">@foreach(session('importErrors') as $err)<li>{{ $err }}</li>@endforeach</ul>
        </div>
    @endif

    <x-card>
        @if($assets->isEmpty())
            <p class="text-sm text-slate-500">Keine Assets.</p>
            <p class="mt-2 text-xs text-slate-500">CSV-Spalten: <code>user_email;name;type;valid_until;lead_time_days;notes</code></p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead><tr class="text-left text-xs font-semibold uppercase text-slate-500">
                        <th class="py-2 pr-4">Asset</th>
                        <th class="py-2 pr-4">Typ</th>
                        <th class="py-2 pr-4">Inhaber</th>
                        <th class="py-2 pr-4">Gueltig bis</th>
                        <th class="py-2 pr-4">Vorlauf</th>
                        <th class="py-2 pr-4">Workflow</th>
                        <th class="py-2 pr-4">Status</th>
                        <th class="py-2"></th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($assets as $a)
                            @php($overdue = $a->valid_until && $a->valid_until->isPast())
                            @php($dueSoon = $a->valid_until && ! $overdue && $a->valid_until->lte(now()->addDays($a->lead_time_days)))
                            <tr>
                                <td class="py-3 pr-4">
                                    <div class="font-medium text-slate-900">{{ $a->name }}</div>
                                    <div class="text-xs text-slate-500">{{ $a->notes }}</div>
                                </td>
                                <td class="py-3 pr-4 text-slate-700">{{ $a->type }}</td>
                                <td class="py-3 pr-4 text-slate-700">{{ $a->user?->name }}</td>
                                <td class="py-3 pr-4 text-xs">
                                    @if($a->valid_until)
                                        <span class="@if($overdue) text-rose-600 font-medium @elseif($dueSoon) text-amber-700 font-medium @else text-slate-700 @endif">
                                            {{ $a->valid_until->format('d.m.Y') }}
                                        </span>
                                    @else —
                                    @endif
                                </td>
                                <td class="py-3 pr-4 text-xs text-slate-700">{{ $a->lead_time_days }} Tage</td>
                                <td class="py-3 pr-4 text-xs">
                                    @if($a->workflow)
                                        <span class="inline-flex items-center rounded-md bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700">{{ $a->workflow->name }}</span>
                                    @else <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="py-3 pr-4">
                                    @switch($a->status)
                                        @case('active')<span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">aktiv</span>@break
                                        @case('expired')<span class="inline-flex items-center rounded-full bg-rose-50 px-2 py-0.5 text-xs font-medium text-rose-700">abgelaufen</span>@break
                                        @case('archived')<span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">archiviert</span>@break
                                    @endswitch
                                </td>
                                <td class="py-3 text-right">
                                    @if(auth()->user()->hasPermission('assets.manage'))
                                        <a href="{{ route('assets.edit', $a) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">Bearbeiten</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $assets->links() }}</div>
        @endif
    </x-card>
</x-app-layout>
