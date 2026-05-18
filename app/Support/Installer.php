<?php

namespace App\Support;

class Installer
{
    public static function markerPath(): string
    {
        return storage_path('app/.installed');
    }

    public static function isInstalled(): bool
    {
        return is_file(self::markerPath());
    }

    public static function markInstalled(): void
    {
        @file_put_contents(self::markerPath(), json_encode([
            'installed_at' => now()->toIso8601String(),
            'version' => (string) @file_get_contents(base_path('.version')) ?: null,
        ], JSON_PRETTY_PRINT));
    }
}
