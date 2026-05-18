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
