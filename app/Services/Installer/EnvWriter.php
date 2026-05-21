<?php

namespace App\Services\Installer;

/**
 * Schmale Helper-Klasse, die .env-Einträge setzt (oder anlegt) ohne
 * existierende Kommentare/Reihenfolge zu zerstören. Werte werden bei
 * Bedarf gequotet.
 */
class EnvWriter
{
    public function __construct(private readonly string $path) {}

    public static function default(): self
    {
        return new self(base_path('.env'));
    }

    /** Stellt sicher, dass .env existiert. Wenn nicht: aus .env.example kopieren. */
    public function ensureFile(): void
    {
        if (is_file($this->path)) return;
        $example = base_path('.env.example');
        if (is_file($example)) {
            @copy($example, $this->path);
            return;
        }
        @file_put_contents($this->path, "APP_NAME=OWE\nAPP_ENV=production\nAPP_DEBUG=false\n");
    }

    /** @param array<string, string|null> $values */
    public function set(array $values): void
    {
        $this->ensureFile();
        $content = (string) @file_get_contents($this->path);
        $lines = preg_split('/\r\n|\n|\r/', $content) ?: [];

        foreach ($values as $key => $value) {
            $key = strtoupper(preg_replace('/[^A-Z0-9_]/i', '', (string) $key));
            if ($key === '') continue;
            $line = $key.'='.$this->formatValue((string) ($value ?? ''));

            $found = false;
            foreach ($lines as $i => $existing) {
                if (preg_match('/^\s*'.preg_quote($key, '/').'\s*=/', $existing)) {
                    $lines[$i] = $line;
                    $found = true;
                    break;
                }
            }
            if (! $found) {
                $lines[] = $line;
            }
        }

        @file_put_contents($this->path, implode("\n", $lines));
    }

    private function formatValue(string $value): string
    {
        // Quoten, wenn Whitespace, # oder Sonderzeichen vorkommen.
        if ($value === '') return '';
        if (preg_match('/[\s#"\']/', $value)) {
            return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
        }
        return $value;
    }

    public function generateAppKey(): string
    {
        $bytes = random_bytes(32);
        return 'base64:'.base64_encode($bytes);
    }
}
