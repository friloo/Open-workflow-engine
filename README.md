# Open Workflow Engine

Self-hosted workflow- und formularbasiertes Automatisierungstool fuer
Shared-Hosting (PHP 8.2+ / MySQL oder MariaDB).

> **Status:** Phase 4 + 5 — Microsoft 365 Single-Sign-On + Benutzer-Sync,
> wiederkehrende Workflows (Wiedervorlagen, z. B. Fuehrerschein-Pruefung)
> und ein Stand-Alone-Form-Builder. Phasen 1-3 sind enthalten.

## Geplanter Funktionsumfang

- Drag-and-Drop Workflow-Designer (Drawflow) — *Phase 2*
- Formular-Builder mit oeffentlich und intern erreichbaren Formularen — *Phase 2*
- Workflows ausgeloest durch Formulare, manuelle Starts, Zeitplaene und
  wiederkehrende Pruefungen (z. B. Fuehrerschein-Kontrolle alle X Monate) — *Phase 2*
- Mehrstufige Genehmigungen (Vorgesetzte oder Rollen) mit Karenzzeit
  und automatischer Eskalation an die naechste Instanz — *Phase 3*
- Microsoft 365 Single-Sign-On und Benutzer-Sync (inkl. Vorgesetzten-Feld) — *Phase 4*
- E-Mail-Benachrichtigungen pro Workflow-Schritt
- Rollenbasiertes Rechtesystem (keine Einzelberechtigungen)
- Revisionssichere, hashverkettete Audit-Historie

## Phase 4 — Microsoft 365 SSO + Benutzer-Sync

- **Konfiguration im Admin-Backend** unter *Systemeinstellungen*:
  Client-ID, Client-Secret (verschluesselt), Tenant-ID, Redirect-URI,
  Auto-Provisioning-Schalter und Standardrolle fuer neu angelegte
  Benutzer.
- **Login-Button** „Mit Microsoft anmelden" erscheint auf der
  Anmeldeseite, sobald M365 aktiviert und konfiguriert ist.
- **OAuth-Callback** legt unbekannte Benutzer optional automatisch an
  (Auto-Provisioning) und verknuepft sie ueber `m365_object_id`.
  Bestehende Benutzer werden anhand der E-Mail erkannt und verknuepft.
- **Benutzer-Sync** ueber den Microsoft-Graph-Endpoint mit Client-
  Credentials-Flow (App-only). Importiert/aktualisiert Name, E-Mail,
  Abteilung, Funktion, Telefon und Vorgesetzten (`manager`). Manueller
  Trigger im Admin-Backend oder per Cron: `php artisan m365:sync-users`.
  Voraussetzung: Application-Permission `User.Read.All` mit Admin
  Consent.

## Phase 5 — Wiederkehrende Workflows + Form-Builder

- **Wiederkehrende Workflows:** Workflows mit Trigger `recurring`
  koennen Wiedervorlagen haben (Bezugs-Person, Intervall in
  Tagen/Wochen/Monaten/Jahren). Beispiel: "Fuehrerschein-Pruefung
  alle 6 Monate" je Mitarbeiter.
- **Scheduler-Command:** `php artisan workflow:run-schedules` laeuft
  stuendlich via Laravel-Scheduler, startet faellige Workflows mit dem
  jeweiligen Subject als Initiator, setzt `last_run_at` und
  berechnet `next_run_at` automatisch.
- **Stand-Alone-Form-Builder** unter *Automatisierung → Formulare*:
  Formulare mit eigenem Schema, optionalem `workflow_id`-Link und
  oeffentlichem Pfad `/formular/{slug}`. Live-Vorschau, Drag-and-Drop-
  freie Reihenfolge-Bearbeitung. Eingaenge starten den verknuepften
  Workflow oder werden nur in `form_submissions` gespeichert.

## Phase 3 — Runtime, oeffentliche Formulare, Mail-Versand

- **Workflow-Engine** (`App\Services\WorkflowEngine`) startet Instanzen,
  laeuft Start/Bedingung/Notify-Knoten ab und pausiert bei Approval-
  Schritten bis zur Entscheidung. Tiefenlimit gegen Endlos-Loops.
- **Bedingungs-Auswertung** unterstuetzt `eq`, `neq`, `contains`, `gt`,
  `gte`, `lt`, `lte`, `checked`, `unchecked`, `empty`, `not_empty`. Trifft
  keine Bedingung zu, wird der Else-Ausgang genutzt.
