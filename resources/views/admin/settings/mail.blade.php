<x-app-layout>
    <x-slot name="header">Systemeinstellungen · Mail-Versand</x-slot>
    <x-slot name="subheader">SMTP-Server fuer Workflow-Benachrichtigungen.</x-slot>

    @include('admin.settings._tabs', ['sections' => $sections, 'current' => 'communication'])

    @include('admin.settings._form_mail')
</x-app-layout>
