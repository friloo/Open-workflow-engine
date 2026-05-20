<x-app-layout>
    <x-slot name="header">Systemeinstellungen · Integrationen</x-slot>
    <x-slot name="subheader">Externe Connectors fuer Notifications und Datenaustausch.</x-slot>

    @include('admin.settings._tabs', ['sections' => $sections, 'current' => 'communication'])

    @include('admin.settings._form_integrations')
</x-app-layout>
