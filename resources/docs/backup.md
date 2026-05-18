# Backup & Restore

Tagessicherung von Datenbank und Anhaengen als ZIP. Permission
`system.backup` (Default: Admin).

## Was im Backup steckt

- **DB-Dump**:
  - SQLite: die `database/database.sqlite`-Datei direkt
  - MySQL/MariaDB: `mysqldump` (wenn verfuegbar), sonst PHP-basierter
    SQL-Dump
- **Anhaenge**: alle Dateien aus `storage/app/attachments/`
- **`manifest.json`**: Metadaten (Erstellzeit, DB-Driver, App-Version)

Speicherort: `storage/app/backups/owe-YYYY-MM-DD_HHMMSS.zip`.

## Automatisch

Cron-Lauf `backup:run` taeglich um **01:30**. Ueberholte Backups
werden anhand der **Retention** (Default 14 Tage) bereinigt.

Konfigurieren: *Verwaltung -> Backups -> Retention*.

## Manuell aus der UI

*Verwaltung -> Backups -> Backup jetzt erstellen.*
Pro Backup: **Download** oder **Loeschen**.

## Restore (nur CLI)

Aus Sicherheitsgruenden bewusst nicht per Klick:

```bash
php artisan backup:restore owe-2026-05-30_013000.zip
```

Der Befehl:

1. Fragt einmal nach (auser bei `--force`).
2. Setzt `.maintenance` -> die App liefert 503.
3. Ersetzt DB und Anhaenge.
4. Entfernt `.maintenance` (im finally-Block, auch bei Fehlern).

Falls das Schema neuer als das Backup ist, hinterher:

```bash
php artisan migrate --force
```

## Empfohlene Praxis

- **Off-Site-Backups**: ZIPs zusaetzlich auf Fremdsystem ziehen
  (z. B. `rsync` oder per Hosting-Tool). Standardspeicher ist
  `storage/app/backups/` auf demselben Server.
- **Probe-Restore**: 1x pro Quartal in einer Testumgebung pruefen,
  dass der Restore wirklich klappt.

## Audit

- `backup.created` — mit Dateiname und Groesse
- `backup.deleted` — bei manueller oder retention-Loeschung (manuell)
- `backup.restored` — mit Manifest
