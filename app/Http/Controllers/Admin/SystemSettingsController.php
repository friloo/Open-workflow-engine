<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\MicrosoftGraphSync;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class SystemSettingsController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): View
    {
        // Uebersicht mit Status-Karten pro Sub-Bereich.
        $mail = Settings::group('mail');
        $m365 = Settings::group('auth.m365');
        $oidc = Settings::group('auth.oidc');
        $google = Settings::group('auth.google');
        $saml = Settings::group('auth.saml');
        $ai = Settings::group('ai');

        return view('admin.settings.overview', [
            'sections' => $this->sectionDescriptors(),
            'status' => [
                'mail_configured' => ! empty($mail['host']) && ! empty($mail['from_address']),
                'm365_enabled' => (bool) ($m365['enabled'] ?? false),
                'sso_providers' => array_values(array_filter([
                    ($m365['enabled'] ?? false) ? 'M365' : null,
                    ($oidc['enabled'] ?? false) ? 'OIDC' : null,
                    ($google['enabled'] ?? false) ? 'Google' : null,
                    ($saml['enabled'] ?? false) ? 'SAML' : null,
                ])),
                'ai_configured' => ! empty($ai['provider']) && (! empty($ai['api_key']) || ($ai['provider'] ?? '') === 'ollama'),
                'document_types_count' => count(\App\Support\DocumentTypes::all()),
                'retention_rules_count' => count((array) Settings::get('attachments.retention', [])),
            ],
        ]);
    }

    public function mail(): View
    {
        return view('admin.settings.mail', [
            'mail' => Settings::group('mail') + $this->defaults(),
            'sections' => $this->sectionDescriptors(),
        ]);
    }

    public function m365(): View
    {
        return view('admin.settings.m365', [
            'm365' => Settings::group('auth.m365') + $this->m365Defaults(),
            'roles' => \App\Models\Role::orderBy('name')->get(['id', 'name', 'slug']),
            'sections' => $this->sectionDescriptors(),
        ]);
    }

    public function ai(): View
    {
        return view('admin.settings.ai', [
            'ai' => Settings::group('ai') + [
                'provider' => 'openai',
                'base_url' => 'https://api.openai.com/v1',
                'model' => 'gpt-4o-mini',
                'api_key' => '',
            ],
            'sections' => $this->sectionDescriptors(),
        ]);
    }

    public function branding(): View
    {
        return view('admin.settings.branding', [
            'branding' => Settings::group('branding') + $this->brandingDefaults(),
            'customFields' => Settings::get('users.custom_fields', []),
            'sections' => $this->sectionDescriptors(),
        ]);
    }

    public function documents(): View
    {
        return view('admin.settings.documents', [
            'documentTypes' => \App\Support\DocumentTypes::all(),
            'roleDocumentTypes' => \App\Support\DocumentTypes::roleMapping(),
            'roles' => \App\Models\Role::orderBy('name')->get(['id', 'name', 'slug']),
            'retention' => Settings::get('attachments.retention', []),
            'sections' => $this->sectionDescriptors(),
        ]);
    }

    public function sharing(): View
    {
        return view('admin.settings.sharing', [
            'shares' => [
                'max_expiry_days' => (int) Settings::get('shares.max_expiry_days', 90),
                'default_expiry_days' => (int) Settings::get('shares.default_expiry_days', 14),
                'review_interval_days' => (int) Settings::get('shares.review_interval_days', 7),
                'review_grace_days' => (int) Settings::get('shares.review_grace_days', 3),
            ],
            'sections' => $this->sectionDescriptors(),
        ]);
    }

    public function integrations(): View
    {
        return view('admin.settings.integrations', [
            'integrations' => Settings::group('integrations'),
            'sections' => $this->sectionDescriptors(),
        ]);
    }

    public function updateIntegrations(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'teams_webhook_url' => ['nullable', 'url', 'max:1024'],
        ]);

        Settings::set('integrations.teams_webhook_url', $data['teams_webhook_url'] ?? '', $request->user()->id);
        $this->audit->log('settings.integrations.updated', null, null, [
            'teams_configured' => ! empty($data['teams_webhook_url']),
        ], 'Integrationen aktualisiert', $request->user()->id);

        return back()->with('status', 'Integrationen gespeichert.');
    }

    public function testTeams(Request $request, \App\Services\TeamsNotifier $teams): RedirectResponse
    {
        $url = (string) Settings::get('integrations.teams_webhook_url', '');
        if (! $url) return back()->withErrors(['teams' => 'Keine Teams-URL konfiguriert.']);

        // Eine Dummy-Adaptive-Card schicken
        $payload = [
            '@type' => 'MessageCard',
            '@context' => 'https://schema.org/extensions',
            'summary' => 'OWE Test',
            'themeColor' => '6366f1',
            'title' => 'OWE Test-Nachricht',
            'text' => 'Wenn du das siehst, klappt die Teams-Anbindung.',
        ];
        try {
            $r = \Illuminate\Support\Facades\Http::timeout(10)->post($url, $payload);
            if (! $r->successful()) {
                return back()->withErrors(['teams' => 'Teams antwortete HTTP '.$r->status()]);
            }
        } catch (\Throwable $e) {
            return back()->withErrors(['teams' => $e->getMessage()]);
        }
        return back()->with('status', 'Test-Nachricht an Teams gesendet.');
    }

    public function infrastructure(): View
    {
        $stored = Settings::group('infrastructure');
        // Aktuelle Effektiv-Werte (DB-Overrides ueber env-Defaults gemerged
        // — der Provider hat sie schon angewendet).
        $effective = [
            'attachments_disk' => config('filesystems.attachments_disk'),
            's3_key' => $this->maskSecret(config('filesystems.disks.s3.key')),
            's3_secret' => $this->maskSecret(config('filesystems.disks.s3.secret')),
            's3_region' => config('filesystems.disks.s3.region'),
            's3_bucket' => config('filesystems.disks.s3.bucket'),
            's3_endpoint' => config('filesystems.disks.s3.endpoint'),
            's3_url' => config('filesystems.disks.s3.url'),
            's3_use_path_style' => (bool) config('filesystems.disks.s3.use_path_style_endpoint'),
            'queue_connection' => config('queue.default'),
            'queue_ocr' => (bool) config('app.queue_ocr'),
            'search_driver' => config('search.driver'),
            'meilisearch_host' => config('search.meilisearch.host'),
            'meilisearch_key' => $this->maskSecret(config('search.meilisearch.key')),
            'libreoffice_preview' => (bool) config('app.libreoffice_preview'),
            'libreoffice_bin' => config('app.libreoffice_bin'),
        ];

        return view('admin.settings.infrastructure', [
            'stored' => $stored,
            'effective' => $effective,
            'libreoffice_available' => \App\Services\OfficePreview::isAvailable(),
            'sections' => $this->sectionDescriptors(),
        ]);
    }

    public function updateInfrastructure(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'attachments_disk' => ['nullable', 'in:local,s3'],
            's3_key' => ['nullable', 'string', 'max:128'],
            's3_secret' => ['nullable', 'string', 'max:256'],
            's3_region' => ['nullable', 'string', 'max:64'],
            's3_bucket' => ['nullable', 'string', 'max:128'],
            's3_endpoint' => ['nullable', 'url', 'max:512'],
            's3_url' => ['nullable', 'string', 'max:512'],
            's3_use_path_style' => ['nullable', 'in:0,1'],

            'queue_connection' => ['nullable', 'in:sync,database,redis'],
            'queue_ocr' => ['nullable', 'in:0,1'],

            'search_driver' => ['nullable', 'in:database,meilisearch'],
            'meilisearch_host' => ['nullable', 'url', 'max:512'],
            'meilisearch_key' => ['nullable', 'string', 'max:256'],

            'libreoffice_preview' => ['nullable', 'in:0,1'],
            'libreoffice_bin' => ['nullable', 'string', 'max:512'],
        ]);

        // Geheimnisse mit '********' kennzeichnen NICHT speichern (sonst ueberschreibt
        // der UI-Render das echte Secret wenn der User es nicht aendert).
        foreach (['s3_secret', 's3_key', 'meilisearch_key'] as $secretKey) {
            if (isset($data[$secretKey]) && str_starts_with((string) $data[$secretKey], '****')) {
                unset($data[$secretKey]);
            }
        }

        // Booleans / Checkboxen normalisieren
        $data['queue_ocr'] = ! empty($data['queue_ocr']);
        $data['s3_use_path_style'] = ! empty($data['s3_use_path_style']);
        $data['libreoffice_preview'] = isset($data['libreoffice_preview']) ? (bool) $data['libreoffice_preview'] : true;

        foreach ($data as $k => $v) {
            // Empty-Strings entfernen ('use env' = nichts in DB), aber
            // explizit gesetzte Booleans und Driver-Strings behalten.
            if ($v === null || $v === '') {
                // Nur entfernen wenn es ein optionaler String ist; Booleans (0/1) sollen bleiben.
                if (! in_array($k, ['queue_ocr', 's3_use_path_style', 'libreoffice_preview'], true)) {
                    \App\Models\Setting::where('key', "infrastructure.{$k}")->delete();
                    continue;
                }
            }
            Settings::set("infrastructure.{$k}", $v, $request->user()->id);
        }

        // Cache Config nicht hart neu laden — der Provider greift beim
        // naechsten Request. Aber: Config-Cache MUSS geleert werden falls
        // 'php artisan config:cache' lief.
        try { \Illuminate\Support\Facades\Artisan::call('config:clear'); } catch (\Throwable) {}

        $this->audit->log('settings.infrastructure.updated', null, null, [
            'attachments_disk' => $data['attachments_disk'] ?? null,
            'queue_connection' => $data['queue_connection'] ?? null,
            'search_driver' => $data['search_driver'] ?? null,
        ], 'Infrastruktur-Einstellungen aktualisiert', $request->user()->id);

        return back()->with('status', 'Einstellungen gespeichert. Aenderungen greifen ab dem naechsten Request.');
    }

    /**
     * Probiert die konfigurierten Verbindungen (S3 / MeiliSearch / Queue) und
     * gibt JSON mit ok/error pro Komponente zurueck. Wird von der UI per
     * fetch() angetriggert.
     */
    public function testInfrastructure(Request $request): \Illuminate\Http\JsonResponse
    {
        $results = [];

        // S3
        try {
            $disk = \Illuminate\Support\Facades\Storage::disk('s3');
            $marker = '.owe-write-test-'.now()->timestamp;
            $disk->put($marker, 'ok');
            $exists = $disk->exists($marker);
            $disk->delete($marker);
            $results['s3'] = ['ok' => $exists, 'message' => $exists ? 'Schreiben + Lesen ok.' : 'Schreiben fehlgeschlagen.'];
        } catch (\Throwable $e) {
            $results['s3'] = ['ok' => false, 'message' => $e->getMessage()];
        }

        // MeiliSearch
        $h = app(\App\Services\Search\DocumentSearch::class)->health();
        $results['meilisearch'] = ['ok' => $h['ok'] ?? false, 'message' => $h['error'] ?? ($h['message'] ?? ($h['status'] ?? 'ok'))];

        // Queue / Worker
        $conn = config('queue.default');
        if ($conn === 'sync') {
            $results['queue'] = ['ok' => true, 'message' => 'sync (kein Worker noetig)'];
        } else {
            $pending = \Schema::hasTable('jobs') ? \DB::table('jobs')->count() : null;
            $results['queue'] = ['ok' => true, 'message' => "Connection: {$conn}, Pending: ".($pending ?? '?')];
        }

        // LibreOffice
        $loAvail = \App\Services\OfficePreview::isAvailable();
        $results['libreoffice'] = ['ok' => $loAvail, 'message' => $loAvail ? 'Binary gefunden.' : 'Nicht installiert oder Pfad falsch.'];

        return response()->json(['results' => $results]);
    }

    /** Maskiert Secrets fuer die Anzeige: 'sk-abcdef...' -> '****cdef'. */
    private function maskSecret($value): string
    {
        if (! $value) return '';
        $s = (string) $value;
        if (strlen($s) <= 4) return '****';
        return '****'.substr($s, -4);
    }

    public function support(): View
    {
        return view('admin.settings.support', [
            'support' => Settings::group('support') + [
                'enabled' => false,
                'mode' => 'mail',
                'email' => '',
                'sidebar_label' => 'IT-Support',
                'api_url' => '',
                'api_method' => 'POST',
                'api_headers' => [],
                'api_body_template' => '{
  "subject": "{{ subject }}",
  "description": "{{ description }}",
  "requester": {
    "name": "{{ user_name }}",
    "email": "{{ user_email }}"
  }
}',
            ],
            'sections' => $this->sectionDescriptors(),
        ]);
    }

    public function updateSupport(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['nullable', 'in:0,1'],
            'mode' => ['required', 'in:mail,api,both'],
            'email' => ['nullable', 'email'],
            'sidebar_label' => ['nullable', 'string', 'max:64'],
            'api_url' => ['nullable', 'url'],
            'api_method' => ['nullable', 'in:GET,POST,PUT,PATCH'],
            'api_headers' => ['array'],
            'api_headers.*.key' => ['nullable', 'string', 'max:128'],
            'api_headers.*.value' => ['nullable', 'string', 'max:1024'],
            'api_body_template' => ['nullable', 'string', 'max:65535'],
        ]);

        // Validierung pro Modus: Mail braucht email, API braucht url.
        if (in_array($data['mode'], ['mail', 'both'], true) && empty($data['email'])) {
            return back()->withErrors(['email' => 'Bei Modus Mail/Beides musst du eine Support-Adresse setzen.'])->withInput();
        }
        if (in_array($data['mode'], ['api', 'both'], true) && empty($data['api_url'])) {
            return back()->withErrors(['api_url' => 'Bei Modus API/Beides musst du eine API-URL setzen.'])->withInput();
        }

        $headers = [];
        foreach ($data['api_headers'] ?? [] as $h) {
            if (! empty($h['key'])) $headers[] = ['key' => $h['key'], 'value' => $h['value'] ?? ''];
        }

        $payload = [
            'enabled' => ! empty($data['enabled']),
            'mode' => $data['mode'],
            'email' => $data['email'] ?? '',
            'sidebar_label' => $data['sidebar_label'] ?: 'IT-Support',
            'api_url' => $data['api_url'] ?? '',
            'api_method' => $data['api_method'] ?? 'POST',
            'api_headers' => $headers,
            'api_body_template' => $data['api_body_template'] ?? '',
        ];

        foreach ($payload as $k => $v) {
            Settings::set("support.{$k}", $v, $request->user()->id);
        }

        $this->audit->log('settings.support.updated', null, null, [
            'mode' => $payload['mode'], 'enabled' => $payload['enabled'],
        ], 'IT-Support-Konfiguration aktualisiert', $request->user()->id);

        return redirect()->route('admin.settings.support')->with('status', 'Support-Konfiguration gespeichert.');
    }

    /**
     * Liste aller Sub-Seiten — wird im Tab-Strip oben gerendert und auf
     * der Overview als Karten ausgegeben.
     */
    private function sectionDescriptors(): array
    {
        return [
            ['slug' => 'overview', 'route' => 'admin.settings.index', 'label' => 'Uebersicht', 'icon' => 'home'],
            ['slug' => 'mail', 'route' => 'admin.settings.mail', 'label' => 'Mail-Versand', 'icon' => 'cog', 'description' => 'SMTP fuer Benachrichtigungen.'],
            ['slug' => 'm365', 'route' => 'admin.settings.m365', 'label' => 'Microsoft 365', 'icon' => 'shield', 'description' => 'SSO + Benutzer-Sync.'],
            ['slug' => 'sso', 'route' => 'admin.settings.sso', 'label' => 'SSO (OIDC/Google/SAML)', 'icon' => 'shield', 'description' => 'Weitere Identity-Provider.'],
            ['slug' => 'branding', 'route' => 'admin.settings.branding', 'label' => 'Branding', 'icon' => 'cog', 'description' => 'Name, Logo, Farben + Benutzerfelder.'],
            ['slug' => 'ai', 'route' => 'admin.settings.ai', 'label' => 'KI', 'icon' => 'cog', 'description' => 'OpenAI / DeepSeek / Ollama.'],
            ['slug' => 'documents', 'route' => 'admin.settings.documents', 'label' => 'Dokumente', 'icon' => 'document', 'description' => 'Archive, Retention, Rollen-Zuordnung.'],
            ['slug' => 'sharing', 'route' => 'admin.settings.sharing', 'label' => 'Sharing', 'icon' => 'cog', 'description' => 'Caps fuer externe Freigaben.'],
            ['slug' => 'support', 'route' => 'admin.settings.support', 'label' => 'IT-Support', 'icon' => 'cog', 'description' => 'Support-Formular fuer Benutzer (Mail oder Ticket-API).'],
            ['slug' => 'integrations', 'route' => 'admin.settings.integrations', 'label' => 'Integrationen', 'icon' => 'cog', 'description' => 'Microsoft Teams, weitere externe Connectors.'],
            ['slug' => 'infrastructure', 'route' => 'admin.settings.infrastructure', 'label' => 'Infrastruktur', 'icon' => 'cog', 'description' => 'Storage, Queue, Such-Backend, Office-Vorschau.'],
        ];
    }

    public function updateRetention(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'rules' => ['array'],
            'rules.*.document_type' => ['required', 'string', 'max:64'],
            'rules.*.min_years' => ['required', 'integer', 'between:0,100'],
            'rules.*.max_years' => ['nullable', 'integer', 'between:1,200'],
            'rules.*.on_expiry' => ['required', 'in:mark_for_review,archive,delete'],
        ]);
        $clean = [];
        foreach ($data['rules'] ?? [] as $r) {
            $type = trim($r['document_type']);
            if ($type === '') continue;
            $clean[$type] = [
                'min_years' => (int) $r['min_years'],
                'max_years' => isset($r['max_years']) ? (int) $r['max_years'] : null,
                'on_expiry' => $r['on_expiry'],
            ];
        }
        Settings::set('attachments.retention', $clean, $request->user()->id);
        $this->audit->log('settings.retention.updated', null, null, ['types' => array_keys($clean)],
            'Aufbewahrungsregeln aktualisiert', $request->user()->id);
        return back()->with('status', 'Aufbewahrungsregeln gespeichert.');
    }

    public function updateShares(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'max_expiry_days' => ['required', 'integer', 'between:1,3650'],
            'default_expiry_days' => ['required', 'integer', 'between:1,3650'],
            'review_interval_days' => ['required', 'integer', 'between:1,365'],
            'review_grace_days' => ['required', 'integer', 'between:1,90'],
        ]);
        if ($data['default_expiry_days'] > $data['max_expiry_days']) {
            $data['default_expiry_days'] = $data['max_expiry_days'];
        }
        foreach ($data as $k => $v) {
            Settings::set("shares.{$k}", $v, $request->user()->id);
        }
        $this->audit->log('settings.shares.updated', null, null, $data,
            'Sharing-Einstellungen aktualisiert', $request->user()->id);
        return back()->with('status', 'Sharing-Einstellungen gespeichert.');
    }

    public function updateDocumentTypes(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'types' => ['array'],
            'types.*' => ['required', 'string', 'max:64'],
        ]);
        $clean = array_values(array_unique(array_filter(array_map('trim', $data['types'] ?? []))));
        Settings::set('attachments.document_types', $clean, $request->user()->id);
        $this->audit->log('settings.document_types.updated', null, null, ['count' => count($clean)],
            'Dokumenttypen aktualisiert', $request->user()->id);
        return back()->with('status', 'Dokumenttypen gespeichert.');
    }

    public function updateRoleDocumentTypes(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'roles' => ['array'],
            'roles.*' => ['array'],
            'roles.*.*' => ['string', 'max:64'],
        ]);
        $clean = [];
        foreach ($data['roles'] ?? [] as $roleSlug => $types) {
            $clean[$roleSlug] = array_values(array_unique(array_filter(array_map('trim', (array) $types))));
        }
        Settings::set('attachments.role_document_types', $clean, $request->user()->id);
        $this->audit->log('settings.role_document_types.updated', null, null, ['roles' => array_keys($clean)],
            'Dokumenttyp-Berechtigungen pro Rolle aktualisiert', $request->user()->id);
        return back()->with('status', 'Berechtigungen gespeichert.');
    }

    public function updateBranding(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'app_name' => ['nullable', 'string', 'max:128'],
            'primary_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'logo_text' => ['nullable', 'string', 'max:4'],
        ]);
        foreach ($data as $k => $v) Settings::set("branding.{$k}", $v ?: null, $request->user()->id);
        $this->audit->log('settings.branding.updated', null, null, $data, 'Branding aktualisiert', $request->user()->id);
        return back()->with('status', 'Branding gespeichert.');
    }

    public function updateCustomFields(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'fields' => ['array'],
            'fields.*.key' => ['required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/'],
            'fields.*.label' => ['required', 'string', 'max:128'],
            'fields.*.type' => ['required', 'in:text,number,date,select'],
            'fields.*.options' => ['nullable', 'string', 'max:2000'],
        ]);
        $fields = array_map(function ($f) {
            $f['options'] = $f['options']
                ? array_values(array_filter(array_map('trim', explode("\n", $f['options'])), fn ($v) => $v !== ''))
                : [];
            return $f;
        }, $data['fields'] ?? []);
        Settings::set('users.custom_fields', $fields, $request->user()->id);
        $this->audit->log('settings.custom_fields.updated', null, null, ['count' => count($fields)], 'Custom-User-Felder aktualisiert', $request->user()->id);
        return back()->with('status', 'Custom-Felder gespeichert.');
    }

    private function brandingDefaults(): array
    {
        return [
            'app_name' => config('app.name'),
            'primary_color' => '#6366f1',
            'logo_text' => 'W',
        ];
    }

    public function updateMail(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'transport' => ['required', 'in:smtp,log'],
            'host' => ['nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'between:1,65535'],
            'encryption' => ['nullable', 'in:tls,ssl'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'from_address' => ['required', 'email', 'max:255'],
            'from_name' => ['required', 'string', 'max:255'],
            'timeout' => ['nullable', 'integer', 'between:1,300'],
        ]);

        $previous = Settings::group('mail');
        foreach ($data as $k => $v) {
            // Empty password keeps the previously stored one.
            if ($k === 'password' && ($v === null || $v === '')) {
                continue;
            }
            Settings::set("mail.{$k}", $v === '' ? null : $v, $request->user()->id);
        }

        $this->audit->log(
            'settings.mail.updated',
            null,
            array_intersect_key($previous, $data),
            array_diff_key($data, ['password' => null]),
            'SMTP-Konfiguration aktualisiert',
            $request->user()->id,
        );

        return redirect()->route('admin.settings.mail')->with('status', 'Mail-Konfiguration gespeichert.');
    }

    public function sendTestMail(Request $request): RedirectResponse
    {
        $request->validate([
            'to' => ['required', 'email'],
        ]);

        try {
            // Settings provider has already applied the live config to this request.
            Mail::raw(
                "Dies ist eine Test-Mail aus der Open Workflow Engine.\n\n".
                "Gesendet von ".$request->user()->name." am ".now()->format('d.m.Y H:i:s').".",
                function ($m) use ($request) {
                    $m->to($request->input('to'))
                      ->subject('OWE: Test-Mail');
                }
            );
        } catch (\Throwable $e) {
            $this->audit->log('settings.mail.test_failed', null, null, ['error' => $e->getMessage()],
                'Test-Mail fehlgeschlagen', $request->user()->id);
            return back()->withErrors(['mail' => 'Versand fehlgeschlagen: '.$e->getMessage()]);
        }

        $this->audit->log('settings.mail.test_sent', null, null, ['to' => $request->input('to')],
            'Test-Mail gesendet', $request->user()->id);

        return back()->with('status', 'Test-Mail an '.$request->input('to').' gesendet.');
    }

    public function updateM365(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'auto_provision' => ['nullable', 'boolean'],
            'client_id' => ['nullable', 'string', 'max:255'],
            'client_secret' => ['nullable', 'string', 'max:255'],
            'tenant_id' => ['nullable', 'string', 'max:255'],
            'redirect_uri' => ['nullable', 'url', 'max:255'],
            'default_role' => ['nullable', 'string', 'exists:roles,slug'],
        ]);

        $previous = Settings::group('auth.m365');

        $keys = ['client_id', 'tenant_id', 'redirect_uri', 'default_role'];
        foreach ($keys as $k) {
            Settings::set("auth.m365.{$k}", $data[$k] ?? null, $request->user()->id);
        }
        Settings::set('auth.m365.enabled', $request->boolean('enabled'), $request->user()->id);
        Settings::set('auth.m365.auto_provision', $request->boolean('auto_provision'), $request->user()->id);

        if (! empty($data['client_secret'])) {
            Settings::set('auth.m365.client_secret', $data['client_secret'], $request->user()->id);
        }

        $this->audit->log(
            'settings.m365.updated',
            null,
            array_diff_key($previous, ['client_secret' => null]),
            array_diff_key($data + ['enabled' => $request->boolean('enabled')], ['client_secret' => null]),
            'M365-Konfiguration aktualisiert',
            $request->user()->id,
        );

        return redirect()->route('admin.settings.m365')->with('status', 'Microsoft-365-Konfiguration gespeichert.');
    }

    public function syncM365(Request $request, MicrosoftGraphSync $sync): RedirectResponse
    {
        $default = Settings::get('auth.m365.default_role') ?: 'employee';
        try {
            $result = $sync->syncUsers($default, $request->user()->id);
        } catch (\Throwable $e) {
            return back()->withErrors(['m365' => 'Sync fehlgeschlagen: '.$e->getMessage()]);
        }
        return back()->with('status',
            "M365-Sync abgeschlossen: {$result['created']} neu, {$result['updated']} aktualisiert, ".count($result['errors'])." Fehler.");
    }

    public function testM365(Request $request, MicrosoftGraphSync $sync): RedirectResponse
    {
        $result = $sync->testConnection();
        $this->audit->log(
            $result['ok'] ? 'settings.m365.test_ok' : 'settings.m365.test_failed',
            null, null, ['user_count' => $result['user_count']],
            $result['message'], $request->user()->id,
        );
        if (! $result['ok']) {
            return back()->withErrors(['m365' => $result['message']]);
        }
        return back()->with('status', 'Microsoft 365: '.$result['message']);
    }

    private function defaults(): array
    {
        return [
            'transport' => 'smtp',
            'host' => '',
            'port' => 587,
            'encryption' => 'tls',
            'username' => '',
            'password' => '',
            'from_address' => 'no-reply@example.com',
            'from_name' => config('app.name'),
            'timeout' => 10,
        ];
    }

    private function m365Defaults(): array
    {
        return [
            'enabled' => false,
            'auto_provision' => true,
            'client_id' => '',
            'client_secret' => '',
            'tenant_id' => 'common',
            'redirect_uri' => url('/auth/m365/callback'),
            'default_role' => 'employee',
        ];
    }

    public function sso(): View
    {
        return view('admin.settings.sso', [
            'oidc' => Settings::group('auth.oidc') + $this->oidcDefaults(),
            'google' => Settings::group('auth.google') + $this->googleDefaults(),
            'saml' => Settings::group('auth.saml') + $this->samlDefaults(),
            'roles' => \App\Models\Role::orderBy('name')->get(['id', 'name', 'slug']),
            'sections' => $this->sectionDescriptors(),
        ]);
    }

    public function updateSso(Request $request): RedirectResponse
    {
        $data = $request->validate([
            // OIDC
            'oidc_enabled' => ['nullable', 'boolean'],
            'oidc_issuer' => ['nullable', 'url', 'max:255'],
            'oidc_client_id' => ['nullable', 'string', 'max:255'],
            'oidc_client_secret' => ['nullable', 'string', 'max:512'],
            'oidc_redirect' => ['nullable', 'url', 'max:255'],
            'oidc_scopes' => ['nullable', 'string', 'max:255'],
            'oidc_button_label' => ['nullable', 'string', 'max:64'],
            'oidc_auto_provision' => ['nullable', 'boolean'],
            'oidc_default_role' => ['nullable', 'string', 'max:64'],

            // Google
            'google_enabled' => ['nullable', 'boolean'],
            'google_client_id' => ['nullable', 'string', 'max:255'],
            'google_client_secret' => ['nullable', 'string', 'max:512'],
            'google_redirect' => ['nullable', 'url', 'max:255'],
            'google_hosted_domain' => ['nullable', 'string', 'max:255'],
            'google_auto_provision' => ['nullable', 'boolean'],
            'google_default_role' => ['nullable', 'string', 'max:64'],

            // SAML
            'saml_enabled' => ['nullable', 'boolean'],
            'saml_idp_entity_id' => ['nullable', 'string', 'max:255'],
            'saml_idp_sso_url' => ['nullable', 'url', 'max:255'],
            'saml_idp_x509_cert' => ['nullable', 'string'],
            'saml_sp_entity_id' => ['nullable', 'string', 'max:255'],
            'saml_email_attribute' => ['nullable', 'string', 'max:128'],
            'saml_name_attribute' => ['nullable', 'string', 'max:128'],
            'saml_button_label' => ['nullable', 'string', 'max:64'],
            'saml_auto_provision' => ['nullable', 'boolean'],
            'saml_default_role' => ['nullable', 'string', 'max:64'],
            'saml_want_assertions_signed' => ['nullable', 'boolean'],
            'saml_want_messages_signed' => ['nullable', 'boolean'],
        ]);

        $userId = $request->user()->id;

        // OIDC
        Settings::set('auth.oidc.enabled', $request->boolean('oidc_enabled'), $userId);
        Settings::set('auth.oidc.auto_provision', $request->boolean('oidc_auto_provision'), $userId);
        foreach (['issuer', 'client_id', 'redirect', 'scopes', 'button_label', 'default_role'] as $k) {
            Settings::set("auth.oidc.{$k}", $data["oidc_{$k}"] ?? null, $userId);
        }
        if (! empty($data['oidc_client_secret'])) {
            Settings::set('auth.oidc.client_secret', $data['oidc_client_secret'], $userId);
        }

        // Google
        Settings::set('auth.google.enabled', $request->boolean('google_enabled'), $userId);
        Settings::set('auth.google.auto_provision', $request->boolean('google_auto_provision'), $userId);
        foreach (['client_id', 'redirect', 'hosted_domain', 'default_role'] as $k) {
            Settings::set("auth.google.{$k}", $data["google_{$k}"] ?? null, $userId);
        }
        if (! empty($data['google_client_secret'])) {
            Settings::set('auth.google.client_secret', $data['google_client_secret'], $userId);
        }

        // SAML
        Settings::set('auth.saml.enabled', $request->boolean('saml_enabled'), $userId);
        Settings::set('auth.saml.auto_provision', $request->boolean('saml_auto_provision'), $userId);
        Settings::set('auth.saml.want_assertions_signed', $request->boolean('saml_want_assertions_signed'), $userId);
        Settings::set('auth.saml.want_messages_signed', $request->boolean('saml_want_messages_signed'), $userId);
        foreach ([
            'idp_entity_id', 'idp_sso_url', 'idp_x509_cert',
            'sp_entity_id', 'email_attribute', 'name_attribute',
            'button_label', 'default_role',
        ] as $k) {
            Settings::set("auth.saml.{$k}", $data["saml_{$k}"] ?? null, $userId);
        }

        $this->audit->log('settings.sso.updated', null, null, [
            'oidc' => (bool) $request->boolean('oidc_enabled'),
            'google' => (bool) $request->boolean('google_enabled'),
            'saml' => (bool) $request->boolean('saml_enabled'),
        ], 'SSO-Konfiguration aktualisiert', $userId);

        return redirect()->route('admin.settings.sso')->with('status', 'SSO-Konfiguration gespeichert.');
    }

    private function oidcDefaults(): array
    {
        return [
            'enabled' => false,
            'auto_provision' => true,
            'issuer' => '',
            'client_id' => '',
            'client_secret' => '',
            'redirect' => url('/auth/oidc/callback'),
            'scopes' => 'openid email profile',
            'button_label' => 'Mit Single Sign-On anmelden',
            'default_role' => 'employee',
        ];
    }

    private function googleDefaults(): array
    {
        return [
            'enabled' => false,
            'auto_provision' => true,
            'client_id' => '',
            'client_secret' => '',
            'redirect' => url('/auth/google/callback'),
            'hosted_domain' => '',
            'default_role' => 'employee',
        ];
    }

    private function samlDefaults(): array
    {
        return [
            'enabled' => false,
            'auto_provision' => true,
            'idp_entity_id' => '',
            'idp_sso_url' => '',
            'idp_x509_cert' => '',
            'sp_entity_id' => url('/auth/saml/metadata'),
            'email_attribute' => 'email',
            'name_attribute' => 'displayName',
            'button_label' => 'Mit SAML anmelden',
            'default_role' => 'employee',
            'want_assertions_signed' => false,
            'want_messages_signed' => false,
        ];
    }
}
