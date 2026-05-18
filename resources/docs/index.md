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
- Webhooks — outgoing HTTP-Events
- Microsoft 365 — SSO und Benutzer-Sync
- Administration — Benutzer, Rollen, Cron, Custom Fields, Branding, DSGVO
- Revisionssicherheit — drei Ebenen: Audit-Hashkette, immutable Versionen, Datei-Hash
- Platzhalter-Referenz — alle verfuegbaren `{{ ... }}`-Variablen
