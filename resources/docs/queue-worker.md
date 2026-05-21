# Queue-Worker / Hintergrund-Jobs

OCR, ZUGFeRD-Parse und KI-Aufrufe sind die langsamsten Operationen
in OWE. Standardmaessig laufen sie **synchron** im Upload-Request —
das Hochladen einer 50-Seiten-PDF dauert dann ein paar Sekunden,
weil tesseract erst durchgehen muss.

Wer das nicht will, schaltet auf einen **Queue-Worker**:

- Uploads sind sofort fertig (Anhang ist da, OCR-Status: `pending`).
- Hintergrund-Worker arbeitet die Jobs ab.
- Bei vielen / grossen Uploads (z. B. Bulk-Import von 1000 Belegen)
  ist das der einzige Weg um den Webserver nicht zu blockieren.

> [!IMPORTANT]
> Queue-Worker ist **optional**. Ohne ihn funktioniert OWE wie bisher
> — alles synchron, kein zusaetzlicher Prozess noetig. Bei wenigen
> Uploads pro Tag braucht's das auch nicht.

## Status-Seite

[/admin/queue](app:admin.queue.index) zeigt:
- Pending-Count (Jobs in der Warteschlange)
- Failed-Count (Jobs die mehrfach gescheitert sind)
- Aktive QUEUE_CONNECTION
- Ob QUEUE_OCR aktiv ist

Sichtbar fuer Admins mit `system.health`-Permission.

## Setup in 3 Schritten

### 1. Connection waehlen

In der `.env`:

```ini
QUEUE_CONNECTION=database
QUEUE_OCR=true
```

`database` reicht fuer KMU; `redis` ist schneller wenn du eh Redis
fuer Cache/Sessions nutzt.

`QUEUE_OCR=true` schaltet OCR + Indexfeld-Extraktion auf Background.
Wer `QUEUE_CONNECTION=database` aber `QUEUE_OCR=false` setzt: andere
Jobs (z. B. Webhook-Dispatcher) gehen in die Queue, OCR bleibt
synchron.

### 2. Worker starten

Empfohlen: systemd-Unit, die den Worker neu startet wenn er crasht.

```ini
# /etc/systemd/system/owe-queue.service
[Unit]
Description=OWE Queue Worker
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/srv/owe
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=2 --max-time=3600
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

Aktivieren:

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now owe-queue
journalctl -u owe-queue -f
```

`--max-time=3600` sorgt dafuer, dass der Worker alle Stunde neustartet
— verhindert Memory-Leaks ueber lange Laufzeit.

Alternative wenn du keinen systemd-Zugriff hast: `supervisor` mit
einer `.conf` analog. Oder ein einfacher Cron-Job alle 5 Minuten
mit `php artisan queue:work --stop-when-empty` (laeuft bis nichts
mehr da ist, dann beendet er sich — Cron startet ihn wieder).

### 3. Verifizieren

[Admin → Queue-Worker](app:admin.queue.index): Pending-Count sollte
nicht permanent steigen. Wenn doch — Worker laeuft nicht oder
zu wenige.

## Fehlgeschlagene Jobs

Wenn ein Job nach `--tries=2` immer noch scheitert, landet er in
`failed_jobs`. CLI-Befehle:

```bash
php artisan queue:failed              # alle anzeigen
php artisan queue:retry all           # alle nochmal versuchen
php artisan queue:retry 42            # nur Job-ID 42
php artisan queue:forget 42           # endgueltig loeschen
```

Die letzten 10 erscheinen auch auf der Status-Seite mit der ersten
Zeile des Stack-Traces.

## Welche Jobs gibt's heute?

- **ProcessAttachmentOcr** — OCR + Indexfeld-Extraktion nach Upload.
  Greift nur wenn `QUEUE_OCR=true`.

Mehr Jobs kommen wenn das Volumen waechst — z. B. wenn Outgoing-
Webhooks asynchron gefeuert werden sollen oder PDF-Render-Knoten
parallel laufen sollen.

## Skalierung

Bei vielen Uploads:

```bash
# Mehrere Worker parallel
ExecStart=/usr/bin/php artisan queue:work --queue=high,default --sleep=3 --tries=2
```

Oder mehrere systemd-Instanzen. Bei Redis-Backend ist Multi-Worker
sicher; bei `database` muss der DB-Lock-Mechanismus reichen — Laravel
sperrt den Job ueber `reserved_at`, da gibt es keine Race-Probleme.

## Horizon

Fuer Redis-basierte Setups gibt's [Laravel Horizon](https://laravel.com/docs/horizon)
mit einem schoenen Dashboard, Auto-Balancing und Metriken. Optional —
nicht in OWE bundled, weil's eine Redis-Pflicht-Abhaengigkeit waere.

Installation falls gewollt:

```bash
composer require laravel/horizon
php artisan horizon:install
```

Dann erreichst du es unter `/horizon` (per Default nur fuer lokale
User; Production-Auth siehe Horizon-Docs).
