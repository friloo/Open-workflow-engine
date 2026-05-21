<form method="post" action="{{ route('profile.update') }}" class="space-y-6">
    @csrf
    @method('patch')

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <x-input-label for="name" value="Name" />
            <x-text-input id="name" name="name" type="text" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" />
        </div>
        <div>
            <x-input-label for="email" value="E-Mail" />
            <x-text-input id="email" name="email" type="email" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" />
        </div>
        <div>
            <x-input-label for="phone" value="Telefon" />
            <x-text-input id="phone" name="phone" type="text" :value="old('phone', $user->phone)" />
        </div>
        @php($locales = config('app.available_locales', ['de' => 'Deutsch']))
        @if(count($locales) > 1)
            <div>
                <x-input-label for="locale" value="Sprache / Language" />
                <select id="locale" name="locale" class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">— {{ __('Standard') }} —</option>
                    @foreach($locales as $code => $label)
                        <option value="{{ $code }}" @selected(old('locale', $user->locale) === $code)>{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('locale')" />
            </div>
        @endif
    </div>

    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
        <input type="hidden" name="email_notifications_enabled" value="0">
        <input type="checkbox" name="email_notifications_enabled" value="1" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" @checked(old('email_notifications_enabled', $user->email_notifications_enabled))>
        E-Mail-Benachrichtigungen zu meinen Workflow-Aufgaben erhalten
    </label>

    <div class="flex items-center gap-4">
        <x-primary-button>Speichern</x-primary-button>
    </div>
</form>
