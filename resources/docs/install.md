# Erstinstallation (Web-Installer)

Wenn OWE frisch hochgeladen wurde und `storage/app/.installed` noch
nicht existiert, leitet jeder Aufruf automatisch nach `/install`.
Kein SSH-Zugriff noetig.

## Voraussetzungen

- PHP >= 8.2
- Pflicht-Extensions: pdo, mbstring, openssl, json, zip, fileinfo,
  curl, tokenizer, xml, ctype, dom
- Empfohlen: pdo_sqlite **oder** pdo_mysql (je nach Datenbank-Wahl),
  gd (Bildverarbeitung)
- Schreibrechte: `storage/`, `bootstrap/cache/`

Der Installer pruefts auf Schritt 1 und zeigt rot, was fehlt.

## Ablauf

### Schritt 1 — System

Liste aller Voraussetzungen mit Ampel-Status. Solange ein **FAIL**
dabei ist, bleibt der Weiter-Button deaktiviert.

### Schritt 2 — Datenbank

- **App-Name** und **App-URL** — landen in `.env`.
- **Treiber**: SQLite (Default, ideal fuer Shared Hosting; nichts zu
  konfigurieren) oder MySQL/MariaDB (Host, Port, Datenbank, Benutzer,
  Passwort).

Beim Speichern:
- `.env` wird aus `.env.example` angelegt (falls nicht vorhanden),
  `APP_KEY` automatisch generiert (256 bit base64).
- MySQL-Verbindung wird **getestet** bevor sie persistiert wird —
  bei Fehler bleibt die Seite mit Fehlertext stehen.
- `php artisan migrate --force` laeuft.
- Roles-and-Permissions-Seeder wird ausgefuehrt.

### Schritt 3 — Admin-Konto

Name, E-Mail, Passwort (min. 8 Zeichen, doppelt). Bekommt die
`admin`-Rolle.

### Schritt 4 — Fertig

- Marker `storage/app/.installed` wird gesetzt (mit Timestamp +
  optional Versions-SHA).
- Hinweise auf die naechsten Schritte (SMTP, Cron, KI, Vorlagen).

Ab jetzt schickt der Middleware alle `/install*`-Aufrufe per Redirect
zur Startseite — der Installer ist gesperrt.

## Neuinstallation / Reset

Marker loeschen (z. B. via FTP):

```bash
rm storage/app/.installed
```

Beim naechsten Aufruf laeuft der Installer wieder. **Achtung**: das
ueberschreibt die bestehende Konfiguration nicht automatisch — die DB
bleibt wie sie ist, und der Admin-Schritt nutzt `updateOrCreate` per
E-Mail, ueberschreibt also nur den Account mit gleicher Adresse.

## Update-Schutz

Der Marker liegt in `storage/app/` — das ist in
**`UpdateManager::PROTECTED_PATHS`**. Ein System-Update entfernt
ihn also nicht, OWE bleibt nach dem Update direkt installiert.
