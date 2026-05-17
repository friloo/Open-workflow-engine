@csrf
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="md:col-span-2">
        <x-input-label for="name" value="Name" />
        <x-text-input id="name" name="name" value="{{ old('name', $workflow->name ?? '') }}" required />
        <x-input-error :messages="$errors->get('name')" />
    </div>
    <div class="md:col-span-2">
        <x-input-label for="description" value="Beschreibung" />
        <textarea id="description" name="description" rows="3"
            class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description', $workflow->description ?? '') }}</textarea>
    </div>
    <div>
        <x-input-label for="trigger_type" value="Trigger" />
        <select id="trigger_type" name="trigger_type" class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
            @php($tt = old('trigger_type', $workflow->trigger_type ?? 'form'))
            <option value="form" @selected($tt==='form')>Formular (Mitarbeiter fuellt aus)</option>
            <option value="manual" @selected($tt==='manual')>Manueller Start</option>
            <option value="schedule" @selected($tt==='schedule')>Zeitgesteuert (einmalig)</option>
            <option value="recurring" @selected($tt==='recurring')>Wiederkehrend (z. B. Fuehrerschein-Pruefung)</option>
        </select>
    </div>
    <div class="flex items-end">
        <label class="inline-flex items-center gap-2 text-sm text-slate-700">
            <input type="hidden" name="is_public" value="0">
            <input type="checkbox" name="is_public" value="1" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" @checked(old('is_public', $workflow->is_public ?? false))>
            Oeffentliches Formular (ohne Login startbar)
        </label>
    </div>
</div>
