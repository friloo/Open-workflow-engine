# System-Update

Self-hosted Updates ueber einen HTTPS-Proxy. Es werden 40-stellige
Git-SHA-Pointer ausgeliefert — keine Tags, keine semver. Updates sind
nicht zwangsweise: Admins ziehen wenn es ihnen passt.

## Architektur

```
[OWE]  ->  https://update.loheide.eu/open-workflow-engine{,-development}
            -> /version          -> 40-stellige SHA
            -> /zip?ref=<SHA>    -> Repo-ZIP
```

Der Channel kommt aus `system_settings.update.channel`:

- `stable` — Standard, Produktion.
- `development` — Vorschau, **nicht** fuer Produktion.

Channel-Wechsel ueber *Verwaltung -> System-Update -> Channel*.

## Was beim Update passiert

1. `GET /version` -> Soll-SHA.
2. Vergleich mit `.version` im Projekt-Root.
3. ZIP ueber `GET /zip?ref=<SHA>` herunterladen, in
   `storage/app/.update-staging/` entpacken.
4. **Maintenance an**: leere Datei `.maintenance` im Projekt-Root.
   `public/index.php` antwortet ab dann mit 503 ohne Laravel zu booten.
5. Dateien aus Staging in das Projekt kopieren — **ausser** den
   geschuetzten Pfaden:
   - `.env`, `.env.backup`
   - `storage/`, `bootstrap/cache/`
   - `database/database.sqlite{,-journal,-shm,-wal}`
   - `public/storage`, `public/.htaccess`, `.htaccess`
6. `composer install --no-dev --optimize-autoloader`
7. `php artisan migrate --force`
8. `.version` auf neue SHA setzen.
9. **Maintenance aus** (passiert in einem PHP-`finally`-Block, d. h.
   auch bei einem Crash).

Fortschritt landet in `storage/app/.update-progress` (JSON). Die UI
pollt `/admin/update/status` alle 5 Sekunden und zeigt Stage + Meldung.

## Konsole

```bash
php artisan owe:update --check     # nur pruefen
php artisan owe:update             # installieren
```

Permission `system.update` (Default: Admin).

## Audit

- `update.channel.changed` — Channel-Wechsel
- `update.completed` — Update erfolgreich, mit alter und neuer SHA
- `update.failed` — Update fehlgeschlagen, mit Fehlertext
