{{-- Wird sowohl von admin/settings/integrations.blade.php als auch von der konsolidierten
     Kommunikation-Seite eingebunden. Erwartet Variable $integrations. --}}
<x-card title="Microsoft Teams"
        description="Channel-Connector fuer Benachrichtigungen aus Workflow-Approvals.">
    <form method="POST" action="{{ route('admin.settings.integrations.update') }}" class="space-y-3 max-w-3xl">
        @csrf
        <div>
            <x-input-label for="teams_webhook_url" value="Teams Channel Webhook-URL" />
            <x-text-input id="teams_webhook_url" name="teams_webhook_url"
                value="{{ $integrations['teams_webhook_url'] ?? '' }}"
                placeholder="https://outlook.office.com/webhook/..." />
            <p class="mt-1 text-xs text-slate-500">
                In Teams: <strong>Channel → Verbindungen → Eingehender Webhook</strong> hinzufuegen,
                Namen vergeben, die generierte URL hier eintragen.
                Sobald gesetzt, bekommt der Channel bei jedem Approval-Step
                eine Adaptive-Card mit Link zur Aufgabe.
            </p>
        </div>
        <x-input-error :messages="$errors->get('teams_webhook_url')" />
        <x-input-error :messages="$errors->get('teams')" />
        <div class="flex items-center gap-2">
            <x-primary-button>Speichern</x-primary-button>
            @if(! empty($integrations['teams_webhook_url']))
                <form method="POST" action="{{ route('admin.settings.integrations.test_teams') }}" class="inline">
                    @csrf
                    <button type="submit" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Test-Nachricht senden</button>
                </form>
            @endif
        </div>
    </form>
</x-card>

<div class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-xs text-slate-600 mt-3">
    Pro Approval-Knoten kannst du das im Designer individuell ueberschreiben
    (eigener Channel pro Workflow) — Feld <code>teams_webhook_url</code> bzw.
    <code>notify_teams</code> in den Knoten-Daten.
    Siehe <a href="{{ route('help.show', 'teams') }}" class="text-indigo-600 hover:text-indigo-500">Anleitung Teams</a>.
</div>
