@php($selected = old('permissions', isset($role) ? $role->permissions->pluck('id')->all() : []))
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
