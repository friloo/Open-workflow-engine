<x-app-layout>
    <x-slot name="header">Dokumente</x-slot>
    <x-slot name="subheader">Volltextsuche ueber alle hochgeladenen PDFs und Bilder — auch nach Workflow-Abschluss.</x-slot>

    @php
        $missing = collect($ocrAvailability)->reject(fn ($v) => $v)->keys()->all();
    @endphp
    @if(! empty($missing))
        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            OCR-Tools nicht installiert: <strong>{{ implode(', ', $missing) }}</strong>.
            Volltextsuche funktioniert nur fuer eingebettete PDF-Texte. Fuer Bild-PDFs
            poppler-utils (pdftotext, pdftoppm) und tesseract-ocr auf dem Server installieren.
        </div>
    @endif

    <div class="mb-4 flex items-center justify-end gap-3">
        <a href="{{ route('documents.inbox') }}" class="text-sm text-indigo-600 hover:text-indigo-500">Postkorb</a>
        <a href="{{ route('documents.bulk') }}"><x-primary-button type="button">Bulk-Upload</x-primary-button></a>
    </div>

    <form method="GET" class="mb-4 grid grid-cols-1 sm:grid-cols-5 gap-2">
        <input type="text" name="q" value="{{ $q }}" placeholder="Suchen im Dateinamen und Volltext..."
            class="sm:col-span-3 rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        <select name="type" class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="">Alle Typen</option>
            @foreach($types as $t)
                <option value="{{ $t }}" @selected($type===$t)>{{ $t }}</option>
            @endforeach
        </select>
        <div class="flex gap-2">
            <select name="status" class="flex-1 rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">OCR: alle</option>
                <option value="done" @selected($status==='done')>fertig</option>
                <option value="pending" @selected($status==='pending')>pending</option>
                <option value="failed" @selected($status==='failed')>fehlgeschlagen</option>
                <option value="skipped" @selected($status==='skipped')>uebersprungen</option>
            </select>
            <x-secondary-button type="submit">Filtern</x-secondary-button>
        </div>
    </form>

    <x-card>
        @if($documents->isEmpty())
            <x-empty-state icon="document"
                title="Keine Dokumente gefunden"
                description="Lade PDFs und Bilder per Bulk-Upload hoch — sie sind danach per OCR-Volltext durchsuchbar.">
                <a href="{{ route('documents.bulk') }}"><x-primary-button type="button">Bulk-Upload starten</x-primary-button></a>
                <a href="{{ route('help.show', 'documents') }}" class="text-sm text-slate-600 hover:text-slate-900">Anleitung lesen</a>
            </x-empty-state>
        @else
            <ul class="divide-y divide-slate-100">
                @foreach($documents as $d)
                    @include('documents._row', ['d' => $d, 'q' => $q])
                @endforeach
            </ul>
        @endif
        <div class="mt-4">{{ $documents->links() }}</div>
    </x-card>
</x-app-layout>
