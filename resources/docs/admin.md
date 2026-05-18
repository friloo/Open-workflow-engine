# Administration

## Benutzer und Rollen

- Permissions kleben an Rollen, nicht an Einzelpersonen.
- System-Rollen: `admin`, `workflow-designer`, `employee`, `auditor`.
- Eigene Rollen koennen erstellt werden und mit beliebigen Permissions
  belegt werden.
- Permission `workflows.design` umfasst Entwerfen UND Aktivieren/Archivieren
  (frueher `workflows.publish` ist aufgegangen).

## Benutzerdefinierte Felder

Unter *Systemeinstellungen -> Benutzerdefinierte Felder* definierst du
Zusatz-Felder (Text, Zahl, Datum, Auswahl), die in der User-Edit-Maske
gerendert werden und in Workflows als
`@{{ initiator_custom.<key> }}` oder
`@{{ subject_user_custom.<key> }}` verwendbar sind.

Beispiele: Kostenstelle, Standort, Eintrittsdatum, Mitarbeiter-Nummer.

## Dokumenttypen + Berechtigungen

Unter *Systemeinstellungen* findest du zwei separate Karten:

- **Dokumenttypen**: einfache Liste (z. B. *Vertrag, Rechnung,
  Fuehrerschein*).
- **Berechtigungen je Rolle**: pro Rolle anklicken, welche Typen sie
  sehen darf. Eine Rolle kann mehrere Typen sehen, ein Typ kann
  mehrere Rollen erlauben.

## Felder-Schemas pro Dokumenttyp

*Verwaltung -> Dokument-Schemas*: pro Dokumenttyp legst du Felder an
(z. B. `rechnungsnummer`, `kostenstelle`, `betrag_brutto`, `iban`) und
waehlst pro Feld eine Erkennungs-Methode (Heuristik, Lookup-Liste,
Regex, KI). Werte werden nach OCR automatisch in
`attachments.indexed_fields` geschrieben und stehen im Workflow als
`{{ doc.indexed_fields.<feld> }}` zur Verfuegung.

Details: *Felder-Schemas pro Dokumenttyp* und *Postkorb + Lookup-
Routing*; vollstaendiges Setup: *Cookbook: Rechnungseingang*.

## Aufbewahrungsregeln

*Verwaltung -> Systemeinstellungen -> Aufbewahrungsregeln*: pro
Dokumenttyp Min/Max-Jahre + Aktion (Pruefung markieren, archivieren,
endgueltig loeschen). Wird taeglich um 03:15 angewendet.

## System-Health und Update

- **System-Health** (Permission `system.health`) zeigt eine Statusseite
  mit Karten fuer DB, Speicherplatz, Mail, IMAP, Audit-Kette,
  Scheduler etc. Plus JSON-Endpoint `/admin/health.json` fuer
  Monitoring.
- **System-Update** (Permission `system.update`) zieht direkt aus der
  UI die neueste Version vom konfigurierten Channel
  (stable/development) und laeuft `composer install` + `migrate`
  hinter einer Wartungsseite.

## E-Mail-Postfaecher (IMAP)

*Verwaltung -> E-Mail-Postfaecher* (Permission `mailboxes.manage`):
mehrere IMAP-Postfaecher mit fixem Dokumenttyp und optionalem
Workflow-Auto-Start. `mail:fetch` laeuft alle 5 Minuten.

## Branding

App-Name, Logo-Text und Primaerfarbe werden zur Laufzeit ins Layout
uebernommen.

## Audit-Log

Jede sicherheitsrelevante Aktion landet im Audit-Log. Eintraege sind
SHA-256-hashverkettet — Manipulation einer Zeile bricht die Kette und
wird vom **Integritaetscheck** unter *Audit-Log -> Integritaetskette
pruefen* erkannt.

Audit-Eintraege koennen ueber Eloquent nicht modifiziert oder geloescht
werden.

DSGVO: `audit:cleanup` anonymisiert IP/User-Agent in Eintraegen aelter
als X Tage (Default 730). Laeuft monatlich via Scheduler.

## Bulk-Aktionen

Auf der **Vorgangs-Liste** kannst du mehrere laufende Workflow-Instanzen
gleichzeitig abbrechen — Checkboxen anhaken, Grund eingeben, Klick.

## Webhooks und Secrets

Siehe separate Abschnitte.

## KI-Integration

Siehe Abschnitt *KI im Designer*.

## Cron / Scheduler

Ein einziger Cron-Eintrag (Server-seitig) reicht aus:

```
* * * * * cd /pfad/zur/app && php artisan schedule:run >> /dev/null 2>&1
```

Damit laufen automatisch:

- `workflow:check-due` (alle 5 Minuten) — Eskalation + Reminder
- `workflow:run-schedules` (stuendlich) — Wiedervorlagen
- `asset:check-due` (taeglich 06:00) — Asset-Faelligkeiten
- `ocr:run-pending` (taeglich 02:30) — Nachholen von OCR-Extraktionen
- `audit:cleanup --days=730` (monatlich) — DSGVO-Anonymisierung
- `documents:retention-check` (taeglich 03:15) — Aufbewahrungsregeln
- `mail:fetch` (alle 5 Minuten) — IMAP-Postfaecher abrufen
- `shares:review` (taeglich 07:00) — Sharing-Link-Pruefungs-Mails
- Scheduler-Sentinel (jede Minute) — Health-Check weiss damit, dass
  der Cron laeuft
