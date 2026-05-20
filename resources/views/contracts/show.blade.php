<x-app-layout>
    <x-slot name="header">{{ $contract->name }}</x-slot>
    <x-slot name="subheader">{{ $contract->party ?: 'Vertrag' }} · {{ $contract->category ?: '—' }}</x-slot>

    <x-breadcrumbs :items="[
        ['label' => 'Vertraege', 'url' => route('contracts.index')],
        ['label' => $contract->name],
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
                    @if(auth()->user()->hasPermission('contracts.manage'))
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
                        <dt class="text-xs uppercase text-slate-500">Kategorie</dt>
                        <dd class="text-slate-900">{{ $contract->category ?: '—' }}</dd>
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
        </div>
    </div>
</x-app-layout>
