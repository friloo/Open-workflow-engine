<?php

namespace App\Support;

/**
 * Validiert URLs gegen SSRF: blockt private IPs, localhost und Nicht-HTTP-Schemes.
 */
class SafeHttpUrl
{
    /** Bekannte gefaehrliche Hosts. */
    private const BLOCKED_HOSTS = [
        'localhost', '127.0.0.1', '0.0.0.0', '::1',
        'metadata.google.internal',
        '169.254.169.254', // AWS/GCP/Azure-Metadaten
    ];

    /**
     * Wirft eine RuntimeException, wenn die URL nicht sicher ist.
     */
    public static function assertSafe(string $url): void
    {
        $parsed = parse_url($url);
        if ($parsed === false || empty($parsed['scheme']) || empty($parsed['host'])) {
            throw new \RuntimeException("Ungültige URL: {$url}");
        }
        $scheme = strtolower($parsed['scheme']);
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new \RuntimeException("Schema {$scheme} ist nicht erlaubt.");
        }
        $host = strtolower($parsed['host']);

        if (in_array($host, self::BLOCKED_HOSTS, true)) {
            throw new \RuntimeException("Host {$host} ist gesperrt (SSRF-Schutz).");
        }

        // Wenn der Host eine IP ist: gegen private Bereiche prüfen
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (! self::isPublicIp($host)) {
                throw new \RuntimeException("Private/loopback IP ist gesperrt (SSRF-Schutz).");
            }
            return;
        }

        // Hostnamen auflösen — alle A/AAAA-Records prüfen
        $ips = @gethostbynamel($host) ?: [];
        foreach ($ips as $ip) {
            if (! self::isPublicIp($ip)) {
                throw new \RuntimeException("Host {$host} löst auf private IP {$ip} auf (SSRF-Schutz).");
            }
        }
    }

    public static function isSafe(string $url): bool
    {
        try {
            self::assertSafe($url);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private static function isPublicIp(string $ip): bool
    {
        return (bool) filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        );
    }

    /**
     * Entfernt Query-String aus einer URL für das Audit-Log
     * (Secrets in Query-Strings landen so nicht im Log).
     */
    public static function redactForLog(string $url): string
    {
        $p = parse_url($url);
        if (! $p) return '[invalid]';
        $out = ($p['scheme'] ?? 'http').'://'.($p['host'] ?? '');
        if (! empty($p['port'])) $out .= ':'.$p['port'];
        if (! empty($p['path'])) $out .= $p['path'];
        if (! empty($p['query'])) $out .= '?…';
        return $out;
    }
}
