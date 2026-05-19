<x-app-layout>
    <x-slot name="header">{{ $webhook->exists ? 'Eingehender Webhook bearbeiten' : 'Neuer eingehender Webhook' }}</x-slot>
    <x-slot name="subheader">Empfaengt POST-Requests von externen Systemen und startet einen Workflow.</x-slot>

    @if($webhook->exists)
        <x-card title="Endpoint" description="So adressiert dein externes System OWE.">
            <div class="space-y-2">
                <div>
                    <label class="block text-xs font-medium text-slate-600">URL</label>
                    <code class="block break-all rounded bg-slate-100 px-2 py-1 text-xs">{{ url('/api/incoming/'.$webhook->token) }}</code>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600">Methode</label>
                    <code class="rounded bg-slate-100 px-2 py-1 text-xs">POST · Content-Type: application/json</code>
                </div>
                @if($webhook->secret)
                    <div>
                        <label class="block text-xs font-medium text-slate-600">Signatur-Header</label>
                        <code class="rounded bg-slate-100 px-2 py-1 text-xs">X-OWE-Signature: sha256=&lt;HMAC-SHA256 des Bodys mit Secret&gt;</code>
                    </div>
                @endif
            </div>
            <form method="POST" action="{{ route('admin.incoming-webhooks.rotate', $webhook) }}" class="mt-3" onsubmit="return confirm('Token rotieren? Alte URL wird ungueltig.')">
                @csrf
                <x-secondary-button>Token rotieren</x-secondary-button>
            </form>
        </x-card>
    @endif

    <form method="POST" action="{{ $webhook->exists ? route('admin.incoming-webhooks.update', $webhook) : route('admin.incoming-webhooks.store') }}"
          x-data='@json(["mappings" => $webhook->field_mappings ?: []])'>
        @csrf
        @if($webhook->exists) @method('PUT') @endif

        <x-card title="Allgemein">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="name" value="Name" />
                    <x-text-input id="name" name="name" :value="old('name', $webhook->name)" required />
                </div>
                <div>
                    <x-input-label for="workflow_id" value="Workflow" />
                    <select id="workflow_id" name="workflow_id" required class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">— Workflow waehlen —</option>
                        @foreach($workflows as $wf)
                            <option value="{{ $wf->id }}" @selected((int) old('workflow_id', $webhook->workflow_id) === $wf->id)>{{ $wf->name }} ({{ $wf->status }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="secret" value="HMAC-Secret (optional, leer = unveraendert)" />
                    <x-text-input id="secret" name="secret" type="password" />
                    <p class="mt-1 text-xs text-slate-500">Bei gesetztem Secret muss der Aufrufer einen <code>X-OWE-Signature</code>-Header mitschicken (HMAC-SHA256 des Bodys).</p>
                </div>
                <div class="flex items-end">
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $webhook->is_active)) class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                        Webhook aktiv
                    </label>
                </div>
            </div>
        </x-card>

        <x-card title="Field-Mapping" description="Wo im eingehenden JSON liegen die Werte, und in welches Formularfeld sollen sie wandern? Pfad in Punktnotation (data.email, order.items.0.sku).">
            <div class="space-y-2">
                <template x-for="(m, idx) in mappings" :key="idx">
                    <div class="grid grid-cols-12 gap-2">
                        <input type="text" :name="`field_mappings[${idx}][path]`" x-model="m.path" placeholder="data.email"
                               class="col-span-5 rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono">
                        <input type="text" :name="`field_mappings[${idx}][field]`" x-model="m.field" placeholder="requester_email"
                               class="col-span-6 rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono">
                        <button type="button" @click="mappings.splice(idx,1)" class="col-span-1 text-xs text-rose-600 hover:text-rose-500">×</button>
                    </div>
                </template>
                <button type="button" @click="mappings.push({path:'',field:''})"
                        class="w-full rounded-lg border border-dashed border-slate-300 px-3 py-2 text-sm text-slate-600 hover:bg-slate-50">+ Mapping</button>
            </div>
            <p class="mt-3 text-xs text-slate-500">Der vollstaendige Payload steht ausserdem als <code>@{{ webhook_payload }}</code> zur Verfuegung.</p>
        </x-card>

        <div class="flex justify-between">
            <a href="{{ route('admin.incoming-webhooks.index') }}" class="text-sm text-slate-600 hover:text-slate-900">Abbrechen</a>
            <x-primary-button>Speichern</x-primary-button>
        </div>
    </form>

    @if($webhook->exists)
        <form method="POST" action="{{ route('admin.incoming-webhooks.destroy', $webhook) }}" class="mt-6" onsubmit="return confirm('Webhook loeschen?')">
            @csrf @method('DELETE')
            <button class="text-sm text-rose-600 hover:text-rose-500">Webhook loeschen</button>
        </form>
    @endif
</x-app-layout>
