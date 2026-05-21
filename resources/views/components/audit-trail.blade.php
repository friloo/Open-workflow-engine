@props([
    'model' => null,        // Eloquent-Model — Audit-Log-Eintraege werden ueber auditable_type/_id geladen
    'limit' => 10,
    'title' => 'Aenderungs-Historie',
])

@php
    $entries = collect();
    if ($model && $model->exists) {
        $entries = \App\Models\AuditLog::query()
            ->where('auditable_type', $model->getMorphClass())
            ->where('auditable_id', $model->getKey())
            ->with('user:id,name')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }
@endphp

<x-card :title="$title" description="Letzte {{ $limit }} relevante Aenderungen aus dem Audit-Log.">
    @if($entries->isEmpty())
        <p class="text-sm text-slate-500">Noch keine Aenderungen protokolliert.</p>
    @else
        <ul class="space-y-2">
            @foreach($entries as $e)
                <li class="border-l-2 border-slate-200 ps-3 py-1">
                    <div class="flex items-baseline justify-between gap-2">
                        <span class="text-xs font-mono text-indigo-700">{{ $e->event }}</span>
                        <span class="text-xs text-slate-500"><x-fmt-date :value="$e->created_at" format="relative" /></span>
                    </div>
                    <div class="text-sm text-slate-700">{{ $e->description }}</div>
                    @if($e->user)
                        <div class="text-xs text-slate-500">von {{ $e->user->name }}</div>
                    @endif
                </li>
            @endforeach
        </ul>
        @if(auth()->user()->hasPermission('audit.view'))
            <a href="{{ route('admin.audit.index', ['q' => $model->getMorphClass() . '#' . $model->getKey()]) }}"
               class="mt-3 inline-block text-xs text-indigo-600 hover:text-indigo-500">
                Alle Audit-Eintraege zu diesem Objekt &rarr;
            </a>
        @endif
    @endif
</x-card>
