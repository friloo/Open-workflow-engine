# Open Workflow Engine

Selbst-gehostete Plattform fuer Workflows, Formulare und revisionssichere
Dokumenten-Ablage. Diese Anleitung erklaert, was du mit OWE machen
kannst und wie alles zusammenpasst.

> [!TIP]
> Wenn du gerade frisch installiert hast, geh direkt zur Seite
> **[Erste Schritte als Admin](app:help.show?topic=first-steps)** — die
> fuehrt dich Hand-am-Steuer durch SMTP, Benutzer, Rollen, ersten
> Workflow.

## Was kann OWE?

OWE besteht aus mehreren Bausteinen, die einzeln nuetzlich sind und
zusammen mehr ergeben:

### Genehmigungen automatisieren

- **[Workflows](app:help.show?topic=workflows)** sind Diagramme aus Knoten
  (Genehmigung, Bedingung, HTTP, PDF, Wait, ...) die du im
  Drag-and-Drop-Designer baust.
- **[Formulare](app:help.show?topic=forms)** sammeln Daten — intern mit
  Login oder oeffentlich ohne. Felder koennen bedingt sein.
- **[Genehmigung per Mail](app:help.show?topic=mail-approval)** —
  signierter Link, der Empfaenger klickt „Genehmigen" oder „Ablehnen"
  ohne sich anzumelden.
- **[Vertretungsregelung](app:help.show?topic=delegation)** — pro Benutzer
  konfigurierbar, fuer Urlaub.
- **[Sub-Workflows & Loops](app:help.show?topic=sub-workflows)** — komplexe
  Prozesse aus wiederverwendbaren Bausteinen. Plus Switch-Knoten und
  Aggregator-Knoten fuer Multi-Branch-Routing und Listen-Faltung.
- **[Benachrichtigungen anpassen](app:help.show?topic=notifications)** —
  pro Event und Kanal selber entscheiden, was du wann bekommst.

### Dokumente verwalten

- **[Dokumente](app:help.show?topic=documents)** — Versionierung, OCR-
  Volltextsuche, Bulk-Upload, Klassifizierung in Archive.
- **[Felder-Schemas](app:help.show?topic=document-schemas)** — pro
  Dokumenttyp definierst du Indexfelder (Rechnungsnummer, Betrag, ...)
  und wie sie aus dem OCR-Text extrahiert werden: Heuristik, Lookup,
  Regex oder KI.
- **[Postkorb & Lookup-Routing](app:help.show?topic=inbox-routing)** —
  eingehende Dokumente werden automatisch klassifiziert und an den
  richtigen Bearbeiter geroutet.
- **[ZUGFeRD / XRechnung](app:help.show?topic=zugferd)** — strukturierte
  E-Rechnungen werden geparsed und ihre Felder direkt nutzbar.
- **[Aufbewahrungsregeln](app:help.show?topic=retention)** — pro
  Dokumenttyp Min/Max-Jahre und automatische Aktion nach Ablauf.
- **[Sharing-Links](app:help.show?topic=sharing)** — externe Freigabe mit
  Ablauf, Passwort, Audit-Trail.

### Datenquellen

- **[E-Mail-Eingang (IMAP)](app:help.show?topic=mailbox)** — Postfach
  abfragen, Anhaenge in Postkorb, optional Workflow starten.
- **[Folder-Inboxen](app:help.show?topic=folder-inbox)** — Scan-Ordner
  vom Multifunktionsgeraet, OWE indexiert automatisch.
- **[Eingehende Webhooks](app:help.show?topic=incoming-webhooks)** —
  externe Systeme triggern Workflows per HTTP-POST mit HMAC.

### Integration

- **[HTTP-Knoten](app:help.show?topic=http-node)** — externe APIs aus
  einem Workflow heraus aufrufen. Mit Body-Templates, Response-Mapping
  und Secrets.
- **[PDF-Knoten](app:help.show?topic=pdf-node)** — am Workflow-Ende einen
  PDF-Beleg aus HTML rendern und revisionssicher anhaengen.
