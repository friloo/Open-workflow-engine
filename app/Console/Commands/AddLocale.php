<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Bootstrapt eine neue UI-Sprache:
 *   php artisan locale:add en --name="English"
 *
 * Legt lang/<code>.json mit allen Keys aus lang/de.json an (Werte = Keys,
 * also vorerst untranslated, sodass deutsche Strings angezeigt werden bis
 * jemand sie ersetzt).
 *
 * Trägt den Code zusätzlich als Vorlage in config/app.php
 * unter 'available_locales' ein — Hinweis wird ausgegeben.
 */
class AddLocale extends Command
{
    protected $signature = 'locale:add {code : Locale-Code (z.B. en, es, fr)} {--name= : Anzeige-Name (z.B. English)}';

    protected $description = 'Legt eine neue Übersetzungs-JSON-Datei als Kopie von lang/de.json an.';

    public function handle(): int
    {
        $code = (string) $this->argument('code');
        if (! preg_match('/^[a-z]{2,3}(_[A-Z]{2})?$/', $code)) {
            $this->error("Ungültiger Locale-Code: {$code} (erwartet z.B. 'en', 'es', 'pt_BR').");
            return self::FAILURE;
        }

        $sourcePath = base_path('lang/de.json');
        $targetPath = base_path("lang/{$code}.json");

        if (! is_dir(base_path('lang'))) {
            mkdir(base_path('lang'), 0775, true);
        }
        if (! file_exists($sourcePath)) {
            $this->warn("lang/de.json existiert noch nicht — lege leere Vorlage an.");
            file_put_contents($sourcePath, json_encode(new \stdClass(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        if (file_exists($targetPath)) {
            if (! $this->confirm("lang/{$code}.json existiert bereits. Überschreiben?", false)) {
                return self::SUCCESS;
            }
        }

        $base = json_decode(file_get_contents($sourcePath), true) ?: [];
        // Identity-Translation: untranslated Werte bleiben gleich dem Key,
        // sodass deutsche Strings angezeigt werden bis jemand uebersetzt.
        ksort($base);
        file_put_contents($targetPath, json_encode($base, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $name = (string) ($this->option('name') ?: strtoupper($code));
        $this->info("Neue Locale-Datei erzeugt: lang/{$code}.json ({$name})");
        $this->line('');
        $this->line("Nächste Schritte:");
        $this->line("  1. config/app.php → 'available_locales' Array um folgenden Eintrag erweitern:");
        $this->line("       '{$code}' => '{$name}',");
        $this->line("  2. lang/{$code}.json bearbeiten und Werte übersetzen.");
        $this->line("  3. User können die Sprache im Profil auswählen.");
        $this->line('');
        $this->line("Hinweis: nur Strings, die in Blade-Views in {{ __('...') }} gewrappt sind, werden übersetzt.");

        return self::SUCCESS;
    }
}
