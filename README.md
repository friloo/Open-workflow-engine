# Open Workflow Engine

Self-hosted workflow- und formularbasiertes Automatisierungstool fuer
Shared-Hosting (PHP 8.2+ / MySQL oder MariaDB).

> **Status:** Phase 2 — Workflow-Designer mit bedingten Verzweigungen,
> inline Formular-Schema-Editor und immutabler Versionierung. Phase 1
> (Auth, Benutzer-/Rollenverwaltung, CSV-Import, Audit-Log) ist enthalten.

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

- Phase 3: Workflow-Runtime — Instanzen starten, Karenzzeit-Eskalation
  (Scheduler/Cron), Mail-Versand, oeffentliche Formulare ohne Login.
- Phase 4: Microsoft 365 SSO und Benutzer-Sync inkl. Vorgesetzten-Feld.

## Lizenz

MIT
