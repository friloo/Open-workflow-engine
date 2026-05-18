<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;

/**
 * Synchronisiert Benutzer aus Microsoft 365 / Entra ID via Graph API.
 *
 * Verwendet Client-Credentials-Flow (App-Only) — der Azure-AD-App muss
 * im Vorfeld die Application-Permission `User.Read.All` erteilt worden
 * sein (Admin Consent erforderlich).
 */
class MicrosoftGraphSync
{
    private const GRAPH_BASE = 'https://graph.microsoft.com/v1.0';

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @return array{created:int, updated:int, errors:array<string>}
     */
    public function syncUsers(?string $defaultRoleSlug = 'employee', ?int $triggeredBy = null): array
    {
        $config = config('services.microsoft-azure');
        if (empty($config['client_id']) || empty($config['client_secret']) || empty($config['tenant'])) {
            throw new \RuntimeException('M365-Konfiguration unvollstaendig.');
        }

        $token = $this->fetchAppToken($config);

        $created = 0;
        $updated = 0;
        $errors = [];
        $oidToUserId = [];        // m365_object_id => user_id
        $oidToManagerOid = [];    // m365_object_id => manager_object_id

        $url = self::GRAPH_BASE.'/users?$select=id,displayName,mail,userPrincipalName,jobTitle,department,mobilePhone,accountEnabled&$top=100';

        while ($url) {
            $resp = Http::withToken($token)->acceptJson()->get($url);
            if (! $resp->successful()) {
                throw new \RuntimeException('Graph-Aufruf fehlgeschlagen: HTTP '.$resp->status().' '.$resp->body());
            }
            $body = $resp->json();
            foreach ($body['value'] ?? [] as $row) {
                try {
                    [$mode, $user, $managerOid] = $this->upsertUser($row, $defaultRoleSlug);
                    if ($mode === 'created') $created++;
                    elseif ($mode === 'updated') $updated++;

                    $oidToUserId[$row['id']] = $user->id;
                    if ($managerOid) $oidToManagerOid[$row['id']] = $managerOid;
                } catch (\Throwable $e) {
                    $errors[] = ($row['userPrincipalName'] ?? $row['id']).': '.$e->getMessage();
                }
            }
            $url = $body['@odata.nextLink'] ?? null;
        }

        // Resolve managers in a second pass once all M365 users are known
        foreach (User::whereNotNull('m365_object_id')->cursor() as $u) {
            $managerOid = $this->fetchManagerOid($token, $u->m365_object_id);
            $u->forceFill(['m365_supervisor_object_id' => $managerOid])->save();
            if ($managerOid && isset($oidToUserId[$managerOid])) {
                $u->forceFill(['supervisor_id' => $oidToUserId[$managerOid]])->save();
            }
        }

        $this->audit->log(
            'm365.sync.completed',
            null,
            null,
            ['created' => $created, 'updated' => $updated, 'errors' => count($errors)],
            "M365-Sync: {$created} neu, {$updated} aktualisiert".(count($errors) ? ", ".count($errors)." Fehler" : ''),
            $triggeredBy,
        );

        return compact('created', 'updated', 'errors');
    }

    /** @return array{0:string,1:User,2:?string} */
    private function upsertUser(array $row, ?string $defaultRoleSlug): array
    {
        $oid = (string) $row['id'];
        $email = strtolower((string) ($row['mail'] ?? $row['userPrincipalName'] ?? ''));
        if (! $email) {
            throw new \RuntimeException('Kein E-Mail-Feld gesetzt.');
        }

        $payload = [
            'name' => $row['displayName'] ?? $email,
            'email' => $email,
            'm365_object_id' => $oid,
            'job_title' => $row['jobTitle'] ?? null,
            'department' => $row['department'] ?? null,
            'phone' => $row['mobilePhone'] ?? null,
            'is_active' => ! isset($row['accountEnabled']) || $row['accountEnabled'] === true,
            'prefer_m365_supervisor' => true,
        ];

        $user = User::withTrashed()->where('m365_object_id', $oid)->first()
            ?? User::withTrashed()->where('email', $email)->first();

        if ($user) {
            if ($user->trashed()) $user->restore();
            $original = $user->only(array_keys($payload));
            $user->forceFill($payload)->save();
            return ['updated', $user, null];
        }

        $user = User::create([
            ...$payload,
            'email_verified_at' => now(),
            'email_notifications_enabled' => true,
        ]);
        if ($defaultRoleSlug) {
            $user->assignRole($defaultRoleSlug);
        }
        return ['created', $user, null];
    }

    private function fetchManagerOid(string $token, string $userOid): ?string
    {
        $resp = Http::withToken($token)->acceptJson()
            ->get(self::GRAPH_BASE."/users/{$userOid}/manager?\$select=id");
        if ($resp->status() === 404) return null;
        if (! $resp->successful()) return null;
        return $resp->json('id');
    }

    /**
     * Verbindungs-/Berechtigungstest: holt ein App-Token und ruft Graph
     * minimal auf, um die Konfiguration zu validieren.
     *
     * @return array{ok:bool, message:string, user_count:?int}
     */
    public function testConnection(): array
    {
        $config = config('services.microsoft-azure');
        if (empty($config['client_id']) || empty($config['client_secret']) || empty($config['tenant'])) {
            return ['ok' => false, 'message' => 'Konfiguration unvollstaendig (Client-ID, Secret und Tenant sind Pflicht).', 'user_count' => null];
        }

        try {
            $token = $this->fetchAppToken($config);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Token-Abruf fehlgeschlagen: '.$e->getMessage(), 'user_count' => null];
        }

        $resp = Http::withToken($token)->acceptJson()
            ->get(self::GRAPH_BASE.'/users?$top=1&$count=true&$select=id', [], ['ConsistencyLevel' => 'eventual']);
        if (! $resp->successful()) {
            $body = $resp->json();
            $code = $body['error']['code'] ?? 'HTTP '.$resp->status();
            $msg = $body['error']['message'] ?? $resp->body();
            return ['ok' => false, 'message' => "Graph-Aufruf fehlgeschlagen ({$code}): {$msg}", 'user_count' => null];
        }

        $count = $resp->json('@odata.count');
        return [
            'ok' => true,
            'message' => 'Verbindung erfolgreich.'.($count !== null ? " Tenant enthaelt {$count} Benutzer." : ''),
            'user_count' => $count,
        ];
    }

    private function fetchAppToken(array $config): string
    {
        $tenant = $config['tenant'];
        $resp = Http::asForm()->post("https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token", [
            'grant_type' => 'client_credentials',
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
            'scope' => 'https://graph.microsoft.com/.default',
        ]);
        if (! $resp->successful()) {
            throw new \RuntimeException('Token-Endpoint Fehler: HTTP '.$resp->status().' '.$resp->body());
        }
        return $resp->json('access_token') ?? throw new \RuntimeException('Kein access_token empfangen.');
    }
}
