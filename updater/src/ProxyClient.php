<?php

namespace Updater;

/**
 * HTTPS-Client an den Update-Proxy. Reine curl-Calls, keine extra
 * Composer-Dependencies. Liefert dekodierte JSON-Payloads bzw. rohe
 * Bytes (fuer /zip + /download).
 *
 * Wichtig: Proxy kann JSON-Errors mit HTTP 200 senden, deshalb prueft
 * der Client zusaetzlich auf 'error' im Response-Body, nicht nur den
 * Statuscode.
 */
final class ProxyClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $userAgent = 'open-workflow-engine-Updater/1.0',
        private readonly int $timeoutSeconds = 60,
    ) {}

    /** @return array<string, mixed> */
    public function version(): array
    {
        return $this->getJson('/version');
    }

    /** @return array<string, mixed> */
    public function check(string $currentSha): array
    {
        return $this->getJson('/check?current_sha='.urlencode($currentSha));
    }

    /** @return array<int, array<string, mixed>> */
    public function files(string $path = ''): array
    {
        $r = $this->getJson('/files'.($path !== '' ? '?path='.urlencode($path) : ''));
        return is_array($r) ? $r : [];
    }

    /** Rohe Bytes der Datei am Pfad $path */
    public function download(string $path): string
    {
        return $this->getRaw('/download/'.ltrim($path, '/'));
    }

    /** Speichert das ZIP in $destination und gibt den Pfad zurueck. */
    public function downloadZip(string $sha, string $destination): string
    {
        $bytes = $this->getRaw('/zip?ref='.urlencode($sha));
        if ($bytes === '' || strncmp($bytes, "PK", 2) !== 0) {
            throw new \RuntimeException('ZIP-Antwort vom Proxy ist kein gueltiges ZIP-Archiv.');
        }
        if (file_put_contents($destination, $bytes) === false) {
            throw new \RuntimeException("ZIP konnte nicht nach {$destination} geschrieben werden.");
        }
        return $destination;
    }

    /** @return array<string, mixed> */
    private function getJson(string $path): array
    {
        $body = $this->getRaw($path);
        $data = json_decode($body, true);
        if (! is_array($data)) {
            throw new \RuntimeException("Proxy {$path}: Antwort ist kein JSON: ".substr($body, 0, 200));
        }
        // Proxy darf JSON-Errors mit HTTP 200 senden
        if (! empty($data['error'])) {
            throw new \RuntimeException("Proxy {$path}: ".$data['error']);
        }
        return $data;
    }

    private function getRaw(string $path): string
    {
        $url = rtrim($this->baseUrl, '/').$path;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_FAILONERROR => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $body = curl_exec($ch);
        $err = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false) {
            throw new \RuntimeException("Proxy {$url} unerreichbar: {$err}");
        }
        if ($code >= 400) {
            throw new \RuntimeException("Proxy {$url} liefert HTTP {$code}: ".substr((string) $body, 0, 200));
        }
        return (string) $body;
    }
}
