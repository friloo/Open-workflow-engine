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
