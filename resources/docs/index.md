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
- **2FA**, **API-Tokens**, **In-App-Benachrichtigungen** (Glocke in der
  Topbar) — Produktiv-Basis.
- **KI-Workflow-Designer**: gib der KI eine Beschreibung, sie liefert
  einen kompletten Workflow-Entwurf.
- **Webhooks** benachrichtigen externe Systeme bei Workflow-Ereignissen.
- **Secrets-Vault** verwahrt API-Tokens verschluesselt.
- **Audit-Log** ist hashverkettet und revisionssicher.

## Erste Schritte

Siehe Abschnitt **Erste Schritte**.

## Themen

- Erste Schritte — Login, Benutzer, erste Liste, erster Workflow
- Workflows entwerfen — Knotentypen, Bedingungen, KI-Entwurf
- KI im Designer — OpenAI/DeepSeek/Ollama als Assistenz
- Formulare — bedingte Felder, Datei-Uploads, oeffentliche Forms
- Listen — Kostenstellen-Mappings fuer Empfaenger
- Assets — Fuehrerschein/Unterweisung mit Wiedervorlage
- Dokumente — Versionierung, OCR-Suche, Bulk-Upload, Vorschau
- HTTP-Knoten — externe API-Calls mit Secrets, Body-Templates, Response-Mapping
- PDF-Knoten — HTML-Template am Workflow-Ende, revisionssicher abgelegt
- E-Mail-Eingang — IMAP-Postfaecher, Anhang->Dokument, optional Workflow-Trigger
- Aufbewahrungsregeln — Min-/Max-Jahre pro Dokumenttyp, Auto-Aktion
- Webhooks — outgoing HTTP-Events
- Zwei-Faktor-Anmeldung — TOTP per Authenticator-App
- API-Tokens — persoenliche Tokens fuer die JSON-API
- Microsoft 365 — SSO und Benutzer-Sync
- Administration — Benutzer, Rollen, Cron, Custom Fields, Branding, DSGVO
- Revisionssicherheit — drei Ebenen: Audit-Hashkette, immutable Versionen, Datei-Hash
- Platzhalter-Referenz — alle verfuegbaren `{{ ... }}`-Variablen
