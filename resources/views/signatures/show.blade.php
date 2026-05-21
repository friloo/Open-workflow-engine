<x-app-layout>
    <x-slot name="header">Signaturen: {{ $attachment->original_name }}</x-slot>
    <x-slot name="subheader">
        Pflicht-Level für Dokumenttyp „{{ $attachment->document_type ?: '—' }}":
        <strong>{{ $levels[$levelForType] ?? 'Keine' }}</strong>
    </x-slot>

    <x-breadcrumbs :items="[
        ['title' => 'Dokumente', 'url' => route('documents.index')],
        ['title' => $attachment->original_name, 'url' => route('documents.show', $attachment)],
        ['title' => 'Signaturen'],
    ]" />

    {{-- Neue Signatur ablegen --}}
    @if($levelForType !== 'none')
        <x-card title="Dokument signieren" description="Standardmäßig wird das in den Einstellungen für diesen Dokumenttyp hinterlegte Level verwendet. Höher signieren ist möglich.">
            <form method="POST" action="{{ route('documents.signatures.store', $attachment) }}" class="space-y-3 max-w-xl">
                @csrf
                <div>
                    <x-input-label for="level" value="Level" />
                    <select id="level" name="level" class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach(['ses','aes','qes'] as $code)
                            <option value="{{ $code }}" @selected($code === $levelForType)>
                                {{ $levels[$code] }} @if($code === $levelForType) — Pflicht-Level @endif
                            </option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('level')" />
                </div>
                <div>
                    <x-input-label for="reason" value="Anlass (optional, wird in der Signatur protokolliert)" />
                    <x-text-input id="reason" name="reason" maxlength="255" placeholder="z. B. Freigabe der Bestellung 4711" />
                </div>
                <x-primary-button>Jetzt signieren</x-primary-button>
                @if(! auth()->user()->hasTwoFactorEnabled())
                    <p class="text-xs text-amber-700">Hinweis: 2FA ist nicht aktiv. Die Signatur wird gültig, aber Audit-mäßig schwächer (ohne 2FA-Bindung).</p>
                @endif
            </form>
        </x-card>
    @else
        <x-card>
            <p class="text-sm text-slate-600">
                Für den Dokumenttyp „<strong>{{ $attachment->document_type ?: '—' }}</strong>" ist
                kein Signatur-Level hinterlegt. Setze es unter
                <a href="{{ route('admin.settings.documents') }}" class="text-indigo-600 hover:text-indigo-500">Admin · Dokument-Archive</a>.
            </p>
        </x-card>
    @endif

    <x-card title="Signatur-Historie">
        @if($signatures->isEmpty())
            <p class="text-sm text-slate-500">Noch nicht signiert.</p>
        @else
            <ul class="divide-y divide-slate-100">
                @foreach($signatures as $s)
                    @php($v = $verifications[$s->id] ?? ['ok' => false, 'reason' => 'unbekannt'])
                    <li class="py-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-slate-900">{{ $s->signer_name }}</span>
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                        @switch($s->level)
                                            @case('ses') bg-slate-100 text-slate-700 @break
                                            @case('aes') bg-indigo-100 text-indigo-700 @break
                                            @case('qes') bg-violet-100 text-violet-700 @break
                                        @endswitch">{{ strtoupper($s->level) }}</span>
                                    @if($s->twofa_verified)
                                        <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700" title="Signer war mit 2FA authentifiziert">2FA</span>
                                    @endif
                                    @if($v['ok'])
                                        <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">✓ verifiziert</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-rose-50 px-2 py-0.5 text-xs font-medium text-rose-700" title="{{ $v['reason'] ?? '' }}">✗ ungültig</span>
                                    @endif
                                </div>
                                <div class="mt-1 text-xs text-slate-500">
                                    <x-fmt-date :value="$s->signed_at" format="d.m.Y H:i:s" />
                                    @if($s->signer_email) · {{ $s->signer_email }} @endif
                                    @if($s->signer_ip) · IP {{ $s->signer_ip }} @endif
                                </div>
                                <div class="mt-1 text-xs text-slate-600 font-mono">
                                    Doku-Hash: <code class="bg-slate-100 rounded px-1">{{ \Illuminate\Support\Str::limit($s->content_hash, 24, '…') }}</code>
                                </div>
                                @if(! empty($s->metadata['reason']))
                                    <div class="mt-1 text-xs text-slate-600">Anlass: „{{ $s->metadata['reason'] }}"</div>
                                @endif
                                @if(! empty($s->metadata['note']))
                                    <div class="mt-1 text-[11px] text-slate-500 italic">{{ $s->metadata['note'] }}</div>
                                @endif
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>
</x-app-layout>
