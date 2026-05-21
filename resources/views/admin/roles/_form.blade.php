@php
    // Kopierte Rolle: $selectedPermissions enthaelt Permission-SLUGS;
    // wir mappen das auf IDs damit das existierende Form-Markup weiter
    // funktioniert.
    $copySlugs = $selectedPermissions ?? [];
    $copyIds = ! empty($copySlugs) ? \App\Models\Permission::whereIn('slug', $copySlugs)->pluck('id')->all() : [];
    $selected = old('permissions', isset($role) ? $role->permissions->pluck('id')->all() : $copyIds);
@endphp
@csrf
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div>
        <x-input-label for="name" value="Name" />
        <x-text-input id="name" name="name" value="{{ old('name', $role->name ?? '') }}" required />
        <x-input-error :messages="$errors->get('name')" />
    </div>
    <div>
        <x-input-label for="description" value="Beschreibung" />
        <x-text-input id="description" name="description" value="{{ old('description', $role->description ?? '') }}" />
    </div>
</div>

<div class="mt-6 rounded-lg border border-slate-200 p-4 bg-slate-50">
    <label class="flex items-start gap-3 cursor-pointer">
        <input type="checkbox" name="requires_2fa" value="1"
               class="mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
               @checked(old('requires_2fa', isset($role) ? $role->requires_2fa : ($copyFrom->requires_2fa ?? false)))>
        <span>
            <span class="block text-sm font-medium text-slate-900">2FA fuer diese Rolle erzwingen</span>
            <span class="block text-xs text-slate-500">User mit dieser Rolle muessen Zwei-Faktor-Authentifizierung einrichten, bevor sie weiter mit der Anwendung arbeiten koennen. Audit-relevant.</span>
        </span>
    </label>
</div>

<div class="mt-8">
    <h3 class="text-sm font-semibold text-slate-900">Berechtigungen</h3>
    <p class="text-xs text-slate-500 mb-3">Waehle alle Permissions aus, die zu dieser Rolle gehoeren.</p>

    @if(isset($role) && $role->slug === 'admin')
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
            Die Administrator-Rolle besitzt implizit alle Rechte. Die Auswahl unten dient nur der Dokumentation.
        </div>
    @endif

    <div class="mt-4 space-y-6">
        @foreach($permissions as $group => $items)
            <div>
                <h4 class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $group }}</h4>
                <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-2">
                    @foreach($items as $p)
                        <label class="flex items-start gap-2 rounded-lg border border-slate-200 p-3 hover:bg-slate-50">
                            <input type="checkbox" name="permissions[]" value="{{ $p->id }}" class="mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" @checked(in_array($p->id, $selected))>
                            <span>
                                <span class="block text-sm font-medium text-slate-900">{{ $p->name }}</span>
                                <span class="block text-xs text-slate-500"><code class="bg-slate-100 rounded px-1">{{ $p->slug }}</code></span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</div>

{{-- Dokument-Archive: pro Archiv eine Checkbox, ob diese Rolle es in der
     Dokumenten-Suche sehen darf. Greift nur wenn der User ueberhaupt
     documents.search hat. Admin sieht immer alles. --}}
<div class="mt-8">
    <h3 class="text-sm font-semibold text-slate-900">Sichtbare Dokument-Archive</h3>
    <p class="text-xs text-slate-500 mb-3">
        Welche Archive (Dokumenttypen) sieht diese Rolle in der Dokumenten-Suche.
        Wirkt nur, wenn die Rolle die Permission <code>documents.search</code> hat.
    </p>

    @if(empty($documentTypes))
        <p class="text-sm text-slate-500">Noch keine Archive definiert.
            <a href="{{ route('admin.settings.documents') }}" class="text-indigo-600 hover:text-indigo-500">jetzt anlegen</a>
        </p>
    @else
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2">
            @php($selectedTypes = old('document_types', $roleDocumentTypes ?? []))
            @foreach($documentTypes as $dt)
                <label class="inline-flex items-center gap-2 rounded-lg border border-slate-200 p-2.5 text-sm hover:bg-slate-50 has-[:checked]:border-indigo-400 has-[:checked]:bg-indigo-50">
                    <input type="checkbox" name="document_types[]" value="{{ $dt }}"
                        class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                        @checked(in_array($dt, $selectedTypes))>
                    <span class="text-slate-700">{{ $dt }}</span>
                </label>
            @endforeach
        </div>
    @endif
</div>

{{-- Listen / Lookup-Tabellen: zwei Checkboxen pro Liste — Lesen + Schreiben.
     Eine Liste ohne Rollen-Eintrag ist fuer ALLE mit lists.view sichtbar. --}}
<div class="mt-8">
    <h3 class="text-sm font-semibold text-slate-900">Zugriff auf Listen / Lookup-Tabellen</h3>
    <p class="text-xs text-slate-500 mb-3">
        Wenn eine Liste hier nicht angehakt ist, kann diese Rolle sie nur sehen, falls die
        Liste insgesamt ohne Rollen-Beschraenkung gepflegt ist (Default-offen).
        Mit Haken bei „Bearbeiten" darf die Rolle Eintraege aendern.
    </p>

    @if($lists->isEmpty())
        <p class="text-sm text-slate-500">Noch keine Listen vorhanden.
            <a href="{{ route('lists.index') }}" class="text-indigo-600 hover:text-indigo-500">jetzt anlegen</a>
        </p>
    @else
        @php($listAccess = old('list_access', $roleListAccess ?? []))
        <div class="space-y-1.5">
            @foreach($lists as $list)
                @php($current = $listAccess[$list->id] ?? null)
                @php($hasAccess = ! empty($current['access']))
                @php($canEdit = ! empty($current['can_edit']))
                <div class="grid grid-cols-12 items-center gap-2 rounded-lg border border-slate-200 p-2.5"
                     x-data="{ access: {{ $hasAccess ? 'true' : 'false' }} }">
                    <div class="col-span-12 sm:col-span-6 min-w-0">
                        <div class="text-sm font-medium text-slate-900 truncate">{{ $list->name }}</div>
                        @if($list->description)
                            <div class="text-xs text-slate-500 truncate">{{ $list->description }}</div>
                        @endif
                    </div>
                    <label class="col-span-6 sm:col-span-3 inline-flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="list_access[{{ $list->id }}][access]" value="1"
                            class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                            x-model="access" @checked($hasAccess)>
                        Sehen
                    </label>
                    <label class="col-span-6 sm:col-span-3 inline-flex items-center gap-2 text-sm text-slate-700"
                           :class="access ? '' : 'opacity-40'">
                        <input type="checkbox" name="list_access[{{ $list->id }}][can_edit]" value="1"
                            class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                            :disabled="!access" @checked($canEdit)>
                        Bearbeiten
                    </label>
                </div>
            @endforeach
        </div>
    @endif
</div>
