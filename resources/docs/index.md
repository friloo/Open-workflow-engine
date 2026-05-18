# Open Workflow Engine — Anleitung

Selbst-gehostete Plattform fuer Workflows, Formulare und revisionssichere
Dokumenten-Ablage.

## Was kannst du damit machen?

- **Workflows** automatisieren Genehmigungen und wiederkehrende Pruefungen
  (z. B. Bestellantrag, Fuehrerschein-Sichtung).
- **Formulare** sammeln Daten — intern oder oeffentlich ohne Login,
  mit bedingten Feldern.
- **Listen** mappen Schluessel (z. B. Kostenstellen) auf Verantwortliche
  und Eskalations-Personen.
- **Assets** sind nutzerbezogene Dokumente mit Ablaufdatum (Fuehrerschein,
  Unterweisung, Zertifikat).
- **Dokumente** koennen massenhaft hochgeladen, klassifiziert, versioniert
  und per OCR-Volltext durchsucht werden.
- **HTTP-Knoten** integriert externe Systeme (Ticketsysteme, ERP, HR) —
  inkl. **KI-Unterstuetzung** beim Konfigurieren.
- **PDF-Knoten** erzeugt am Workflow-Ende einen PDF-Beleg aus einem
  HTML-Template — wird als revisionssicheres Attachment angehaengt.
- **E-Mail-Eingang** (IMAP) zieht eingehende Mails automatisch in das
  System: Anhaenge werden klassifiziert und optional starten sie einen
  Workflow.
- **Aufbewahrungsregeln** pro Dokumenttyp — taeglich angewendet.
- **Felder-Schemas pro Dokumenttyp**: pro Dokumenttyp definierst du
  Felder (Rechnungsnummer, Datum, Kostenstelle, IBAN, ...) und wie sie
  erkannt werden — Heuristik, Lookup-Liste, Regex oder optional KI.
- **Postkorb** + **Lookup-Routing**: eingehende Dokumente werden
  klassifiziert, Kostenstellen aus deiner Liste erkannt, Workflows
  routen automatisch zum Verantwortlichen.
- **Vertretungsregelung** pro Benutzer (Urlaubs-tauglich).
- **Workflow-Statistik**: Durchlaufzeiten, Engpaesse, Throughput.
- **Genehmigung per Mail** ueber signierten Link — kein Login noetig.
- **System-Health**-Seite + JSON-Endpoint fuer Monitoring.
- **2FA**, **API-Tokens**, **In-App-Benachrichtigungen** (Glocke in der
  Topbar) — Produktiv-Basis.
- **System-Update** direkt aus der UI (Channel-Factory, signierte
  Maintenance, atomar).
- **KI-Workflow-Designer**: gib der KI eine Beschreibung, sie liefert
  einen kompletten Workflow-Entwurf.
- **Webhooks** benachrichtigen externe Systeme bei Workflow-Ereignissen.
- **Secrets-Vault** verwahrt API-Tokens verschluesselt.
- **Audit-Log** ist hashverkettet und revisionssicher.

## Erste Schritte

Siehe Abschnitt **Erste Schritte**.

## Themen

### Einstieg

- Erste Schritte — Login, Benutzer, erste Liste, erster Workflow
- **Cookbook: Rechnungseingang einrichten** — Setup von Liste, Schema,
  IMAP und Workflow in 30 Minuten (Schritt-fuer-Schritt)

### Workflows

- Workflows entwerfen — Knotentypen, Bedingungen, doc.\*-Routing, KI-Entwurf
- Workflow-Statistik — Durchlaufzeiten, Engpaesse, Throughput
- Vertretungsregelung — Urlaubs-Vertretung pro Benutzer
- Genehmigung per Mail — signierter Link, kein Login noetig
- KI im Designer — OpenAI/DeepSeek/Ollama als Assistenz
- Formulare — bedingte Felder, Datei-Uploads, oeffentliche Forms

### Daten

- Listen — Kostenstellen-Mappings, Lookup-Quelle fuer Schemas und Routing
- Assets — Fuehrerschein/Unterweisung mit Wiedervorlage
- Dokumente — Versionierung, OCR-Suche, Bulk-Upload, Postkorb
- **Felder-Schemas pro Dokumenttyp** — Heuristik / Lookup / Regex / KI
- **Postkorb + Lookup-Routing** — `doc.*` im Workflow, Routing zum
  Verantwortlichen aus der Liste
- Aufbewahrungsregeln — Min-/Max-Jahre pro Dokumenttyp, Auto-Aktion
- Sharing-Links — externe Freigaben mit Cap und Auto-Review

### Integration

- HTTP-Knoten — externe API-Calls mit Secrets, Body-Templates, Response-Mapping
- PDF-Knoten — HTML-Template am Workflow-Ende, revisionssicher abgelegt
- E-Mail-Eingang — IMAP-Postfaecher, Anhang -> Dokument, optional Workflow-Trigger
- Webhooks — outgoing HTTP-Events
- Microsoft 365 — SSO und Benutzer-Sync
- API-Tokens — persoenliche Tokens fuer die JSON-API
- Secrets-Vault — API-Tokens verschluesselt

### Betrieb

- Administration — Benutzer, Rollen, Cron, Custom Fields, Branding, DSGVO
- System-Health — Statusseite + JSON-Endpoint fuer Monitoring
- System-Update — Update aus der UI, Channel-Factory, atomar
- Zwei-Faktor-Anmeldung — TOTP per Authenticator-App
- Revisionssicherheit — Audit-Hashkette, immutable Versionen, Datei-Hash
- Platzhalter-Referenz — alle verfuegbaren `{{ ... }}`-Variablen
