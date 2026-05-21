# Updater (zentral)

Auto-Update-System für Open Workflow Engine. Pull-Modell: dieser Updater
zieht den Code als ZIP vom Update-Proxy, entpackt nach Staging, kopiert
mit Schutz für sensible Pfade in Produktion und wendet eigene
Schema-Migrationen an.

```
[OWE-Instanz]  ←HTTPS→  [Proxy]  ←pull→  [Git-Repo]
```

## Proxy-URLs

| Channel | URL |
|---|---|
| `stable` | `https://update.loheide.eu/open-workflow-engine` |
| `development` | `https://update.loheide.eu/open-workflow-engine-development` |

## Komponenten

Alles unter `updater/`:

```
updater/
├── README.md                        Diese Datei
├── routes.php                       Route-Registrierung
├── src/
│   ├── ProxyClient.php              HTTPS-Calls (curl)
│   ├── UpdateManager.php            Orchestrierung
│   ├── UpdaterFactory.php           Channel-Auflösung
│   ├── StagingApplier.php           Staging → Production
│   ├── MigrationsRunner.php         Eigene SQL-Migrationen
│   ├── SqlSplitter.php              String/Kommentar-aware Split
│   └── UpdateController.php         /admin/update*-Endpoints
├── migrations/                      Eigene SQL-Files (alphabetisch)
├── templates/maintenance.html       503-Page
└── ui/index.blade.php               Admin-UI (extendet x-app-layout)
```

## Geschützte Pfade (werden nie überschrieben)

`config/`, `storage/`, `.env`, `.env.example`, `.git/`, `.gitignore`,
`public/uploads/`, `public/build/`, `vendor/`, `composer.lock`,
`node_modules/`, `*.sqlite*`, `*.db`, `database/database.sqlite`,
`.version`, `.maintenance`, `.update-staging*`, `.update-progress`,
`updater-settings.json`.

**Nicht** geschützt: `updater/` selbst — der Updater soll sich
aktualisieren können.

## Migration-System

Eigene Tracking-Tabelle `_updater_migrations` (Präfix vermeidet
Kollision mit Laravels `migrations`-Tabelle). Driver-aware Syntax für
SQLite und MySQL/MariaDB. SQL-Files in `updater/migrations/` werden
alphabetisch ausgeführt; 60-Sekunden-Cache-Lockfile verhindert
Pro-Request-Re-Run.

## Channel-Settings

In `updater-settings.json` im Projekt-Root (nicht in
Laravel-Config/Settings):

```json
{ "channel": "stable" }
```

Über `/admin/update` änderbar.

## Touch am Bestandscode

Genau **zwei** Eingriffe sind nötig (beide rückbau-trivial):

1. `composer.json` — PSR-4-Mapping `"Updater\\": "updater/src/"`
2. `routes/web.php` — eine Zeile am Ende:
   ```php
   require __DIR__.'/../updater/routes.php';
   ```

Der Maintenance-Hook in `public/index.php` (Zeilen 7–18) **war bereits
vorhanden** vor der Installation und prüft `.maintenance` im Root — der
Updater nutzt genau diesen Pfad.

## Rückbau

1. **Letzten Touch entfernen**: in `routes/web.php` die letzte Zeile
   (`require __DIR__.'/../updater/routes.php';`) löschen.
2. **Autoload-Mapping entfernen**: in `composer.json` den Eintrag
   `"Updater\\": "updater/src/"` aus `autoload.psr-4` löschen.
3. **Ordner löschen**: `rm -r updater/`
4. **Composer regenerieren**: `composer dump-autoload`
5. **Datenbank** (optional): `DROP TABLE _updater_migrations;`
6. **Settings + Artefakte** (optional): `rm -f .version
   .maintenance .update-progress .update-staging.zip updater-settings.json &&
   rm -rf .update-staging`

Der Maintenance-Hook in `public/index.php` schadet nicht, wenn er
stehen bleibt — er feuert nur wenn `.maintenance` existiert. Optional
wegnehmen falls gewünscht.

## Smoke-Test

Bei aktivem Proxy (oder Mock):

1. `composer dump-autoload`
2. Login als Admin mit Permission `system.settings`
3. `/admin/update` öffnen — aktuelle SHA wird angezeigt (oder „noch
   nie installiert" wenn `.version` fehlt)
4. „Auf Updates prüfen" → JSON-Antwort vom Proxy
5. Wenn `has_update`: „Update installieren" klickbar
6. Während Installation: Progress-Bar aktualisiert sich via Polling
   `/admin/update/progress`, anderes Frontend antwortet 503 (Maintenance)
7. Nach Erfolg: `.version` aktualisiert, `.maintenance` entfernt,
   Audit-Log-Eintrag `updater.installed`

## Häufige Fehler

- **`vendor/` wird mit übertragen** → ist in PROTECTED_PATHS, Composer
  läuft separat über `composer install` nach dem Update.
- **SQLite-DB verschwindet** → `*.sqlite*` und `*.db` sind in
  PROTECTED_PATHS.
- **`.maintenance` bleibt hängen** → `installUpdate()` räumt im
  `finally` auf. Manuell: `rm .maintenance`.
- **Migration läuft bei jedem Request** → 60-Sekunden-Cache via
  Lockfile in `sys_get_temp_dir()`.
- **Proxy liefert HTTP 200 mit Error-JSON** → ProxyClient prüft auf
  `error`-Feld im Body, nicht nur den Statuscode.

## Frontend-Maintenance-Hook (Bestand)

Bereits vorhanden in `public/index.php` Zeilen 7–18:

```php
if (file_exists(__DIR__.'/../.maintenance')) {
    http_response_code(503);
    header('Retry-After: 60');
    // ... 503-Page ausliefern
    exit;
}
```

Liest `.maintenance` im Projekt-Root — genau dort schreibt der Updater
beim `maintenanceOn()` hin. Wenn ihr lieber `updater/templates/maintenance.html`
auslesen wollt, könnt ihr die Echo-Zeilen durch ein `require` ersetzen.
