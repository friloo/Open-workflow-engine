# Aufbewahrungsregeln

Pro Dokumenttyp legt der Admin fest, wie lange das Dokument mindestens
und maximal aufbewahrt werden soll und was nach Ablauf passiert.

## Konfiguration

*Verwaltung -> Systemeinstellungen -> Aufbewahrungsregeln pro Dokumenttyp*

Pro Regel:

- **Dokumenttyp** — der Name, wie in den Dokumenttypen oben angelegt.
- **Min. Jahre** — Mindestaufbewahrungsdauer (informativ).
- **Max. Jahre** — Schwelle, ab der die Aktion greift.
- **Nach Ablauf**:
  - **Pruefung markieren** — nur ein Audit-Eintrag, Dokument bleibt
    sichtbar (Empfehlung fuer Vertraege).
  - **Archivieren (Soft-Delete)** — verschwindet aus den Listen, Datei
    bleibt physisch erhalten.
  - **Endgueltig loeschen** — DB-Eintrag und Datei werden entfernt.
    Audit-Eintrag bleibt erhalten (mit Hash).

## Cron

`documents:retention-check` laeuft taeglich um 03:15 (siehe
`routes/console.php`). Per Hand testbar:

```bash
php artisan documents:retention-check --dry-run
```

Loggt jede betroffene Akte und die geplante Aktion, ohne sie auszufuehren.

## Audit

- `settings.retention.updated` — bei Aenderungen in der Verwaltung
- `document.retention.review_due` — Markierung zur Pruefung
- `document.retention.archived` — Soft-Delete durchgefuehrt
- `document.retention.deleted` — endgueltig entfernt
