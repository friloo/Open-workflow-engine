@php
    $user = auth()->user();
    $candidates = \App\Models\User::where('is_active', true)
        ->where('id', '!=', $user->id)
        ->orderBy('name')->limit(500)->get(['id', 'name', 'email']);
    $isActive = $user->activeDelegate() !== null;
@endphp

@if($isActive)
    <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">
        <strong>Vertretung aktiv</strong> · {{ $user->delegate->name ?? '—' }} ({{ $user->delegate->email ?? '' }})
        · {{ $user->delegate_from?->format('d.m.Y') }} – {{ $user->delegate_to?->format('d.m.Y') }}
        @if($user->delegate_reason)<div class="text-xs mt-1">{{ $user->delegate_reason }}</div>@endif
    </div>
@elseif($user->delegate_user_id)
    <div class="mb-4 rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">
        Vertretung geplant für {{ $user->delegate_from?->format('d.m.Y') }} – {{ $user->delegate_to?->format('d.m.Y') }}
        an {{ $user->delegate->name ?? '—' }}.
    </div>
@endif

<form method="POST" action="{{ route('profile.delegation.update') }}" class="space-y-4">
    @csrf
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="md:col-span-2">
            <x-input-label for="delegate_user_id" value="Vertretung durch" />
            <select id="delegate_user_id" name="delegate_user_id"
                    class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">— keine —</option>
                @foreach($candidates as $c)
                    <option value="{{ $c->id }}" @selected((int) old('delegate_user_id', $user->delegate_user_id) === $c->id)>{{ $c->name }} ({{ $c->email }})</option>
                @endforeach
            </select>
        </div>
        <div>
            <x-input-label for="delegate_from" value="Von" />
            <x-text-input id="delegate_from" name="delegate_from" type="date" :value="old('delegate_from', $user->delegate_from?->format('Y-m-d'))" />
        </div>
        <div>
            <x-input-label for="delegate_to" value="Bis (inkl.)" />
            <x-text-input id="delegate_to" name="delegate_to" type="date" :value="old('delegate_to', $user->delegate_to?->format('Y-m-d'))" />
        </div>
        <div class="md:col-span-2">
            <x-input-label for="delegate_reason" value="Grund (optional, intern)" />
            <x-text-input id="delegate_reason" name="delegate_reason" :value="old('delegate_reason', $user->delegate_reason)" placeholder="z. B. Urlaub" />
        </div>
    </div>

    <x-primary-button>Speichern</x-primary-button>
    <p class="text-xs text-slate-500">Neue Aufgaben gehen während des Zeitraums direkt an die Vertretung. Bereits offene Aufgaben bleiben unverändert; sie kannst du im Aufgaben-Bereich manuell weiterleiten.</p>
</form>

@if($user->delegate_user_id)
    <form method="POST" action="{{ route('profile.delegation.clear') }}" class="mt-3" onsubmit="return confirm('Vertretung jetzt beenden?')">
        @csrf @method('DELETE')
        <button type="submit" class="text-sm text-rose-600 hover:text-rose-500">Vertretung beenden</button>
    </form>
@endif
