<div align="center">

# Open Workflow Engine

**Self-hosted Workflows · Light-DMS · Postkorb · Genehmigungen — fuer KMU auf Shared Hosting.**

[![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white)]()
[![Laravel](https://img.shields.io/badge/Laravel-11-FF2D20?logo=laravel&logoColor=white)]()
[![Tests](https://img.shields.io/badge/tests-190%20passing-10B981)]()
[![License](https://img.shields.io/badge/license-MIT-475569)]()

PDF-Rechnungen per Mail rein → Kostenstelle erkannt → richtige Person genehmigt — *ohne* Cloud, *ohne* SSH, *ohne* Composer auf dem Server.

</div>

---

## Was OWE kann

<table>
<tr>
<td width="50%" valign="top">

### 🔄 Workflows
- Drag-and-Drop-Designer (Drawflow), Versionierung
- Knotentypen: Start, Bedingung, Genehmigung, HTTP, PDF, Mail, Ende
- Empfaenger: User · Rolle · Vorgesetzter · Lookup-Liste · **Parallel-Quorum (n-aus-m / alle)**
- Trigger: Formular · manuell · wiederkehrend · IMAP · Webhook
- **Vertretungsregelung** (Urlaub) automatisch
- **Trockenlauf** mit Testdaten vor Aktivierung
- **3 fertige Vorlagen** zum Importieren (Rechnung, Urlaub, Bestellung)

</td>
<td width="50%" valign="top">

### 📄 Light-DMS
- Bulk-Upload, Inline-Vorschau, OCR-Volltext, Versionierung
- **Dokumenttyp-Schemas**: Rechnungsnummer, Datum, Betrag, Kostenstelle, IBAN, USt-ID automatisch extrahieren
- Erkennung: Heuristik · Regex · **Lookup-Liste (anlernen)** · KI optional
- **Postkorb** fuer eingehende Mails ohne Auto-Workflow
- Filter auf erkannte Felder + **CSV-Export**
- Sharing-Links mit Passwort, Ablauf-Cap, Auto-Review
- Aufbewahrungsregeln pro Typ (DSGVO)

</td>
</tr>
<tr>
<td valign="top">

### ✉️ Integrationen
- **IMAP-Eingang** (multi Postfaecher, Anhang → Dokument → Workflow)
- **Outgoing Webhooks** mit HMAC-Signatur
- **Incoming Webhooks** unter `POST /api/incoming/<token>`
- **HTTP-Knoten** mit Body-Templates, Response-Mapping, KI-Assistent
- **Microsoft 365** SSO + Benutzer-Sync (App-only Graph)
- **JSON-API** unter `/api/v1` mit persoenlichen API-Tokens
- **PDF-Render-Knoten** (HTML-Template → revisionssicheres Attachment)
- **Genehmigung per Mail** ueber signierten Link (kein Login)

</td>
<td valign="top">

### 🛡️ Betrieb
- **Web-Installer** (Schritt-fuer-Schritt, ohne SSH/Composer)
- **Backup & Restore** taeglich, mit „Backup beim Install hochladen"
- **Update aus der UI** (Channel-Factory, atomar, Maintenance-Flag)
- **2FA / TOTP** pro Benutzer optional
- **Audit-Hashkette** (SHA-256-verkettet, manipulationssicher)
- **System-Health-Seite** + JSON-Endpoint fuer Monitoring
- **Dashboard** mit persoenlichen Tasks, Postkorb, Ampel
- Rollen-/Permission-System, keine Einzel-Berechtigungen

</td>
</tr>
</table>

---

## Quick-Start

```bash
# 1. Release-ZIP entpacken und per FTP hochladen (enthaelt vendor/)
# 2. Browser oeffnen → automatischer Redirect nach /install
```

Der **Web-Installer** macht den Rest:

```
  ┌──────────────────┐
  │ 1. System-Check  │  PHP-Version · Extensions · Schreibrechte
  ├──────────────────┤
  │ 2. Datenbank     │  SQLite (Default) oder MySQL/MariaDB
  ├──────────────────┤
  │ 3. Admin-Konto   │  Name · E-Mail · Passwort
  ├──────────────────┤
  │ 4. Fertig        │  Login + Cookbook-Hinweise
  └──────────────────┘
```

**Migration auf neuen Host?** Auf der Welcome-Seite Karte *Aus Backup wiederherstellen* — ZIP hochladen, DB-Credentials angeben, fertig.

> **Kein Composer auf dem Server noetig.** Release-ZIPs enthalten `vendor/`
> vorgebaut. Update-System ueberspringt `composer install` falls `proc_open`
> oder die CLI fehlen.

---

## Beispiel: Rechnungseingang

So sieht ein typischer End-to-End-Flow aus — Setup-Zeit ca. 30 Minuten,
komplett ohne KI:

```
                Mail mit PDF-Rechnung
                        │
                        ▼
              ┌──────────────────┐
              │ IMAP-Postfach    │
              │ Doku-Typ:Rechnung│
              └────────┬─────────┘
                       │
                       ▼
              ┌──────────────────┐
              │ OCR + Schema     │   ← Heuristik fuer Nummer/Datum/Brutto
              │ "Rechnung"       │   ← Lookup-Liste 'Kostenstellen'
              └────────┬─────────┘     fuer kostenstelle-Feld
                       │
                       ▼
              ┌──────────────────┐
              │ Workflow startet │
              │ (Anhang gehaengt)│
              └────────┬─────────┘
                       │
            ┌──────────┴───────────┐
            ▼                      ▼
   doc.indexed_fields.       sonst → Buchhaltung
   kostenstelle erkannt?
            │
            ▼
   Lookup-Empfaenger
   = Verantwortlich(er)
   aus Kostenstellen-Liste
```

Vollstaendige Schritt-fuer-Schritt-Anleitung: **Cookbook: Rechnungseingang einrichten** in der Online-Hilfe.

---

## Architektur in einem Satz

Laravel 11 + Eloquent + Tailwind + Alpine.js + Drawflow — SQLite by default, MySQL optional, alles als File-Storage in `storage/app/`, kein Redis/Queue/Worker fuer den Betrieb noetig (Scheduler reicht via 1 Cron-Eintrag).

```
┌─────────────────────────────────────────────────────────────┐
│  Browser ────────┐                                           │
│                  ▼                                           │
│  Laravel 11  ◄──────── Web-Installer (vor Setup)             │
│       │                                                      │
│       ├── WorkflowEngine        (Drawflow-JSON walker)       │
│       ├── AttachmentStorage     (SHA-256, Versionen, OCR)    │
│       ├── FieldExtractor        (Heuristik/Regex/Lookup/KI)  │
│       ├── BackupService         (ZIP DB+Anhaenge)            │
│       └── UpdateManager         (Channel-Factory, atomar)    │
│                                                              │
│  storage/app/                                                │
│   ├── attachments/<Y>/<M>/<ulid>.<ext>   (Datei-Hashes!)     │
│   ├── backups/owe-<datum>.zip                                │
│   ├── .installed   (Marker)                                  │
│   └── .update-progress                                       │
└─────────────────────────────────────────────────────────────┘
```

---

## Anforderungen

| | Mindestens |
|---|---|
| **PHP** | 8.2 |
| **Extensions (Pflicht)** | pdo, mbstring, openssl, json, zip, fileinfo, curl, tokenizer, xml, ctype, dom |
| **Datenbank** | SQLite oder MySQL/MariaDB |
| **Webserver** | Apache / nginx / Caddy / shared hosting |
| **Optional** | `pdftotext` + `pdftoppm` + `tesseract` fuer OCR · `gd` fuer Bildverarbeitung |

---

## Cron — genau ein Eintrag

```cron
* * * * * cd /pfad/zur/app && php artisan schedule:run >> /dev/null 2>&1
```

Damit laufen:

| Task | Frequenz | Was |
|---|---|---|
| `workflow:check-due` | alle 5 Minuten | Eskalationen + Frist-Reminder |
| `workflow:run-schedules` | stuendlich | wiederkehrende Workflows |
| `mail:fetch` | alle 5 Minuten | IMAP-Postfaecher |
| `tasks:remind` | taeglich 09:00 | Erinnerung fuer lange offene Tasks |
| `asset:check-due` | taeglich 06:00 | Asset-Faelligkeiten |
| `ocr:run-pending` | taeglich 02:30 | OCR-Nachzieher |
| `documents:retention-check` | taeglich 03:15 | DSGVO-Aufbewahrung |
| `backup:run` | taeglich 01:30 | Tagessicherung |
| `shares:review` | taeglich 07:00 | Sharing-Link-Pruefung |
| `audit:cleanup` | monatlich | IP/UA aelter 2 Jahre anonymisieren |

---

## Dokumentation

In der App unter `/hilfe` (Topbar-Fragezeichen-Icon). Themen:

<details>
<summary><strong>Einstieg</strong></summary>

- Erstinstallation (Web-Installer)
- Erste Schritte
- Dashboard / Startseite
- **Cookbook: Rechnungseingang einrichten**

</details>

<details>
<summary><strong>Workflows</strong></summary>

- Workflows entwerfen
- Workflow-Vorlagen (Import/Export)
- Workflow-Simulation (Trockenlauf)
- Workflow-Statistik
- Vertretungsregelung
- Genehmigung per Mail
- KI im Designer
- Formulare

</details>

<details>
<summary><strong>Daten</strong></summary>

- Listen (Kostenstellen etc.)
- Assets (Fuehrerschein etc.)
- Dokumente (Versionen, OCR, Bulk)
- **Felder-Schemas pro Dokumenttyp**
- **Postkorb + Lookup-Routing**
- Aufbewahrungsregeln
- Sharing-Links

</details>

<details>
<summary><strong>Integration</strong></summary>

- HTTP-Knoten
- PDF-Knoten
- E-Mail-Eingang (IMAP)
- Webhooks (outgoing)
- **Eingehende Webhooks**
- Microsoft 365 SSO
- API-Tokens
- Secrets-Vault

</details>

<details>
<summary><strong>Betrieb</strong></summary>

- Administration
- System-Health
- System-Update
- **Backup & Restore**
- Zwei-Faktor-Anmeldung
- Revisionssicherheit
- Platzhalter-Referenz

</details>

Source: `resources/docs/*.md`.

---

## Lifecycle ohne SSH

```
   Frischer Upload              Update                    Migration
   ─────────────────            ─────────                  ─────────
   FTP-Upload                   Browser → /admin/update    Backup auf altem Host
   Release-ZIP                                             Browser auf neuem Host
   (mit vendor/)                Channel waehlen            → /install Welcome
        │                       (stable / dev)             → "Aus Backup
        ▼                       Button „Update jetzt"        wiederherstellen"
   Browser →                    installieren"              → ZIP hochladen
   /install                     Maintenance an              + DB-Credentials
   Wizard                       atomarer Tausch            → fertig (Login mit
        │                       Maintenance aus              alten Daten)
        ▼
   Login
```

---

## Tests

```bash
php artisan test
```

190 Feature-Tests · 595 Assertions · ca. 8 Sekunden. SQLite `:memory:` in
`phpunit.xml`, `RefreshDatabase` pro Test.

---

## Lizenz

MIT — siehe `LICENSE`.

---

<div align="center">
<sub>Gebaut fuer KMU und Vereine, die ihre Workflows behalten wollen — statt SaaS-Ketten anzuschmieden.</sub>
</div>