- **[Webhooks (outgoing)](app:help.show?topic=webhooks)** — Events aus
  dem Workflow an externe URLs schicken, mit HMAC-Signatur.
- **[Secrets-Vault](app:help.show?topic=secrets)** — API-Tokens
  verschluesselt, in HTTP-Knoten als `{{ secret.NAME }}` verwendbar.
- **[Microsoft 365](app:help.show?topic=m365)** — SSO ueber Entra ID,
  Benutzer-Sync.
- **[API-Tokens](app:help.show?topic=api-tokens)** — fuer die JSON-API.

### Betrieb & Sicherheit

- **[2FA](app:help.show?topic=2fa)** — TOTP per Authenticator-App.
- **[Revisionssicherheit](app:help.show?topic=revisionssicher)** —
  Audit-Hashkette, immutable Datei-Versionen, SHA-256 pro Datei.
- **[System-Health](app:help.show?topic=health)** — Statusseite +
  JSON-Endpoint fuer Monitoring.
- **[System-Update](app:help.show?topic=update)** — Update direkt aus
  der UI, signiert, atomar.
- **[Backup & Restore](app:help.show?topic=backup)** — taegliche
  ZIP-Sicherung, Restore via CLI.
- **[GoBD-Konformitaet](app:help.show?topic=gobd)** — Verfahrens-
  dokumentation, was deckt OWE technisch ab, was muss der Betreiber
  selbst tun, WP-Pruefung.
- **[DSGVO-Anfragen](app:help.show?topic=dsgvo)** — Auskunft + Anonymisierung
  ueber Admin-UI mit Audit-Trail.

### Skalierung & Performance

- **[Object-Storage](app:help.show?topic=object-storage)** — S3 / MinIO /
  Wasabi als Anhang-Storage. Pflicht bei Multi-Server-Setups.
- **[Queue-Worker](app:help.show?topic=queue-worker)** — OCR + schwere
  Verarbeitung im Hintergrund statt synchron im Upload-Request.
- **[Volltext-Suche skalieren](app:help.show?topic=search-meilisearch)** —
  MeiliSearch fuer fuzzy + sub-10ms-Suche bei 100k+ Dokumenten.
- **[Office-Vorschau](app:help.show?topic=office-preview)** — DOCX / XLSX /
  PPTX im Browser anzeigen via LibreOffice.

## Wo fange ich an?

Je nachdem wo du stehst:

> [!NOTE]
> **Du installierst gerade neu** → Geh zu
> [Erstinstallation](app:help.show?topic=install). Wenn der Installer
> durchgelaufen ist, kommst du auf das Login.

> [!NOTE]
> **Frisch eingeloggt, leere Instanz** →
> [Erste Schritte als Admin](app:help.show?topic=first-steps). 10 Punkte,
> ein gutes Drittel davon optional.

> [!TIP]
> **Du willst einen konkreten Use-Case sehen** →
> [Cookbook: Rechnungseingang](app:help.show?topic=cookbook-rechnungseingang).
> Zeigt End-to-End wie eine Rechnung per Mail reinkommt, klassifiziert
> wird und an den Kostenstellen-Verantwortlichen geht — in ca. 30
> Minuten Setup.

> [!TIP]
> **Du brauchst eine konkrete Platzhalter-Variable** im Designer →
> [Platzhalter-Referenz](app:help.show?topic=placeholders).

## Tastatur-Shortcuts

Drueck **Strg+K** (Linux/Windows) oder **Cmd+K** (Mac) irgendwo in der
App und du bekommst eine **Schnellsuche** ueber Dokumente, Workflows,
Aufgaben und Benutzer. Druecke **?** fuer die volle Tastatur-Uebersicht.

## Hilfe & Konzepte verlinkt

Links die hier in den Anleitungen auf interne Seiten zeigen
(z. B. „Geh zu Einstellungen → Mail-Versand") sind echte App-Links —
ein Klick und du bist da, ohne in der Sidebar zu suchen.
