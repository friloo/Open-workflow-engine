<x-app-layout :full="true">
    <x-slot name="header">API-Dokumentation</x-slot>
    <x-slot name="subheader">OpenAPI-Spec der ein- und ausgehenden Schnittstellen — intern.</x-slot>

    <div class="px-4 sm:px-6 lg:px-8 py-6 space-y-4">
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
            Diese Seite ist nur über Admin-Berechtigung erreichbar. Sie ist NICHT
            öffentlich exponiert. Die Spec liegt in <code>resources/api-docs/openapi.yaml</code>
            und wird hand-curiert — bei Code-Änderungen an Endpunkten bitte mitpflegen.
        </div>

        <div class="flex items-center gap-2 text-xs">
            <a href="{{ route('admin.api_docs.spec') }}" class="text-indigo-600 hover:text-indigo-500" download>
                openapi.yaml herunterladen
            </a>
            <span class="text-slate-300">·</span>
            <a href="{{ route('admin.api_docs.spec') }}" target="_blank" class="text-slate-600 hover:text-slate-900">
                Rohformat ansehen
            </a>
        </div>

        {{-- Swagger-UI lädt via CDN. Wenn das wegen CSP / Offline nicht geht,
             zeigt das Fallback unten die Roh-YAML. --}}
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui.css" />
        <div id="swagger-ui" class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden"></div>

        <noscript>
            <div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800">
                Swagger-UI braucht JavaScript. Roh-YAML siehst du via Link oben.
            </div>
        </noscript>

        <script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui-bundle.js" crossorigin></script>
        <script>
            window.addEventListener('load', () => {
                if (typeof SwaggerUIBundle === 'undefined') {
                    document.getElementById('swagger-ui').innerHTML =
                        '<div class="p-6 text-sm text-rose-700">Swagger-UI konnte nicht geladen werden — bitte Netzwerk-Zugriff auf jsdelivr.net prüfen oder die YAML direkt herunterladen.</div>';
                    return;
                }
                SwaggerUIBundle({
                    url: @json(route('admin.api_docs.spec')),
                    dom_id: '#swagger-ui',
                    deepLinking: true,
                    docExpansion: 'list',
                    defaultModelsExpandDepth: 1,
                    tryItOutEnabled: false,
                });
            });
        </script>
    </div>
</x-app-layout>
