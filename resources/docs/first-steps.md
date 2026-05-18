# Erste Schritte

## 1. Anmelden

Administrator-Login direkt nach `migrate --seed`:

- **E-Mail:** `admin@example.com`
- **Passwort:** `password`

> Aendere das Passwort sofort unter *Mein Profil*.

## 2. Systemeinstellungen

Unter *Verwaltung -> Systemeinstellungen* hinterlegst du:

- **SMTP** fuer Workflow-Mails (Test-Button vorhanden).
- **Microsoft 365** (optional) fuer SSO und Benutzer-Sync.
- **KI** (optional) fuer Assistenz beim Einrichten von HTTP-Knoten.
- **Branding** (App-Name, Logo, Primaerfarbe).
- **Benutzerdefinierte Felder** auf User-Ebene.

## 3. Benutzer

Lege Benutzer einzeln an oder importiere sie per **CSV**
(*Benutzer -> CSV-Import*). Spaltennamen muessen den definierten Feld-Keys
entsprechen. Vorgesetzte werden ueber `supervisor_email` verknuepft.

## 4. Rollen

Permissions werden **ausschliesslich an Rollen** vergeben. Standard-Rollen:

- `admin` — Vollzugriff
- `workflow-designer` — entwirft Workflows, Listen, Assets
- `employee` — startet Workflows, bearbeitet Aufgaben
- `auditor` — sieht Audit-Log

## 5. Erste Liste

*Stammdaten -> Listen -> Neue Liste.* Mindestens eine Spalte muss als
"Schluessel" markiert sein. Empfehlung fuer Kostenstellen:

| Schluessel | Verantwortlich | Eskalation |
|-----------|----------------|------------|
| kostenstelle | responsible_email | escalation_email |

## 6. Erster Workflow

*Workflows -> Neuer Workflow.* Trigger waehlen, im Designer
Knoten verknuepfen, speichern (= neue Version), aktivieren.

## 7. Beispiel: Rechnungseingang automatisieren

Ein typisches End-to-End-Setup steht im **Cookbook**: Lookup-Liste fuer
Kostenstellen -> Felder-Schema fuer „Rechnung" -> IMAP-Postfach ->
Workflow mit Routing zum Kostenstellen-Verantwortlichen.
Siehe **Rechnungseingang einrichten** im Hilfemenue oder
`resources/docs/cookbook-rechnungseingang.md`.
