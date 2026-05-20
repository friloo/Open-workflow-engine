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
        $ai = Settings::group('ai');

        return view('admin.settings.overview', [
            'sections' => $this->sectionDescriptors(),
            'status' => [
                'mail_configured' => ! empty($mail['host']) && ! empty($mail['from_address']),
                'm365_enabled' => (bool) ($m365['enabled'] ?? false),
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
            ['slug' => 'branding', 'route' => 'admin.settings.branding', 'label' => 'Branding', 'icon' => 'cog', 'description' => 'Name, Logo, Farben + Benutzerfelder.'],
            ['slug' => 'ai', 'route' => 'admin.settings.ai', 'label' => 'KI', 'icon' => 'cog', 'description' => 'OpenAI / DeepSeek / Ollama.'],
            ['slug' => 'documents', 'route' => 'admin.settings.documents', 'label' => 'Dokumente', 'icon' => 'document', 'description' => 'Archive, Retention, Rollen-Zuordnung.'],
            ['slug' => 'sharing', 'route' => 'admin.settings.sharing', 'label' => 'Sharing', 'icon' => 'cog', 'description' => 'Caps fuer externe Freigaben.'],
            ['slug' => 'support', 'route' => 'admin.settings.support', 'label' => 'IT-Support', 'icon' => 'cog', 'description' => 'Support-Formular fuer Benutzer (Mail oder Ticket-API).'],
            ['slug' => 'integrations', 'route' => 'admin.settings.integrations', 'label' => 'Integrationen', 'icon' => 'cog', 'description' => 'Microsoft Teams, weitere externe Connectors.'],
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
}