- **Empfaenger-Auflosung** dynamisch: Vorgesetzter des Antragstellers,
  Vorgesetzter des vorigen Bearbeiters, Mitglieder einer Rolle oder
  konkrete Person. Beruecksichtigt `prefer_m365_supervisor`.
- **Karenzzeit-Eskalation:** Console-Command `php artisan workflow:check-due`
  laeuft alle 5 Minuten via Laravel-Scheduler und eskaliert offene
  Schritte mit ueberschrittener `due_at` an Rolle oder Vorgesetzten.
- **Task-Inbox** (`/tasks`) zeigt offene Aufgaben mit Frist-Hervorhebung
  (rot fuer ueberfaellig) und Sidebar-Badge mit Zaehler.
- **Genehmigungs-Aktionen:** Genehmigen, Ablehnen, optional Weiterleiten
  an dritte Person inkl. Kommentar.
- **Oeffentliche Formulare** unter `/f/{public_slug}` — kein Login noetig.
  Honeypot gegen Spam, Validierung anhand des Form-Schemas.
- **Interner Workflow-Start** unter `Workflows -> Starten` fuer
  authentifizierte Antragsteller (Mitarbeiter-Rolle).
- **Mail-Versand:**
  - `WorkflowTaskAssignedMail` bei jeder neuen Aufgabe.
  - `WorkflowNotificationMail` fuer Notify-Knoten mit Platzhaltern
    (`@{{ feld_key }}`, `@{{ initiator }}`).
  - Respektiert `email_notifications_enabled` pro Benutzer.
- **SMTP-Konfiguration im Admin-Backend** unter
  *Verwaltung → Systemeinstellungen.* Aenderungen ueberschreiben die
  `.env`-Werte zur Laufzeit; Passwoerter werden verschluesselt (`Crypt::encryptString`)
  gespeichert. Inklusive Test-Mail-Button.

### Cron-Setup auf Shared-Hosting

Ein einziger Cron-Eintrag fuehrt alle Laravel-Scheduler-Tasks aus
(inkl. der Karenzzeit-Pruefung):

```cron
* * * * * cd /pfad/zur/app && php artisan schedule:run >> /dev/null 2>&1
```

## Phase 2 — Workflow-Designer

- **Drag-and-Drop-Editor** (Drawflow) mit Knoten-Palette und Settings-Panel.
- **Knotentypen:**
  - *Start* — automatischer Einstiegspunkt fuer den Trigger.
  - *Genehmigung* — Empfaenger nach Wahl (Vorgesetzter des Antragstellers,
    Vorgesetzter des vorigen Bearbeiters, Mitglieder einer Rolle, oder
    eine konkrete Person), Karenzzeit (Stunden/Tage/Monate), optionale
    Eskalation an Vorgesetzten oder Rolle, optionale Weiterleitung.
    Ausgaenge: Genehmigt / Abgelehnt / (Weitergeleitet).
  - *Bedingung* — beliebig viele Verzweigungen mit Operatoren
    (`ist gleich`, `enthaelt`, `groesser`, `ist angekreuzt`, `ist leer` …)
    plus implizitem **Else-Ausgang**. Felder kommen aus dem Form-Schema.
  - *Benachrichtigung* — sendet E-Mail mit Platzhaltern (`@{{ feld }}`).
  - *Ende* — markiert das Ergebnis (abgeschlossen / abgelehnt / abgebrochen).
- **Form-Schema-Editor** im Designer fuer formulargetriggerte Workflows
  (Text, Textarea, Zahl, Datum, Select, Radio, Checkbox).
- **Immutable Versionierung:** Jeder Speichervorgang erzeugt eine neue
  `workflow_version`. Aeltere Versionen koennen wiederhergestellt werden
  (was als neuer Versions-Eintrag dokumentiert wird).
- **Audit-Log:** Anlegen, Speichern, Aktivieren, Archivieren, Loeschen
  jedes Workflows landet in der hashverketteten Audit-Historie.

## Phase 1 — was bereits funktioniert

- **Auth:** Login via E-Mail/Passwort (Microsoft 365 vorbereitet, kommt in Phase 4).
  Oeffentliche Registrierung ist deaktiviert; Konten werden ausschliesslich
  von Administratoren angelegt oder importiert.
