<x-app-layout>
    <x-slot name="header">API-Tokens</x-slot>
    <x-slot name="subheader">Persönliche Tokens für automatisierte Zugriffe (Header: <code class="font-mono">Authorization: Bearer owe_…</code>).</x-slot>

    @if($plain)
        <x-card>
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                <p class="text-sm text-amber-800 font-semibold">Dein neuer Token (wird nur jetzt angezeigt):</p>
                <code class="mt-2 block break-all rounded bg-white border border-amber-200 p-2 text-xs">{{ $plain }}</code>
                <p class="mt-2 text-xs text-amber-800">Im Passwort-Manager speichern. Nach dem Verlassen der Seite ist der Klartext weg.</p>
            </div>
        </x-card>
    @endif

    <x-card title="Neuen Token erzeugen">
        <form method="POST" action="{{ route('tokens.store') }}" class="space-y-4">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="md:col-span-2">
                    <x-input-label for="name" value="Name (was nutzt den Token?)" />
                    <x-text-input id="name" name="name" required placeholder="z. B. n8n-Bridge" />
                </div>
                <div>
                    <x-input-label for="expires_in_days" value="Ablauf (Tage, leer = unbegrenzt)" />
                    <x-text-input id="expires_in_days" name="expires_in_days" type="number" min="1" max="3650" />
                </div>
            </div>
            <div>
                <x-input-label value="Berechtigungen (leer = alle deine Permissions)" />
                <div class="mt-2 grid grid-cols-2 md:grid-cols-3 gap-1.5">
                    @foreach($permissions as $p)
                        <label class="inline-flex items-center gap-1.5 rounded-md border border-slate-200 px-2 py-1 text-xs has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50">
                            <input type="checkbox" name="abilities[]" value="{{ $p->slug }}" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="font-mono">{{ $p->slug }}</span>
                        </label>
                    @endforeach
                </div>
                <p class="mt-2 text-xs text-slate-500">Der Token kann nie mehr als der Benutzer selbst — die Rolle bleibt also die Obergrenze.</p>
            </div>
            <x-primary-button>Token erzeugen</x-primary-button>
        </form>
    </x-card>

    <x-card title="Aktive Tokens">
        @if($tokens->isEmpty())
            <p class="text-sm text-slate-500">Noch keine Tokens.</p>
        @else
            <div class="overflow-x-auto -mx-4 sm:mx-0">
<table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead><tr class="text-left text-xs font-semibold uppercase text-slate-500">
                    <th class="py-2 pr-4">Name</th>
                    <th class="py-2 pr-4">Prefix</th>
                    <th class="py-2 pr-4">Status</th>
                    <th class="py-2 pr-4">Berechtigungen</th>
                    <th class="py-2 pr-4">Zuletzt</th>
                    <th class="py-2"></th>
                </tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($tokens as $t)
                        <tr>
                            <td class="py-3 pr-4 font-medium text-slate-900">{{ $t->name }}</td>
                            <td class="py-3 pr-4 font-mono text-xs">{{ $t->prefix }}…</td>
                            <td class="py-3 pr-4">
                                @if($t->revoked_at)
                                    <span class="inline-flex items-center rounded-full bg-rose-50 px-2 py-0.5 text-xs font-medium text-rose-700">widerrufen</span>
                                @elseif($t->expires_at && $t->expires_at->isPast())
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">abgelaufen</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">aktiv</span>
                                @endif
                                @if($t->expires_at && ! $t->revoked_at)
                                    <div class="text-xs text-slate-500 mt-1">läuft ab {{ $t->expires_at->format('d.m.Y') }}</div>
                                @endif
                            </td>
                            <td class="py-3 pr-4 text-xs">
                                @if(empty($t->abilities))
                                    <span class="text-slate-500">— alle eigenen —</span>
                                @else
                                    <div class="flex flex-wrap gap-1">
                                        @foreach($t->abilities as $a)
                                            <span class="font-mono inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-xs">{{ $a }}</span>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                            <td class="py-3 pr-4 text-xs text-slate-500">{{ $t->last_used_at?->diffForHumans() ?? '—' }}</td>
                            <td class="py-3 text-right">
                                @if(! $t->revoked_at)
                                    <form method="POST" action="{{ route('tokens.destroy', $t) }}" class="inline" onsubmit="return confirm('Token widerrufen?')">
                                        @csrf @method('DELETE')
                                        <button class="text-sm text-rose-600 hover:text-rose-500">Widerrufen</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
</div>
        @endif
    </x-card>
</x-app-layout>
