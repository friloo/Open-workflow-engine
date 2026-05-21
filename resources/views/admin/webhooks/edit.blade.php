@php($isNew = ! $webhook->exists)
<x-app-layout>
    <x-slot name="header">{{ $isNew ? 'Neuer Webhook' : $webhook->name }}</x-slot>

    <div class="mb-4"><a href="{{ route('admin.webhooks.index') }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; Webhooks</a></div>

    <x-card>
        <form method="POST" action="{{ $isNew ? route('admin.webhooks.store') : route('admin.webhooks.update', $webhook) }}"
            x-data="{ headers: @js($webhook->headers ?? []) }">
            @csrf
            @if(! $isNew) @method('PUT') @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="name" value="Name" />
                    <x-text-input id="name" name="name" value="{{ old('name', $webhook->name) }}" required />
                    <x-input-error :messages="$errors->get('name')" />
                </div>
                <div>
                    <x-input-label for="url" value="URL" />
                    <x-text-input id="url" name="url" type="url" value="{{ old('url', $webhook->url) }}" required placeholder="https://hooks.example.com/owe" />
                    <x-input-error :messages="$errors->get('url')" />
                </div>
                <div class="sm:col-span-2">
                    <x-input-label value="Ereignisse" />
                    <div class="mt-1 grid grid-cols-2 gap-2">
                        @foreach($allEvents as $event)
                            <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                                <input type="checkbox" name="events[]" value="{{ $event }}" @checked(in_array($event, old('events', $webhook->events ?? []))) class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                <code class="text-xs">{{ $event }}</code>
                            </label>
                        @endforeach
                    </div>
                    <x-input-error :messages="$errors->get('events')" />
                </div>
                <div class="sm:col-span-2">
                    <x-input-label for="secret" value="HMAC-Secret (optional)" />
                    <x-text-input id="secret" name="secret" type="password" placeholder="@if($webhook->secret)(unverändert lassen)@endif" autocomplete="new-password" />
                    <p class="mt-1 text-xs text-slate-500">Wird verschlüsselt gespeichert. Header <code>X-OWE-Signature</code> mit <code>sha256=&lt;hex&gt;</code> über dem JSON-Body.</p>
                </div>
                <div class="sm:col-span-2">
                    <x-input-label value="Zusatz-Header" />
                    <template x-for="(h, hi) in headers" :key="hi">
                        <div class="mb-1 grid grid-cols-5 gap-1">
                            <input type="text" :name="`headers[${hi}][key]`" x-model="h.key" placeholder="Header" class="col-span-2 rounded-lg border-slate-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono">
                            <input type="text" :name="`headers[${hi}][value]`" x-model="h.value" placeholder="Wert" class="col-span-2 rounded-lg border-slate-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono">
                            <button type="button" @click="headers.splice(hi,1)" class="text-xs text-rose-600 hover:text-rose-500">×</button>
                        </div>
                    </template>
                    <button type="button" @click="headers.push({key:'', value:''})" class="mt-1 w-full rounded-lg border border-dashed border-slate-300 px-2 py-1 text-xs text-slate-600 hover:bg-slate-50">+ Header</button>
                </div>
                <div>
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $webhook->is_active ?? true)) class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                        Aktiv
                    </label>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <a href="{{ route('admin.webhooks.index') }}"><x-secondary-button type="button">Abbrechen</x-secondary-button></a>
                <x-primary-button>{{ $isNew ? 'Anlegen' : 'Speichern' }}</x-primary-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
