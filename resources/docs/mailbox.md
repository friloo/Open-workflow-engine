# E-Mail-Eingang (IMAP)

Mehrere IMAP-Postfaecher koennen so konfiguriert werden, dass eingehende
Mails automatisch verarbeitet werden:

- Anhaenge werden als Dokumente abgelegt (revisionssicher, SHA-256,
  optional mit Dokumenttyp).
- Optional wird pro Mail eine Workflow-Instanz gestartet.
- Mail wird im Postfach als gelesen markiert und ggf. in einen
  Zielordner verschoben.

## Konfiguration

*Verwaltung -> E-Mail-Postfaecher*. Permission `mailboxes.manage`.

Pro Postfach:

- **Name** — interne Bezeichnung (z. B. „Eingangs-Rechnungen").
- **IMAP-Host, Port, Verschluesselung** — z. B. `imap.beispiel.de`,
  `993`, `SSL`.
- **Zertifikat pruefen** — sollte fuer Produktion an bleiben.
- **Benutzer, Passwort** — Passwort wird verschluesselt gespeichert.
- **Ordner** — meist `INBOX`.
- **Dokumenttyp fuer Anhaenge** — landet automatisch in jedem
  abgelegten Attachment.
- **Workflow starten** *(optional)* — wenn gesetzt, wird pro Mail eine
  Instanz angelegt. Felder im Formular koennen aus der Mail befuellt
  werden:
  - *Feldname fuer Betreff* — z. B. `betreff`
  - *Feldname fuer Absender* — z. B. `absender`
  - *Feldname fuer Mail-Text* — z. B. `text`
- **Verarbeitete Mails verschieben** + **Zielordner** (Default
  `Verarbeitet`). Der Ordner wird bei Bedarf angelegt.

## Cron

`mail:fetch` laeuft alle 5 Minuten (siehe `routes/console.php`).
Manuell:

```bash
php artisan mail:fetch
php artisan mail:fetch --mailbox=2
```

In der Verwaltung gibt es zudem **Test** (nur Verbindung) und
**Jetzt abrufen** (einmaliger Abruf inkl. Verarbeitung).

## Verarbeitung

Pro Mail wird ein `mailbox_messages`-Eintrag angelegt (eindeutig pro
UID + Postfach). Bereits verarbeitete Mails werden uebersprungen, auch
wenn sie erneut als ungelesen markiert werden — das schuetzt vor
Doppelt-Anlage.

Auditiert wird `mailbox.message.received` mit Absender, Betreff und
Anhang-Anzahl. Fehler landen in `mailbox.message.failed` und im Feld
`last_error` des Postfachs.

## Was wird automatisch indexiert

Direkt nach OCR laeuft die Felder-Extraktion ueber das Schema des
Dokumenttyps (Verwaltung -> Dokument-Schemas). Das heisst bei
`document_type=Rechnung` werden Rechnungsnummer, Datum, Brutto,
Kostenstelle (per Lookup-Liste), IBAN, USt-ID etc. — je nach Schema —
automatisch in `attachments.indexed_fields` geschrieben.

Diese Felder stehen im Workflow direkt zur Verfuegung:

- `{{ doc.indexed_fields.kostenstelle }}` in E-Mail-Templates etc.
- Bedingungs-Knoten: Feld = `doc.indexed_fields.kostenstelle`
- Approval-Empfaenger „Aus Liste nachschlagen" mit Schluessel-Feld
  in Punktnotation

Vollstaendiges Setup-Beispiel: **Cookbook: Rechnungseingang**.

## Ohne Workflow-Auto-Start

Wenn ein Postfach kein „Workflow starten" gesetzt hat, landen die
verarbeiteten Anhaenge im **Postkorb** (*Dokumente -> Postkorb*). Dort
kann pro Zeile manuell ein Workflow gestartet werden — die erkannten
Felder werden mitgegeben.
