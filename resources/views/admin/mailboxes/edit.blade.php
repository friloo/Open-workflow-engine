<x-app-layout>
    <x-slot name="header">{{ $mailbox->exists ? 'Postfach bearbeiten' : 'Neues Postfach' }}</x-slot>
    <x-slot name="subheader">IMAP-Zugangsdaten und Verarbeitung. Passwörter werden verschlüsselt gespeichert.</x-slot>

    <form method="POST" action="{{ $mailbox->exists ? route('admin.mailboxes.update', $mailbox) : route('admin.mailboxes.store') }}" class="space-y-6">
        @csrf
        @if($mailbox->exists) @method('PUT') @endif

        <x-card title="Allgemein">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700">Name</label>
                    <input type="text" name="name" value="{{ old('name', $mailbox->name) }}" required class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div class="flex items-end">
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $mailbox->is_active)) class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                        Postfach aktiv
                    </label>
                </div>
            </div>
        </x-card>

        <x-card title="IMAP-Verbindung">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-700">Host</label>
                    <input type="text" name="host" value="{{ old('host', $mailbox->host) }}" required class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Port</label>
                    <input type="number" name="port" value="{{ old('port', $mailbox->port) }}" required class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Verschlüsselung</label>
                    <select name="encryption" class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach(['ssl' => 'SSL', 'tls' => 'STARTTLS', 'none' => 'Keine'] as $val => $label)
                            <option value="{{ $val }}" @selected(old('encryption', $mailbox->encryption) === $val)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end">
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="checkbox" name="validate_cert" value="1" @checked(old('validate_cert', $mailbox->validate_cert)) class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                        Zertifikat prüfen
                    </label>
                </div>
            </div>
            <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700">Benutzer</label>
                    <input type="text" name="username" value="{{ old('username', $mailbox->username) }}" required class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Passwort {{ $mailbox->exists ? '(leer = unverändert)' : '' }}</label>
                    <input type="password" name="password" value="" {{ $mailbox->exists ? '' : 'required' }} class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Ordner</label>
                    <input type="text" name="folder" value="{{ old('folder', $mailbox->folder) }}" required class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono">
                </div>
            </div>
        </x-card>

        <x-card title="Verarbeitung">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700">Dokumenttyp für Anhänge</label>
                    <select name="document_type" class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">— ohne Typ —</option>
                        @foreach($documentTypes as $type)
                            <option value="{{ $type }}" @selected(old('document_type', $mailbox->document_type) === $type)>{{ $type }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Workflow starten (optional)</label>
                    <select name="workflow_id" class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">— kein Workflow —</option>
                        @foreach($workflows as $wf)
                            <option value="{{ $wf->id }}" @selected((int) old('workflow_id', $mailbox->workflow_id) === $wf->id)>{{ $wf->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <p class="mt-4 text-xs text-slate-500">Wenn ein Workflow gewählt ist, wird pro Mail eine Instanz gestartet. Mail-Inhalte koennen in Formularfelder geschrieben werden:</p>
            <div class="mt-2 grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-medium text-slate-600">Feldname für Betreff</label>
                    <input type="text" name="subject_field" value="{{ old('subject_field', $mailbox->subject_field) }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono" placeholder="betreff">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600">Feldname für Absender</label>
                    <input type="text" name="from_field" value="{{ old('from_field', $mailbox->from_field) }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono" placeholder="absender">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600">Feldname für Mail-Text</label>
                    <input type="text" name="body_field" value="{{ old('body_field', $mailbox->body_field) }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono" placeholder="text">
                </div>
            </div>

            <div class="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-3 space-y-2">
                <label class="inline-flex items-center gap-2 text-sm">
                    <input type="checkbox" name="move_processed" value="1" @checked(old('move_processed', $mailbox->move_processed)) class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                    Verarbeitete Mails in Zielordner verschieben
                </label>
                <div>
                    <label class="block text-xs font-medium text-slate-600">Zielordner</label>
                    <input type="text" name="processed_folder" value="{{ old('processed_folder', $mailbox->processed_folder) }}" class="mt-1 block w-full max-w-md rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono">
                </div>
            </div>
        </x-card>

        <div class="flex justify-between">
            <a href="{{ route('admin.mailboxes.index') }}" class="text-sm text-slate-600 hover:text-slate-900">Abbrechen</a>
            <x-primary-button>Speichern</x-primary-button>
        </div>
    </form>
</x-app-layout>
