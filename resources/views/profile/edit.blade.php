<x-app-layout>
    <x-slot name="header">Mein Profil</x-slot>
    <x-slot name="subheader">Persoenliche Daten und Benachrichtigungen.</x-slot>

    <div class="space-y-6">
        <x-card title="Profilinformationen" description="Aktualisiere deinen Namen und deine E-Mail-Adresse.">
            @include('profile.partials.update-profile-information-form')
        </x-card>

        <x-card title="Passwort aendern" description="Stelle ein langes, zufaelliges Passwort ein, damit dein Konto sicher bleibt.">
            @include('profile.partials.update-password-form')
        </x-card>

        <x-card title="Vertretung" description="Waehrend deiner Abwesenheit gehen alle dir zugewiesenen Aufgaben automatisch an die hier hinterlegte Person.">
            @include('profile.partials.update-delegation-form')
        </x-card>
    </div>
</x-app-layout>
