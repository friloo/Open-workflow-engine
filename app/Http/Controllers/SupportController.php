<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class SupportController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function show(Request $request): View
    {
        $cfg = Settings::group('support');
        if (empty($cfg['enabled'])) abort(404);

        return view('support.show', [
            'cfg' => $cfg + ['sidebar_label' => 'IT-Support', 'mode' => 'mail'],
        ]);
    }

    public function send(Request $request)
    {
        $cfg = Settings::group('support');
        if (empty($cfg['enabled'])) abort(404);

        $data = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            // Description darf jetzt länger sein, da das Modal automatisch
            // den aktuellen Page-Link anhängt.
            'description' => ['required', 'string', 'max:6000'],
        ]);

        $user = $request->user();
        $context = [
            'subject' => $data['subject'],
            'description' => $data['description'],
            'user_name' => $user->name,
            'user_email' => $user->email,
            'user_id' => $user->id,
            'app_name' => config('app.name'),
            'app_url' => config('app.url'),
            'timestamp' => now()->toIso8601String(),
        ];

        $mode = $cfg['mode'] ?? 'mail';
        $errors = [];
        $sentMail = false;
        $sentApi = false;

        if (in_array($mode, ['mail', 'both'], true)) {
            try {
                $this->sendMail($cfg, $context);
                $sentMail = true;
            } catch (\Throwable $e) {
                Log::warning('Support-Mail fehlgeschlagen', ['error' => $e->getMessage()]);
                $errors[] = 'Mail-Versand fehlgeschlagen: '.$e->getMessage();
            }
        }

        if (in_array($mode, ['api', 'both'], true)) {
            try {
                $this->sendApi($cfg, $context);
                $sentApi = true;
            } catch (\Throwable $e) {
                Log::warning('Support-API fehlgeschlagen', ['error' => $e->getMessage()]);
                $errors[] = 'Ticket-API fehlgeschlagen: '.$e->getMessage();
            }
        }

        $this->audit->log('support.ticket', null, null, [
            'subject' => $context['subject'],
            'mode' => $mode,
            'mail_sent' => $sentMail,
            'api_sent' => $sentApi,
            'errors' => $errors,
        ], 'Support-Ticket aus '.$user->email, $user->id);

        if ($errors && ! $sentMail && ! $sentApi) {
            $msg = implode(' · ', $errors);
            if ($request->wantsJson() || $request->expectsJson()) {
                return response()->json(['error' => $msg], 502);
            }
            return back()->withErrors(['support' => $msg])->withInput();
        }

        $status = 'Anfrage übermittelt.';
        if ($sentMail) $status .= ' Mail an Support gesendet.';
        if ($sentApi)  $status .= ' Ticket im Ticketsystem angelegt.';

        if ($request->wantsJson() || $request->expectsJson()) {
            return response()->json(['status' => $status, 'mail' => $sentMail, 'api' => $sentApi]);
        }
        return redirect()->route('support.show')->with('status', $status);
    }

    /** Schickt eine simple Plain-Text-Mail an die konfigurierte Support-Adresse. */
    private function sendMail(array $cfg, array $ctx): void
    {
        $to = $cfg['email'] ?? null;
        if (! $to) throw new \RuntimeException('Keine Support-Adresse konfiguriert.');

        $body = "Neues Support-Ticket\n"
            ."--------------------\n\n"
            ."Betreff:     {$ctx['subject']}\n"
            ."Absender:    {$ctx['user_name']} <{$ctx['user_email']}>\n"
            ."User-ID:     {$ctx['user_id']}\n"
            ."Eingegangen: {$ctx['timestamp']}\n"
            ."App:         {$ctx['app_name']} ({$ctx['app_url']})\n\n"
            ."Beschreibung:\n\n"
            ."{$ctx['description']}\n";

        Mail::raw($body, function ($m) use ($to, $ctx) {
            $m->to($to)
              ->subject('[Support] '.$ctx['subject'])
              ->replyTo($ctx['user_email'], $ctx['user_name']);
        });
    }

    /**
     * Schickt das gerenderte Body-Template als HTTP-Request an das
     * konfigurierte Ticketsystem. Platzhalter im Template werden mit
     * den Context-Werten gefüllt.
     */
    private function sendApi(array $cfg, array $ctx): void
    {
        $url = $cfg['api_url'] ?? '';
        if (! $url) throw new \RuntimeException('Keine API-URL konfiguriert.');

        $method = strtoupper($cfg['api_method'] ?? 'POST');
        $template = (string) ($cfg['api_body_template'] ?? '');
        $body = $this->renderTemplate($template, $ctx);

        $headers = ['Content-Type' => 'application/json'];
        foreach (($cfg['api_headers'] ?? []) as $h) {
            if (! empty($h['key'])) {
                $headers[$h['key']] = $this->renderTemplate((string) ($h['value'] ?? ''), $ctx);
            }
        }

        $resp = Http::withHeaders($headers)->timeout(15)->send($method, $url, [
            'body' => $body,
        ]);

        if ($resp->failed()) {
            throw new \RuntimeException('HTTP '.$resp->status().': '.substr($resp->body(), 0, 200));
        }
    }

    private function renderTemplate(string $tmpl, array $ctx): string
    {
        return preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/',
            fn ($m) => isset($ctx[$m[1]]) ? $this->jsonSafe((string) $ctx[$m[1]]) : $m[0],
            $tmpl,
        );
    }

    /**
     * Escaped die für JSON gefaehrlichen Zeichen. Templates sind
     * typisch JSON, also rohe " oder Newlines im User-Input würden den
     * Body ungültig machen.
     */
    private function jsonSafe(string $v): string
    {
        $out = json_encode($v, JSON_UNESCAPED_UNICODE);
        // json_encode liefert "string" inkl. Quotes — wir wollen nur den
        // escapeden Inhalt, da der Platzhalter im Template typisch
        // bereits in Quotes steht.
        return substr($out, 1, -1);
    }
}