- **Benutzerverwaltung:** CRUD mit Vorgesetzten-Verknuepfung,
  Abteilung, Funktion, Personalnummer, M365-Object-ID,
  Praeferenz "M365-Vorgesetzter verwenden", E-Mail-Benachrichtigungs-Schalter.
- **Rollen & Permissions:** Permissions werden ausschliesslich an Rollen
  haengen; Benutzer erhalten ihre Rechte ueber Rollen. Vier System-Rollen
  (`admin`, `workflow-designer`, `employee`, `auditor`) und 16 Permissions
  werden geseedet.
- **CSV-Import:** Benutzer per CSV anlegen oder aktualisieren, inkl.
  Mapping auf Vorgesetzte (per E-Mail) und Rollen (per Slug).
- **Audit-Log:** Jede sicherheitsrelevante Aktion (Login, Logout,
  Benutzer-/Rollenaenderung, Importe …) wird in einer SHA-256-verketteten
  Audit-Kette gespeichert. Eintraege koennen nicht ueber das ORM modifiziert
  oder geloescht werden; die Integritaet kann ueber die Admin-UI verifiziert
  werden.
- **DB-Schema fuer Workflows und Formulare ist bereits angelegt**, damit
  die folgenden Phasen darauf aufsetzen koennen, ohne destruktive Migrationen
  zu erzwingen.

## Lokale Einrichtung

```bash
# 1. Dependencies installieren
composer install
npm install

# 2. .env vorbereiten und App-Key erzeugen
cp .env.example .env
php artisan key:generate

# 3. DB-Zugang in .env eintragen (MySQL/MariaDB)
#    DB_DATABASE / DB_USERNAME / DB_PASSWORD

# 4. Migrationen und Seeds ausfuehren
php artisan migrate --seed

# 5. Assets bauen (oder `npm run dev`)
npm run build

# 6. Server starten
php artisan serve
```

Standard-Logins nach dem Seeding:

| Rolle              | E-Mail                    | Passwort   |
| ------------------ | ------------------------- | ---------- |
| Administrator      | `admin@example.com`       | `password` |
| Workflow-Designer  | `designer@example.com`    | `password` |
| Mitarbeiter        | `employee@example.com`    | `password` |

> Die Demo-Accounts werden nur in `APP_ENV=local` angelegt. Auf Produktion
> wird nur der Admin geseedet — bitte direkt nach dem ersten Login das
> Passwort aendern.

## CSV-Import-Format

Die erste Zeile enthaelt die Spaltennamen. Folgende Spalten werden gelesen:

| Spalte                          | Pflicht | Beispiel                          |
| ------------------------------- | ------- | --------------------------------- |
| `name`                          | ja      | `Max Mustermann`                  |
| `email`                         | ja      | `max@example.com`                 |
| `department`                    | nein    | `IT`                              |
| `job_title`                     | nein    | `Developer`                       |
| `phone`                         | nein    | `+49 30 12345`                    |
| `employee_id`                   | nein    | `42`                              |
| `supervisor_email`              | nein    | `chef@example.com`                |
| `role_slugs`                    | nein    | `employee,workflow-designer`      |
| `is_active`                     | nein    | `1` / `0` / `ja` / `nein`         |
| `email_notifications_enabled`   | nein    | `1` / `0`                         |

Existierende Konten werden anhand der E-Mail erkannt und aktualisiert
(soft-deleted Konten werden wiederhergestellt).

## Audit-Log

Jeder Eintrag enthaelt `prev_hash` und `hash`, wobei der Hash ueber die
serialisierten Felder gebildet wird. Das Model verhindert Updates und
Deletes ueber Eloquent. Administratoren mit der Permission `audit.verify`
koennen unter *Audit-Log → Integritaetskette pruefen* die Kette komplett
nachvollziehen und manipulierte Datensaetze identifizieren.

## Naechste Schritte

Damit ist der initial geplante Funktionsumfang erreicht. Sinnvolle
Erweiterungen:

- Drag-and-Drop fuer Formularfelder (statt manueller Reihenfolge).
- Pruefung/Test fuer M365-Credentials direkt im Backend (analog zur Test-Mail).
- Asset-Verwaltung (z. B. Fuehrerschein als eigene Entitaet mit
  Ablaufdatum, statt rein zeitintervallbasierter Schedules).
- Quartz/Calendar-aehnliche Schedules (Wochentage, Uhrzeiten).

## Lizenz

MIT
