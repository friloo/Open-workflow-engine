@php($selectedRoles = old('roles', isset($user) ? $user->roles->pluck('id')->all() : []))
@csrf
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div>
        <x-input-label for="name" value="Name" />
        <x-text-input id="name" name="name" type="text" value="{{ old('name', $user->name ?? '') }}" required />
        <x-input-error :messages="$errors->get('name')" />
    </div>
    <div>
        <x-input-label for="email" value="E-Mail" />
        <x-text-input id="email" name="email" type="email" value="{{ old('email', $user->email ?? '') }}" required />
        <x-input-error :messages="$errors->get('email')" />
    </div>
    <div>
        <x-input-label for="password" value="Passwort {{ isset($user) ? '(leer lassen = unveraendert)' : '' }}" />
        <x-text-input id="password" name="password" type="password" autocomplete="new-password" />
        <x-input-error :messages="$errors->get('password')" />
    </div>
    <div>
        <x-input-label for="supervisor_id" value="Vorgesetzter" />
        <select id="supervisor_id" name="supervisor_id" class="block w-full rounded-lg border-slate-300 bg-white text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="">— keiner —</option>
            @foreach($supervisors as $s)
                <option value="{{ $s->id }}" @selected(old('supervisor_id', $user->supervisor_id ?? null) == $s->id)>{{ $s->name }} ({{ $s->email }})</option>
            @endforeach
        </select>
        <p class="mt-1 text-xs text-slate-500">Bei M365-Sync kann der dort hinterlegte Vorgesetzte verwendet werden.</p>
    </div>
    <div>
        <x-input-label for="department" value="Abteilung" />
        <x-text-input id="department" name="department" type="text" value="{{ old('department', $user->department ?? '') }}" />
    </div>
    <div>
        <x-input-label for="job_title" value="Funktion / Position" />
        <x-text-input id="job_title" name="job_title" type="text" value="{{ old('job_title', $user->job_title ?? '') }}" />
    </div>
    <div>
        <x-input-label for="phone" value="Telefon" />
        <x-text-input id="phone" name="phone" type="text" value="{{ old('phone', $user->phone ?? '') }}" />
    </div>
    <div>
        <x-input-label for="employee_id" value="Personalnummer" />
        <x-text-input id="employee_id" name="employee_id" type="text" value="{{ old('employee_id', $user->employee_id ?? '') }}" />
    </div>
</div>

<div class="mt-6 flex flex-col gap-3">
    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" @checked(old('is_active', $user->is_active ?? true))>
        Benutzer ist aktiv (kann sich anmelden)
    </label>
    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
        <input type="hidden" name="email_notifications_enabled" value="0">
        <input type="checkbox" name="email_notifications_enabled" value="1" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" @checked(old('email_notifications_enabled', $user->email_notifications_enabled ?? true))>
        E-Mail-Benachrichtigungen erhalten
    </label>
    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
        <input type="hidden" name="prefer_m365_supervisor" value="0">
        <input type="checkbox" name="prefer_m365_supervisor" value="1" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" @checked(old('prefer_m365_supervisor', $user->prefer_m365_supervisor ?? false))>
        M365-Vorgesetzten verwenden (sofern vorhanden)
    </label>

    <label class="inline-flex items-start gap-2 text-sm text-slate-700">
        <input type="hidden" name="is_service_account" value="0">
        <input type="checkbox" name="is_service_account" value="1" class="mt-1 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" @checked(old('is_service_account', $user->is_service_account ?? false))>
        <span>
            <strong>Service-Account</strong> (Konto fuer API-Integrationen, kein Mensch)
            <span class="block text-xs text-slate-500 mt-0.5">
                Erscheint nicht in Empfaenger-/Vorgesetzten-Dropdowns, wird von
                Auswahllisten ausgeschlossen. Token werden ueber Benutzer-Detail
                &raquo; API-Tokens vergeben.
            </span>
        </span>
    </label>
</div>

@if(! empty($customFields))
    <div class="mt-8">
        <h3 class="text-sm font-semibold text-slate-900">Benutzerdefinierte Felder</h3>
        <p class="text-xs text-slate-500 mb-3">Im Workflow verfuegbar als <code>@{{ initiator_custom.&lt;key&gt; }}</code>.</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            @foreach($customFields as $f)
                @php($val = old("custom_fields.{$f['key']}", $user->custom_fields[$f['key']] ?? null))
                <div>
                    <x-input-label :for="'cf-'.$f['key']" :value="$f['label']" />
                    @switch($f['type'])
                        @case('select')
                            <select id="cf-{{ $f['key'] }}" name="custom_fields[{{ $f['key'] }}]" class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">—</option>
                                @foreach(($f['options'] ?? []) as $opt)
                                    <option value="{{ $opt }}" @selected($val==$opt)>{{ $opt }}</option>
                                @endforeach
                            </select>
                            @break
                        @case('date')
                            <x-text-input :id="'cf-'.$f['key']" type="date" :name="'custom_fields['.$f['key'].']'" :value="$val" />
                            @break
                        @case('number')
                            <x-text-input :id="'cf-'.$f['key']" type="number" :name="'custom_fields['.$f['key'].']'" :value="$val" />
                            @break
                        @default
                            <x-text-input :id="'cf-'.$f['key']" :name="'custom_fields['.$f['key'].']'" :value="$val" />
                    @endswitch
                </div>
            @endforeach
        </div>
    </div>
@endif

<div class="mt-8">
    <h3 class="text-sm font-semibold text-slate-900">Rollen</h3>
    <p class="text-xs text-slate-500 mb-3">Berechtigungen werden ausschliesslich ueber Rollen gesteuert.</p>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
        @foreach($roles as $r)
            <label class="flex items-start gap-2 rounded-lg border border-slate-200 p-3 hover:bg-slate-50">
                <input type="checkbox" name="roles[]" value="{{ $r->id }}" class="mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" @checked(in_array($r->id, $selectedRoles))>
                <span>
                    <span class="block text-sm font-medium text-slate-900">{{ $r->name }}</span>
                    @if($r->description)<span class="block text-xs text-slate-500">{{ $r->description }}</span>@endif
                </span>
            </label>
        @endforeach
    </div>
</div>
