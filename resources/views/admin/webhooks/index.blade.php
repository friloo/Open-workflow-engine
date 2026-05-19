<x-app-layout>
    <x-slot name="header">Webhooks</x-slot>
    <x-slot name="subheader">Bei Workflow-Ereignissen werden HTTP-POSTs an externe Systeme gesendet.</x-slot>

    <div class="mb-4 flex justify-end">
        <a href="{{ route('admin.webhooks.create') }}"><x-primary-button type="button">Neuer Webhook</x-primary-button></a>
    </div>

    <x-card>
        @if($webhooks->isEmpty())
            <x-empty-state title="Noch keine Webhooks" description="Lege oben einen Endpunkt an, um Events aus Workflows nach extern zu schicken." />
        @else
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead><tr class="text-left text-xs font-semibold uppercase text-slate-500">
                    <th class="py-2 pr-4">Name</th>
                    <th class="py-2 pr-4">URL</th>
                    <th class="py-2 pr-4">Ereignisse</th>
                    <th class="py-2 pr-4">Status</th>
                    <th class="py-2 pr-4">Zuletzt</th>
                    <th class="py-2"></th>
                </tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($webhooks as $w)
                        <tr>
                            <td class="py-3 pr-4 font-medium text-slate-900">{{ $w->name }}</td>
                            <td class="py-3 pr-4 text-xs font-mono text-slate-700">{{ \Illuminate\Support\Str::limit($w->url, 60) }}</td>
                            <td class="py-3 pr-4"><div class="flex flex-wrap gap-1">
                                @foreach($w->events as $e)<span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700">{{ $e }}</span>@endforeach
                            </div></td>
                            <td class="py-3 pr-4">
                                @if($w->is_active)<span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">aktiv</span>
                                @else<span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">pausiert</span>
                                @endif
                                @if($w->failure_count > 0)
                                    <span class="ms-1 inline-flex items-center rounded-full bg-rose-50 px-2 py-0.5 text-xs font-medium text-rose-700">{{ $w->failure_count }} Fehler</span>
                                @endif
                            </td>
                            <td class="py-3 pr-4 text-xs text-slate-500">{{ $w->last_called_at?->diffForHumans() ?? '—' }}</td>
                            <td class="py-3 text-right space-x-3">
                                <form method="POST" action="{{ route('admin.webhooks.test', $w) }}" class="inline">@csrf<button class="text-sm text-slate-600 hover:text-slate-900">Test</button></form>
                                <a href="{{ route('admin.webhooks.edit', $w) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">Bearbeiten</a>
                                <form method="POST" action="{{ route('admin.webhooks.destroy', $w) }}" class="inline" onsubmit="return confirm('Webhook loeschen?')">
                                    @csrf @method('DELETE')
                                    <button class="text-sm text-rose-600 hover:text-rose-500">Loeschen</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
        <div class="mt-4">{{ $webhooks->links() }}</div>
    </x-card>
</x-app-layout>
