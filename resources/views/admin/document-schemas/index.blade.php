<x-app-layout>
    <x-slot name="header">Felder-Schemas pro Dokumenttyp</x-slot>
    <x-slot name="subheader">Pro Dokumenttyp definieren, welche Felder (z. B. Rechnungsnummer, Datum, Betrag, IBAN) automatisch ausgelesen werden sollen.</x-slot>

    <x-card>
        @if(empty($types))
            <x-empty-state icon="document"
                title="Noch keine Dokumenttypen"
                description="Lege zuerst Dokumenttypen unter Systemeinstellungen an.">
                <a href="{{ route('admin.settings.index') }}"><x-secondary-button type="button">Zu den Einstellungen</x-secondary-button></a>
            </x-empty-state>
        @else
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead><tr class="text-left text-xs font-semibold uppercase text-slate-500">
                    <th class="py-2 pr-4">Dokumenttyp</th>
                    <th class="py-2 pr-4">Felder</th>
                    <th class="py-2"></th>
                </tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($types as $t)
                        <tr>
                            <td class="py-3 pr-4 font-medium text-slate-900">{{ $t }}</td>
                            <td class="py-3 pr-4">
                                @if(empty($schemas[$t]))
                                    <span class="text-xs text-slate-500">— kein Schema —</span>
                                @else
                                    <div class="flex flex-wrap gap-1">
                                        @foreach($schemas[$t] as $f)
                                            <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-xs font-mono text-slate-700">{{ $f['key'] }}</span>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                            <td class="py-3 text-right">
                                <a href="{{ route('admin.document_schemas.edit', $t) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">Bearbeiten</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-card>
</x-app-layout>
