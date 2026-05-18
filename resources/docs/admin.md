# Administration

## Benutzer und Rollen

- Permissions kleben an Rollen, nicht an Einzelpersonen.
- System-Rollen sind: `admin`, `workflow-designer`, `employee`, `auditor`.
- Eigene Rollen koennen erstellt werden und mit beliebigen Permissions
  belegt werden.

## Benutzerdefinierte Felder

Unter *Systemeinstellungen -> Benutzerdefinierte Felder* definierst du
Zusatz-Felder, die im User-Edit-Formular gerendert werden und in
Workflows als `{{ initiator_custom.<key> }}` oder
`{{ subject_user_custom.<key> }}` verwendbar sind.

## Audit-Log

Jede sicherheitsrelevante Aktion landet im Audit-Log. Eintraege sind
SHA-256-hashverkettet — Manipulation einer Zeile bricht die Kette und
wird vom **Integritaetscheck** unter *Audit -> Integritaetskette pruefen*
erkannt.

Audit-Eintraege koennen ueber Eloquent nicht modifiziert oder geloescht
werden.

## Cron / Scheduler

Ein einziger Cron-Eintrag (Server-seitig) reicht aus:

```
* * * * * cd /pfad/zur/app && php artisan schedule:run >> /dev/null 2>&1
```

Damit laufen automatisch:

- `workflow:check-due` (alle 5 Minuten) — Eskalation + Reminder
- `workflow:run-schedules` (stuendlich) — Wiedervorlagen
- `asset:check-due` (taeglich 06:00) — Asset-Faelligkeiten

## Webhooks

Siehe Abschnitt *Webhooks*.

## KI-Integration

Siehe Abschnitt *HTTP-Knoten + KI*.

## Branding

App-Name, Logo-Text und Primaerfarbe werden zur Laufzeit ins Layout
uebernommen.
