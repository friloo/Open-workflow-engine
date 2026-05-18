@php($schema = $schema ?? [])
@foreach($schema as $field)
    @php($key = $field['key'])
    @php($label = $field['label'] ?? $key)
    @php($type = $field['type'] ?? 'text')
    @php($required = ! empty($field['required']))
    @php($options = $field['options'] ?? [])
    @php($old = old($key))
    <div>
        <label for="f-{{ $key }}" class="block text-sm font-medium text-slate-700 mb-1">
            {{ $label }}@if($required) <span class="text-rose-500">*</span>@endif
        </label>

        @switch($type)
            @case('textarea')
                <textarea id="f-{{ $key }}" name="{{ $key }}" rows="4"
                    @if($required) required @endif
                    class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ $old }}</textarea>
                @break
            @case('number')
                <input id="f-{{ $key }}" name="{{ $key }}" type="number" value="{{ $old }}"
                    @if($required) required @endif
                    class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @break
            @case('date')
                <input id="f-{{ $key }}" name="{{ $key }}" type="date" value="{{ $old }}"
                    @if($required) required @endif
                    class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @break
            @case('select')
                <select id="f-{{ $key }}" name="{{ $key }}"
                    @if($required) required @endif
                    class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">— bitte waehlen —</option>
                    @foreach($options as $opt)
                        <option value="{{ $opt }}" @selected($old==$opt)>{{ $opt }}</option>
                    @endforeach
                </select>
                @break
            @case('radio')
                <div class="space-y-1">
                    @foreach($options as $opt)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="radio" name="{{ $key }}" value="{{ $opt }}" @checked($old==$opt) @if($required) required @endif
                                class="border-slate-300 text-indigo-600 focus:ring-indigo-500">
                            {{ $opt }}
                        </label>
                    @endforeach
                </div>
                @break
            @case('checkbox')
                <label class="flex items-center gap-2 text-sm">
                    <input type="hidden" name="{{ $key }}" value="0">
                    <input type="checkbox" name="{{ $key }}" value="1" @checked($old=='1')
                        class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                    {{ $label }}
                </label>
                @break
            @case('file')
                <input id="f-{{ $key }}" name="{{ $key }}" type="file"
                    accept=".pdf,.jpg,.jpeg,.png,.webp,.heic,.heif,.doc,.docx,.xls,.xlsx,.txt,.csv"
                    @if($required) required @endif
                    class="block w-full text-sm text-slate-700 file:mr-4 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100">
                <p class="mt-1 text-xs text-slate-500">PDF, Bild oder Office-Dokument (max. 15 MB).</p>
                @break
            @default
                <input id="f-{{ $key }}" name="{{ $key }}" type="text" value="{{ $old }}"
                    @if($required) required @endif
                    class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        @endswitch

        <x-input-error :messages="$errors->get($key)" />
    </div>
@endforeach

<input type="text" name="_honeypot" autocomplete="off" tabindex="-1" style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0;">
