<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Carbon;

/**
 * Aggregiert Login-Anomalien aus dem Audit-Log fuer das Admin-
 * Dashboard / die Security-Uebersicht.
 *
 * Kein neuer Speicher — wir lesen ausschliesslich aus dem existierenden
 * AuditLog (Events: auth.login, auth.login.failed, auth.login.blocked,
 * auth.ldap.login, auth.ldap.blocked).
 */
class LoginAnomalies
{
    /**
     * @return array{
     *   window_hours: int,
     *   failed_24h: int,
     *   blocked_24h: int,
     *   ok_24h: int,
     *   top_failed_emails: array<int, array{email:string, count:int}>,
     *   top_failed_ips: array<int, array{ip:string, count:int}>,
     *   suspicious_users: array<int, array{user_id:int, name:string, ip:string, at:string}>,
     *   recent_failures: array<int, array<string, mixed>>,
     * }
     */
    public function snapshot(int $hours = 24): array
    {
        $since = Carbon::now()->subHours($hours);

        $base = AuditLog::query()->where('created_at', '>=', $since);

        $failed24 = (clone $base)->where('event', 'auth.login.failed')->count();
        $blocked24 = (clone $base)->where('event', 'auth.login.blocked')->count();
        $ok24 = (clone $base)->whereIn('event', ['auth.login', 'auth.ldap.login'])->count();

        $failures = (clone $base)->where('event', 'auth.login.failed')
            ->latest('created_at')->limit(200)->get(['new_values', 'created_at']);

        $byEmail = [];
        $byIp = [];
        $recent = [];
        foreach ($failures as $f) {
            $payload = $f->new_values ?? [];
            $email = (string) ($payload['email'] ?? '');
            $ip = (string) ($payload['ip'] ?? '');
            if ($email !== '') $byEmail[$email] = ($byEmail[$email] ?? 0) + 1;
            if ($ip !== '') $byIp[$ip] = ($byIp[$ip] ?? 0) + 1;
            if (count($recent) < 8) {
                $recent[] = [
                    'email' => $email,
                    'ip' => $ip,
                    'ua' => $payload['ua'] ?? '',
                    'at' => $f->created_at?->toIso8601String(),
                ];
            }
        }
        arsort($byEmail); arsort($byIp);
        $topEmails = [];
        foreach (array_slice($byEmail, 0, 5, true) as $email => $count) {
            $topEmails[] = ['email' => $email, 'count' => $count];
        }
        $topIps = [];
        foreach (array_slice($byIp, 0, 5, true) as $ip => $count) {
            $topIps[] = ['ip' => $ip, 'count' => $count];
        }

        // Suspicious: User loggte sich erfolgreich von neuer IP ein
        // (IP noch nie zuvor in auth.login fuer diesen User gesehen)
        $suspicious = $this->newIpSuccesses($hours);

        return [
            'window_hours' => $hours,
            'failed_24h' => $failed24,
            'blocked_24h' => $blocked24,
            'ok_24h' => $ok24,
            'top_failed_emails' => $topEmails,
            'top_failed_ips' => $topIps,
            'suspicious_users' => $suspicious,
            'recent_failures' => $recent,
        ];
    }

    /**
     * Erfolgreiche Logins der letzten X Stunden, bei denen die Source-IP
     * fuer den User in den vorhergehenden 30 Tagen NICHT gesehen wurde.
     *
     * @return array<int, array{user_id:int, name:string, ip:string, at:string}>
     */
    private function newIpSuccesses(int $hours): array
    {
        $since = Carbon::now()->subHours($hours);
        $historyCut = Carbon::now()->subDays(30);

        $rows = AuditLog::query()
            ->whereIn('event', ['auth.login', 'auth.ldap.login'])
            ->where('created_at', '>=', $since)
            ->whereNotNull('user_id')
            ->with('user:id,name')
            ->latest('created_at')->limit(100)
            ->get(['id', 'user_id', 'ip_address', 'created_at']);

        $out = [];
        foreach ($rows as $r) {
            if (! $r->ip_address || ! $r->user_id) continue;
            $hasHistory = AuditLog::query()
                ->whereIn('event', ['auth.login', 'auth.ldap.login'])
                ->where('user_id', $r->user_id)
                ->where('ip_address', $r->ip_address)
                ->where('created_at', '<', $r->created_at)
                ->where('created_at', '>=', $historyCut)
                ->exists();
            if ($hasHistory) continue;
            $out[] = [
                'user_id' => $r->user_id,
                'name' => $r->user?->name ?? '—',
                'ip' => $r->ip_address,
                'at' => $r->created_at?->toIso8601String(),
            ];
            if (count($out) >= 10) break;
        }
        return $out;
    }
}
