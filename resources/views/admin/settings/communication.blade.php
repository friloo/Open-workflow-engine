<x-app-layout>
    <x-slot name="header">Systemeinstellungen · Kommunikation</x-slot>
    <x-slot name="subheader">Mail-Versand, IT-Support-Formular und externe Notifications (Teams) — alle Kommunikationskanaele an einem Ort.</x-slot>

    @include('admin.settings._tabs', ['sections' => $sections, 'current' => 'communication'])

    @if(session('status'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    {{-- Anker-Sprungleiste --}}
    <nav class="mb-6 flex flex-wrap gap-2 text-xs">
        <a href="#mail" class="rounded-full bg-slate-100 px-3 py-1 text-slate-700 hover:bg-slate-200">Mail-Versand</a>
        <a href="#support" class="rounded-full bg-slate-100 px-3 py-1 text-slate-700 hover:bg-slate-200">IT-Support</a>
        <a href="#teams" class="rounded-full bg-slate-100 px-3 py-1 text-slate-700 hover:bg-slate-200">Microsoft Teams</a>
    </nav>

    <div id="mail" class="scroll-mt-24 mb-8">
        <h2 class="text-lg font-semibold text-slate-900 mb-3">Mail-Versand</h2>
        @include('admin.settings._form_mail')
    </div>

    <div id="support" class="scroll-mt-24 mb-8">
        <h2 class="text-lg font-semibold text-slate-900 mb-3">IT-Support-Formular</h2>
        @include('admin.settings._form_support')
    </div>

    <div id="teams" class="scroll-mt-24 mb-8">
        <h2 class="text-lg font-semibold text-slate-900 mb-3">Externe Notifications</h2>
        @include('admin.settings._form_integrations')
    </div>
</x-app-layout>
