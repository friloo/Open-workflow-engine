<x-app-layout>
    <x-slot name="header">Secrets</x-slot>
    <x-slot name="subheader">API-Tokens, Bearer-Keys etc. Im Designer als <code>@{{ secret.key }}</code> verwendbar.</x-slot>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
            <x-card>
                @if($secrets->isEmpty())
                    <x-empty-state title="Noch keine Secrets" description="Lege oben einen verschluesselten Wert an (API-Keys, Tokens) — in HTTP-Knoten als @{{ secret.NAME }} verwendbar." />
                @else
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead><tr class="text-left text-xs font-semibold uppercase text-slate-500">
                            <th class="py-2 pr-4">Key</th>
                            <th class="py-2 pr-4">Beschreibung</th>
                            <th class="py-2 pr-4">Angelegt</th>
                            <th class="py-2"></th>
                        </tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($secrets as $s)
                                @php($placeholder = '{{ secret.'.$s->key.' }}')
                                <tr>
                                    <td class="py-3 pr-4"><code class="rounded bg-slate-100 px-2 py-0.5 text-xs">{{ $placeholder }}</code></td>
                                    <td class="py-3 pr-4 text-slate-700 text-xs">{{ $s->description }}</td>
                                    <td class="py-3 pr-4 text-xs text-slate-500">{{ $s->created_at->diffForHumans() }}@if($s->creator) · {{ $s->creator->name }}@endif</td>
                                    <td class="py-3 text-right">
                                        <form method="POST" action="{{ route('admin.secrets.update', $s) }}" class="inline-flex items-center gap-1">
                                            @csrf @method('PUT')
                                            <input type="password" name="value" placeholder="neuer Wert" class="rounded-lg border-slate-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            <button class="text-xs text-indigo-600 hover:text-indigo-500">rotieren</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.secrets.destroy', $s) }}" class="inline ms-2" onsubmit="return confirm('Secret loeschen?')">
                                            @csrf @method('DELETE')
                                            <button class="text-xs text-rose-600 hover:text-rose-500">loeschen</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
                <div class="mt-4">{{ $secrets->links() }}</div>
            </x-card>
        </div>

        <x-card title="Neuer Secret">
            <form method="POST" action="{{ route('admin.secrets.store') }}" class="space-y-3">
                @csrf
                <div>
                    <x-input-label for="key" value="Key" />
                    <x-text-input id="key" name="key" placeholder="z. B. jira_token" required />
                    <p class="mt-1 text-xs text-slate-500">Nur a-z, 0-9, _. Wird zu <code class="text-xs">@{{ secret.&lt;key&gt; }}</code> im Designer.</p>
                    <x-input-error :messages="$errors->get('key')" />
                </div>
                <div>
                    <x-input-label for="value" value="Wert" />
                    <x-text-input id="value" name="value" type="password" required autocomplete="new-password" />
                    <p class="mt-1 text-xs text-slate-500">Wird verschluesselt gespeichert.</p>
                </div>
                <div>
                    <x-input-label for="description" value="Beschreibung" />
                    <x-text-input id="description" name="description" placeholder="z. B. Jira API Token Prod" />
                </div>
                <x-primary-button>Anlegen</x-primary-button>
            </form>
        </x-card>
    </div>
</x-app-layout>
