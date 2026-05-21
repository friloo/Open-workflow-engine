<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;

class Settings
{
    /**
     * Keys whose value is encrypted at rest.
     */
    private const ENCRYPTED_KEYS = [
        'mail.password',
        'auth.m365.client_secret',
        'auth.oidc.client_secret',
        'auth.google.client_secret',
        'auth.ldap.bind_password',
        'ai.api_key',
    ];

    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        if (! self::tableReady()) {
            return self::$cache = [];
        }

        $rows = Setting::query()->get(['key', 'value']);
        $out = [];
        foreach ($rows as $row) {
            $out[$row->key] = self::maybeDecrypt($row->key, $row->value);
        }
        return self::$cache = $out;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::all()[$key] ?? $default;
    }

    public static function group(string $prefix): array
    {
        $prefix = rtrim($prefix, '.').'.';
        $out = [];
        foreach (self::all() as $k => $v) {
            if (str_starts_with($k, $prefix)) {
                $out[substr($k, strlen($prefix))] = $v;
            }
        }
        return $out;
    }

    public static function set(string $key, mixed $value, ?int $userId = null): void
    {
        $stored = in_array($key, self::ENCRYPTED_KEYS, true) && is_string($value) && $value !== ''
            ? Crypt::encryptString($value)
            : $value;

        Setting::updateOrCreate(
            ['key' => $key],
            ['value' => $stored, 'updated_at' => now(), 'updated_by' => $userId],
        );

        self::$cache = null;
    }

    public static function forget(string $key): void
    {
        Setting::where('key', $key)->delete();
        self::$cache = null;
    }

    public static function flush(): void
    {
        self::$cache = null;
    }

    private static function maybeDecrypt(string $key, mixed $value): mixed
    {
        if (! in_array($key, self::ENCRYPTED_KEYS, true)) {
            return $value;
        }
        if (! is_string($value) || $value === '') {
            return $value;
        }
        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return $value;
        }
    }

    private static function tableReady(): bool
    {
        try {
            return Schema::hasTable('system_settings');
        } catch (\Throwable) {
            return false;
        }
    }
}
