<?php

namespace App\Services\Installer;

/**
 * Pruefungen fuer den Erstinstallations-Wizard: PHP, Extensions, Schreibrechte.
 */
class InstallChecker
{
    public const MIN_PHP = '8.2.0';

    public const REQUIRED_EXTENSIONS = [
        'pdo', 'mbstring', 'openssl', 'json', 'zip', 'fileinfo', 'curl',
        'tokenizer', 'xml', 'ctype', 'dom',
    ];

    public const RECOMMENDED_EXTENSIONS = [
        'pdo_sqlite' => 'SQLite-Treiber (Standard-Datenbank)',
        'pdo_mysql' => 'MySQL/MariaDB-Treiber',
        'gd' => 'Bildverarbeitung (z. B. PDF-Vorschauen)',
        'imap' => 'IMAP-Postfaecher (alternativ: ueber webklex/php-imap ohne ext-imap)',
    ];

    /** @return array<int, array{name:string, status:'ok'|'fail'|'warn', message:string}> */
    public function checks(): array
    {
        $out = [];

        // PHP-Version
        $php = PHP_VERSION;
        $out[] = [
            'name' => 'PHP-Version',
            'status' => version_compare($php, self::MIN_PHP, '>=') ? 'ok' : 'fail',
            'message' => "PHP {$php} (benoetigt >= ".self::MIN_PHP.')',
        ];

        // Pflicht-Extensions
        $missing = array_values(array_filter(self::REQUIRED_EXTENSIONS, fn ($e) => ! extension_loaded($e)));
        $out[] = [
            'name' => 'PHP-Extensions (Pflicht)',
            'status' => empty($missing) ? 'ok' : 'fail',
            'message' => empty($missing)
                ? 'Alle '.count(self::REQUIRED_EXTENSIONS).' geladen.'
                : 'Fehlt: '.implode(', ', $missing),
        ];

        // Empfohlene Extensions (nur Warnung)
        $missingRec = [];
        foreach (self::RECOMMENDED_EXTENSIONS as $ext => $desc) {
            if (! extension_loaded($ext)) $missingRec[] = "{$ext} ({$desc})";
        }
        $out[] = [
            'name' => 'PHP-Extensions (empfohlen)',
            'status' => empty($missingRec) ? 'ok' : 'warn',
            'message' => empty($missingRec) ? 'Alle empfohlenen geladen.' : 'Fehlt: '.implode('; ', $missingRec),
        ];

        // Schreibrechte
        foreach (['storage', 'bootstrap/cache'] as $dir) {
            $path = base_path($dir);
            $writable = is_dir($path) && is_writable($path);
            $out[] = [
                'name' => "Schreibrecht {$dir}",
                'status' => $writable ? 'ok' : 'fail',
                'message' => $writable ? $path : "Nicht beschreibbar oder fehlt: {$path}",
            ];
        }

        // vendor/ vorhanden? (Wenn nicht, koennten wir gar nicht booten —
        // also nur Hinweis. Hilft bei manuellen Uploads, falls jemand
        // versehentlich nur den Source ohne vendor hochgeladen hat.)
        $vendorOk = is_file(base_path('vendor/autoload.php'));
        $out[] = [
            'name' => 'vendor/ (Composer-Dependencies)',
            'status' => $vendorOk ? 'ok' : 'fail',
            'message' => $vendorOk
                ? 'autoload.php gefunden — Release-Paket war vollstaendig.'
                : 'vendor/autoload.php fehlt. Bitte das Release-ZIP MIT vendor/-Ordner verwenden (oder lokal "composer install --no-dev" vor dem Upload).',
        ];

        // .env
        $envExists = is_file(base_path('.env'));
        $exampleExists = is_file(base_path('.env.example'));
        $out[] = [
            'name' => '.env',
            'status' => $envExists ? 'ok' : ($exampleExists ? 'warn' : 'fail'),
            'message' => $envExists
                ? '.env vorhanden'
                : ($exampleExists ? '.env wird beim Speichern aus .env.example angelegt' : 'Weder .env noch .env.example vorhanden!'),
        ];

        // App-Key
        $appKey = (string) env('APP_KEY', '');
        $out[] = [
            'name' => 'APP_KEY',
            'status' => $appKey !== '' ? 'ok' : 'warn',
            'message' => $appKey !== '' ? 'gesetzt' : 'wird vom Installer automatisch generiert',
        ];

        return $out;
    }

    public function canProceed(): bool
    {
        foreach ($this->checks() as $c) {
            if ($c['status'] === 'fail') return false;
        }
        return true;
    }
}
