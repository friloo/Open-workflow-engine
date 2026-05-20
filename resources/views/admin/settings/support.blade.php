<x-app-layout>
    <x-slot name="header">Systemeinstellungen · IT-Support</x-slot>
    <x-slot name="subheader">Support-Formular fuer eingeloggte Benutzer (Mail oder Ticketsystem-API).</x-slot>

    @include('admin.settings._tabs', ['sections' => $sections, 'current' => 'communication'])

    @include('admin.settings._form_support')
</x-app-layout>
