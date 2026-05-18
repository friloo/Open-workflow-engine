# System-Health

*Verwaltung -> System-Health* zeigt auf einen Blick, ob alle wichtigen
Komponenten arbeiten. Permission `system.health` (Default: Admin).

## Checks

- **Datenbank** — PDO-Verbindung + Anzahl Benutzer.
- **Speicherplatz** — `storage/app`-Disk: ab 80% belegt warnen, ab 90%
  Fehler.
- **Mail-Versand** — Mailer-Konfiguration. `log`/`array` -> Warnung
  (Mails landen nirgendwo).
- **IMAP-Postfaecher** — Fehler im `last_error` oder Postfach seit
  > 2h nicht mehr abgefragt -> Warnung.
- **OCR-Backlog** — Anzahl Anhaenge mit `ocr_status = pending`.
- **Failed Jobs** — Eintraege in `failed_jobs` (sofern Queue aktiv).
- **Audit-Kette** — laeuft `AuditLogger::verifyChain()`. Bruch der
  Hashkette -> Fehler.
- **Scheduler** — Sentinel `storage/framework/schedule-last-run` wird
  bei jedem Cron-Lauf aktualisiert. Aelter als 10 Minuten -> Cron
  laeuft nicht.
- **System-Update** — pendinger Update-Verfuegbarkeit, Wartungsmodus,
  Channel-Erreichbarkeit.
- **PHP** — Version + benoetigte Extensions.

## JSON-Endpoint

`GET /admin/health.json` liefert das Ganze als JSON inkl. Gesamtstatus
(`ok | warn | fail`). Praktisch fuer externe Monitoring-Tools (z. B.
Uptime-Kuma):

```bash
curl -s -H 'Cookie: laravel_session=...' https://owe.example.com/admin/health.json
```

(Aus Sicherheitsgruenden bleibt der Endpoint hinter dem Login.)
