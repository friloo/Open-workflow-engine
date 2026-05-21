<x-app-layout>
    <x-slot name="header">API-Tokens fuer {{ $managedUser->name }}</x-slot>
    <x-slot name="subheader">{{ $managedUser->email }}@if($managedUser->isServiceAccount()) · <span class="text-amber-700">Service-Account</span>@endif</x-slot>

    <x-breadcrumbs :items="[
        ['title' => 'Benutzer', 'url' => route('admin.users.index')],
        ['title' => $managedUser->name, 'url' => route('admin.users.edit', $managedUser)],
        ['title' => 'API-Tokens'],
    ]" />

    @if($plain)
        <x-card>
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                <p class="text-sm text-amber-800 font-semibold">Neuer Token (wird nur jetzt angezeigt):</p>
                <code class="mt-2 block break-all rounded bg-white border border-amber-200 p-2 text-xs">{{ $plain }}</code>
                <p class="mt-2 text-xs text-amber-800">An die Integration weitergeben + im Passwort-Manager ablegen. Nach Verlassen ist der Klartext weg.</p>
            </div>
        </x-card>
    @endif

    <x-card title="Neuen Token fuer {{ $managedUser->name }} erzeugen">
        <form method="POST" action="{{ route('admin.users.tokens.store', $managedUser) }}" class="space-y-4">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="md:col-span-2">
                    <x-input-label for="name" value="Name (was nutzt den Token?)" />
                    <x-text-input id="name" name="name" required placeholder="z. B. n8n-Bridge / PowerBI-Reports / DATEV-Sync" />
                    <x-input-error :messages="$errors->get('name')" />
                </div>
                <div>
                    <x-input-label for="expires_in_days" value="Ablauf (Tage, leer = unbegrenzt)" />
                    <x-text-input id="expires_in_days" name="expires_in_days" type="number" min="1" max="3650" />
                </div>
            </div>
            <div>
                <x-input-label value="Berechtigungen (leer = alle Rechte des Benutzers)" />
                <div class="mt-2 grid grid-cols-2 md:grid-cols-3 gap-1.5">
                    @forelse($permissions as $p)
                        <label class="inline-flex items-center gap-1.5 rounded-md border border-slate-200 px-2 py-1 text-xs has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50">
                            <input type="checkbox" name="abilities[]" value="{{ $p->slug }}" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="font-mono">{{ $p->slug }}</span>
                        </label>
                    @empty
                        <p class="col-span-full text-xs text-slate-500">Der Benutzer hat aktuell keine spezifischen Permissions — Token bekommt nur die Default-Ability `*` falls leer gelassen.</p>
                    @endforelse
                </div>
                <x-input-error :messages="$errors->get('abilities')" />
                <p class="mt-2 text-xs text-slate-500">Token kann nie mehr als der Benutzer selbst. Admin-Override gilt nicht — falls du den Benutzer broader machen willst, gib ihm zuerst die passende Rolle.</p>
            </div>
            <x-primary-button>Token erzeugen</x-primary-button>
        </form>
    </x-card>

    <x-card title="Aktive Tokens">
        @if($tokens->isEmpty())
            <p class="text-sm text-slate-500">Noch keine Tokens fuer diesen Benutzer.</p>
        @else
            <table class="min-w-full text-sm divide-y divide-slate-200">
                <thead>
                    <tr class="text-left text-xs uppercase text-slate-500">
                        <th class="py-2 pr-4">Name</th>
                        <th class="py-2 pr-4">Praefix</th>
                        <th class="py-2 pr-4">Abilities</th>
                        <th class="py-2 pr-4">Zuletzt benutzt</th>
                        <th class="py-2 pr-4">Ablauf</th>
                        <th class="py-2 pr-4">Status</th>
                        <th class="py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($tokens as $t)
                        <tr class="{{ $t->revoked_at ? 'opacity-60' : '' }}">
                            <td class="py-2 pr-4 font-medium text-slate-900">{{ $t->name }}</td>
                            <td class="py-2 pr-4"><code class="text-xs">{{ $t->prefix }}…</code></td>
                            <td class="py-2 pr-4 text-xs">
                                @if(empty($t->abilities))
                                    <em>alle (User-Rechte)</em>
                                @else
                                    @foreach($t->abilities as $a)
                                        <code class="text-[10px] bg-slate-100 px-1 py-0.5 rounded">{{ $a }}</code>
                                    @endforeach
                                @endif
                            </td>
                            <td class="py-2 pr-4 text-xs text-slate-500">{{ $t->last_used_at?->diffForHumans() ?: '—' }}</td>
                            <td class="py-2 pr-4 text-xs text-slate-500">{{ $t->expires_at?->format('d.m.Y') ?: 'unbegrenzt' }}</td>
                            <td class="py-2 pr-4 text-xs">
                                @if($t->revoked_at)
                                    <span class="text-rose-700">widerrufen</span>
                                @elseif($t->expires_at && $t->expires_at->isPast())
                                    <span class="text-amber-700">abgelaufen</span>
                                @else
                                    <span class="text-emerald-700">aktiv</span>
                                @endif
                            </td>
                            <td class="py-2 pr-4 text-right">
                                @unless($t->revoked_at)
                                    <form method="POST" action="{{ route('admin.users.tokens.destroy', [$managedUser, $t]) }}" class="inline"
                                          onsubmit="return confirm('Token {{ addslashes($t->name) }} widerrufen?')">
                                        @csrf @method('DELETE')
                                        <button class="text-xs text-rose-600 hover:text-rose-500">Widerrufen</button>
                                    </form>
                                @endunless
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-card>
</x-app-layout>
