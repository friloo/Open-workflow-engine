<x-app-layout>
    <x-slot name="header">Eingehende Webhooks</x-slot>
    <x-slot name="subheader">Externe Systeme rufen einen Endpoint auf — der zugeordnete Workflow startet automatisch mit gemappten Feldern.</x-slot>

    <div class="mb-4 flex justify-end">
        <a href="{{ route('admin.incoming-webhooks.create') }}"><x-primary-button type="button">Neuer Webhook</x-primary-button></a>
    </div>

    <x-card>
        @if($webhooks->isEmpty())
            <x-empty-state icon="cog" title="Noch keine Webhooks"
                description="Lege einen Webhook an, um einen Workflow per HTTP-POST aus einem Drittsystem zu starten." />
        @else
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead><tr class="text-left text-xs font-semibold uppercase text-slate-500">
                    <th class="py-2 pr-4">Name</th>
                    <th class="py-2 pr-4">Workflow</th>
                    <th class="py-2 pr-4">Status</th>
                    <th class="py-2 pr-4">Calls</th>
                    <th class="py-2 pr-4">Zuletzt</th>
                    <th class="py-2"></th>
                </tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($webhooks as $w)
                        <tr>
                            <td class="py-3 pr-4 font-medium text-slate-900">{{ $w->name }}</td>
                            <td class="py-3 pr-4 text-xs text-slate-700">{{ $w->workflow?->name ?? '—' }}</td>
                            <td class="py-3 pr-4">
                                @if($w->is_active)
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">aktiv</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">pausiert</span>
                                @endif
                                @if($w->secret)<span class="ms-1 inline-flex items-center rounded-md bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">HMAC</span>@endif
                                @if($w->failure_count > 0)
                                    <div class="text-xs text-rose-700 mt-1" title="{{ $w->last_error }}">{{ $w->failure_count }} Fehler</div>
                                @endif
                            </td>
                            <td class="py-3 pr-4 text-xs">{{ $w->call_count }}</td>
                            <td class="py-3 pr-4 text-xs text-slate-500">{{ $w->last_called_at?->diffForHumans() ?? '—' }}</td>
                            <td class="py-3 text-right">
                                <a href="{{ route('admin.incoming-webhooks.edit', $w) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">Bearbeiten</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="mt-4">{{ $webhooks->links() }}</div>
        @endif
    </x-card>
</x-app-layout>
