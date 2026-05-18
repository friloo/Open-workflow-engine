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
        $roles = \App\Models\Role::orderBy('name')->get(['id', 'name', 'slug']);
        return view('admin.settings.index', [
            'mail' => Settings::group('mail') + $this->defaults(),
            'm365' => Settings::group('auth.m365') + $this->m365Defaults(),
            'roles' => $roles,
            'branding' => Settings::group('branding') + $this->brandingDefaults(),
            'customFields' => Settings::get('users.custom_fields', []),
            'documentTypes' => \App\Support\DocumentTypes::all(),
            'roleDocumentTypes' => \App\Support\DocumentTypes::roleMapping(),
            'shares' => [
                'max_expiry_days' => (int) Settings::get('shares.max_expiry_days', 90),
                'default_expiry_days' => (int) Settings::get('shares.default_expiry_days', 14),
                'review_interval_days' => (int) Settings::get('shares.review_interval_days', 7),
                'review_grace_days' => (int) Settings::get('shares.review_grace_days', 3),
            ],
            'retention' => Settings::get('attachments.retention', []),
        ]);
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

        return redirect()->route('admin.settings.index')->with('status', 'Mail-Konfiguration gespeichert.');
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

        return redirect()->route('admin.settings.index')->with('status', 'Microsoft-365-Konfiguration gespeichert.');
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
