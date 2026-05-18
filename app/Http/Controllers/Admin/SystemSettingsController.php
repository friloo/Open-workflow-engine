<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
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
        return view('admin.settings.index', [
            'mail' => Settings::group('mail') + $this->defaults(),
        ]);
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
}
