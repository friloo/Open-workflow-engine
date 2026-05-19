# Folder-Inboxen (lokale Datenquelle)

Neben IMAP gibt es eine zweite Datenquelle fuer eingehende Dokumente:
**lokale Ordner**. Praktisch fuer Multifunktions-Drucker mit Scan-zu-
Ordner, FTP-Drop-Pfade oder gemounten Netzwerk-Shares.

## Wann nutzen

- **Scanner-Output-Ordner**: viele Buero-Drucker speichern PDFs an einen
  FTP-/SMB-Pfad oder einen lokalen Ordner. OWE zieht die da automatisch ab.
- **FTP-Drop-Folder**: Lieferant laed PDFs per FTP hoch, OWE archiviert sie.
- **Lokale Ablage**: jemand schiebt PDFs in einen Ordner, OWE klassifiziert
  + startet Workflow.

## Konfiguration

*Verwaltung -> Folder-Inboxen*. Permission `folder_inboxes.manage`.

Pro Folder:

- **Name** — intern.
- **Pfad** — absoluter Pfad ODER relativ zu `storage/app/` (Checkbox).
  Letzteres ist auf Shared Hosting sicherer.
- **Dokumenttyp** — wird allen importierten Dateien zugewiesen (z. B.
  „Rechnung").
- **Workflow starten** *(optional)* — pro Datei wird eine Workflow-
  Instanz angelegt, der Anhang an die Instanz gehaengt.
- **Nach Import**: **Loeschen** (Default) oder **In Unterordner
  verschieben** (`verarbeitet/` default).
- **Erlaubte Endungen** — kommagetrennt (`pdf, png, jpg, jpeg`). Leer =
  PDF + gaengige Bildformate.

## Cron

`folder:scan` laeuft alle 5 Minuten ueber den Laravel-Scheduler.

Manueller Lauf:

```bash
php artisan folder:scan
php artisan folder:scan --inbox=2
```

In der UI gibt's pro Inbox einen *Jetzt scannen*-Button.

## Was beim Import passiert

1. Ordner wird gescannt, neue Dateien mit erlaubter Endung gefunden.
2. Pro Datei:
   - Workflow-Instanz starten (wenn konfiguriert)
   - Datei via `AttachmentStorage::storeBytes` archivieren (SHA-256,
     OCR, Felder-Schema, Audit)
   - `doc.*`-Kontext in die Workflow-Instanz schreiben
   - Source-Datei je nach Setting loeschen oder verschieben
3. Stats werden in `last_status` + `last_error` der Inbox gespeichert.

## Audit

- `folder_inbox.created` / `.updated` / `.deleted`
- `folder_inbox.imported` — pro importierte Datei, mit Workflow-Instanz-ID
