<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Direkte LDAP/Active-Directory-Anbindung ohne Extra-Package.
 *
 * Flow:
 *  1. Service-Account bindet sich am LDAP-Server (bind_dn + bind_password).
 *  2. Sucht den User per Filter (z. B. sAMAccountName={username}).
 *  3. Re-bindet als gefundener User mit dem eingegebenen Passwort.
 *  4. Liefert Attribute (mail, displayName, dn) zurueck.
 *
 * Falls die PHP-LDAP-Extension nicht geladen ist, kommt eine
 * sprechende Fehlermeldung. So bleibt die App startbar auch ohne
 * `php-ldap` (Default-Build bei vielen Distros).
 *
 * Klasse ist absichtlich kein static-Helper, damit Tests via
 * `$this->app->bind(LdapAuthenticator::class, ...)` einen Fake einklinken.
 */
class LdapAuthenticator
{
    /**
     * @return array{ok: bool, dn?: string, email?: string, name?: string, error?: string}
     */
    public function authenticate(string $username, string $password): array
    {
        if (! function_exists('ldap_connect')) {
            return ['ok' => false, 'error' => 'PHP-LDAP-Extension nicht installiert (php-ldap fehlt).'];
        }
        if ($username === '' || $password === '') {
            return ['ok' => false, 'error' => 'Benutzername und Passwort erforderlich.'];
        }

        $host = (string) config('services.ldap.host');
        $port = (int) (config('services.ldap.port') ?: 389);
        $useTls = (bool) config('services.ldap.use_tls', false);
        $baseDn = (string) config('services.ldap.base_dn');
        $bindDn = (string) config('services.ldap.bind_dn');
        $bindPw = (string) config('services.ldap.bind_password');
        $filterTpl = (string) (config('services.ldap.user_filter') ?: '(&(objectClass=user)(sAMAccountName={username}))');
        $emailAttr = strtolower((string) (config('services.ldap.email_attribute') ?: 'mail'));
        $nameAttr = strtolower((string) (config('services.ldap.name_attribute') ?: 'displayName'));

        if ($host === '' || $baseDn === '') {
            return ['ok' => false, 'error' => 'LDAP-Host oder Base-DN fehlt in der Konfiguration.'];
        }

        $conn = @ldap_connect($host, $port);
        if (! $conn) {
            return ['ok' => false, 'error' => "Verbindung zu {$host}:{$port} fehlgeschlagen."];
        }

        @ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
        @ldap_set_option($conn, LDAP_OPT_REFERRALS, 0);
        @ldap_set_option($conn, LDAP_OPT_NETWORK_TIMEOUT, 5);

        if ($useTls) {
            if (! @ldap_start_tls($conn)) {
                $err = ldap_error($conn);
                @ldap_close($conn);
                return ['ok' => false, 'error' => "StartTLS fehlgeschlagen: {$err}"];
            }
        }

        if ($bindDn !== '') {
            if (! @ldap_bind($conn, $bindDn, $bindPw)) {
                $err = ldap_error($conn);
                @ldap_close($conn);
                Log::warning('LDAP service-bind failed', ['dn' => $bindDn, 'error' => $err]);
                return ['ok' => false, 'error' => "Service-Bind fehlgeschlagen: {$err}"];
            }
        }

        // Username-Escaping um LDAP-Injection zu verhindern.
        $safeUser = self::escapeFilterValue($username);
        $filter = str_replace('{username}', $safeUser, $filterTpl);

        $search = @ldap_search($conn, $baseDn, $filter, [$emailAttr, $nameAttr, 'dn', 'cn']);
        if (! $search) {
            $err = ldap_error($conn);
            @ldap_close($conn);
            return ['ok' => false, 'error' => "Suche fehlgeschlagen: {$err}"];
        }

        $entries = @ldap_get_entries($conn, $search);
        if (! $entries || ($entries['count'] ?? 0) === 0) {
            @ldap_close($conn);
            return ['ok' => false, 'error' => 'Benutzer im Verzeichnis nicht gefunden.'];
        }
        if ($entries['count'] > 1) {
            @ldap_close($conn);
            return ['ok' => false, 'error' => 'Filter ist nicht eindeutig (mehr als ein Treffer).'];
        }

        $entry = $entries[0];
        $userDn = (string) $entry['dn'];

        // Eigentliche Authentifizierung: re-bind als User mit dessen Passwort.
        if (! @ldap_bind($conn, $userDn, $password)) {
            @ldap_close($conn);
            return ['ok' => false, 'error' => 'Ungueltige Anmeldedaten.'];
        }

        $email = isset($entry[$emailAttr][0]) ? strtolower((string) $entry[$emailAttr][0]) : '';
        $name = $entry[$nameAttr][0] ?? ($entry['cn'][0] ?? $username);

        @ldap_close($conn);
        return ['ok' => true, 'dn' => $userDn, 'email' => $email, 'name' => $name];
    }

    /**
     * RFC4515-konforme Eskapierung von User-Eingabe im Filter.
     * Verhindert LDAP-Injection.
     */
    private static function escapeFilterValue(string $value): string
    {
        return str_replace(
            ['\\', '*', '(', ')', "\x00"],
            ['\\5c', '\\2a', '\\28', '\\29', '\\00'],
            $value,
        );
    }
}
