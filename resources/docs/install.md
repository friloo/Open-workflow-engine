# Erstinstallation (Web-Installer)

Wenn OWE frisch hochgeladen wurde und `storage/app/.installed` noch
nicht existiert, leitet jeder Aufruf automatisch nach `/install`.
Kein SSH-Zugriff noetig.

## Voraussetzungen

- PHP **>= 8.2** (auch 8.3 und neuer unterstuetzt). 8.4 ist nicht
  Pflicht — composer.json fixiert die Plattform-Target auf 8.2, damit
  keine Symfony-v8-Pakete reingezogen werden, die 8.4 voraussetzen.
- Pflicht-Extensions: pdo, mbstring, openssl, json, zip, fileinfo,
  curl, tokenizer, xml, ctype, dom
- Empfohlen: pdo_sqlite **oder** pdo_mysql (je nach Datenbank-Wahl),
  gd (Bildverarbeitung)
- Schreibrechte: `storage/`, `bootstrap/cache/`
- `vendor/`-Ordner vorhanden — kommt im **Release-ZIP vorgebaut**, also
  *kein Composer* auf dem Server noetig.

Der Installer pruefts auf Schritt 1 und zeigt rot, was fehlt.

## Empfohlener Ablauf (Webroot zuerst, dann Bootstrap)

1. **Im Hosting-Backend** (Plesk, cPanel, All-Inkl, ...) lege ein
   Verzeichnis `public/` unterhalb deines Domain-Root an und stelle
   den **Document-Root** der Domain auf genau diesen `public/`-Ordner.
   Bei vielen Hostern unter „Domain bearbeiten → Webroot".

2. **Bootstrap-Installer hochladen**: Lade `owe-installer.php` per
   FTP **in genau diesen `public/`-Ordner**. Sonst nichts.

3. **Im Browser aufrufen**: `https://deine-domain.tld/owe-installer.php`.
   Der Bootstrap erkennt automatisch, dass er aus einem `public/`-Ordner
   laeuft, entpackt Laravel ein Level **darueber** (im Domain-Root) und
   legt `public/index.php`, `app/`, `vendor/`, `.env` usw. korrekt an.
   `vendor/` und `.env` liegen damit ausserhalb des oeffentlichen
   Verzeichnisses — sauberste Variante, kein nachtraegliches Umstellen
   noetig.

4. Nach dem Entpacken wirst du auf `/install` weitergeleitet, wo der
   App-Wizard DB und Admin-Konto einrichtet.

## Fallback: Hoster ohne Webroot-Konfiguration

Wenn dein Hoster keine Doc-Root-Umstellung erlaubt, geht's auch ohne:

1. Lade `owe-installer.php` direkt ins **FTP-Root** (= Domain-Root).
2. Rufe `https://deine-domain.tld/owe-installer.php` auf.
3. Der Bootstrap merkt, dass er NICHT in einem `public/`-Ordner liegt,
   entpackt Laravel ins aktuelle Verzeichnis und legt zusaetzlich eine
   Fallback-`.htaccess` ins Root, die alle Requests intern nach
   `public/` umleitet.

> ⚠️ **Sicherheit beim Fallback**: `vendor/`, `.env` etc. liegen im
> oeffentlich erreichbaren Pfad. Die mitgelieferte `.htaccess`
> blockiert den direkten Zugriff per `FilesMatch` und `RedirectMatch`.
> Wenn `mod_rewrite` ausgeschaltet ist, GREIFT die Blockade nicht
> mehr — dann unbedingt Document-Root umstellen.

## Brauche ich Composer auf dem Server?

**Nein** — solange du das offizielle Release-ZIP nutzt. Es enthaelt
`vendor/` komplett. Du brauchst also weder SSH noch eine Composer-
Installation auf dem Webhoster.

Nur falls du das Repo direkt klonst (z. B. fuer Eigenentwicklung),
brauchst du lokal einmal `composer install --no-dev` und uebertraegst
dann `vendor/` mit per FTP. Auf dem Server selbst nie.

Das **Update-System** ist genauso entspannt: der ZIP-Pull enthaelt
ebenfalls `vendor/`, und falls auf dem Host `proc_open` oder die
`composer`-CLI fehlen sollte, ueberspringt der UpdateManager den
`composer install`-Schritt mit Hinweis und faehrt mit dem
mitgelieferten `vendor/` fort.

`php artisan migrate` braucht **kein** `proc_open` — wird in-process
ueber `Artisan::call()` ausgefuehrt.

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

### Alternative zu Schritt 2-3: Aus Backup wiederherstellen

Auf der Welcome-Seite gibt es zwei Karten:

- **Frische Installation** — der oben beschriebene Weg.
- **Aus Backup wiederherstellen** — perfekt fuer den Umzug auf einen
  neuen Host. Du bekommst ein einzelnes Formular:
  - App-Name, App-URL
  - DB-Treiber (muss zum Backup passen) + ggf. MySQL-Zugangsdaten
  - Backup-ZIP zum Upload
  - Bestaetigung „bestehende Daten werden ueberschrieben"

Beim Absenden passiert intern:

1. `.env` wird geschrieben, APP_KEY generiert falls leer.
2. DB-Verbindung wird getestet (MySQL).
3. ZIP wird nach `storage/app/backups/` geschoben und das Manifest
   gelesen. Stimmt der Driver nicht ueberein -> Fehler.
4. `BackupService::restore()` faehrt mit dem Maintenance-Flag und
   ersetzt DB + Anhaenge.
5. `migrate --force` laeuft (falls das Code-Schema neuer als das
   Backup ist).
6. Marker `storage/app/.installed` wird gesetzt.
7. Du landest auf einer Erfolgsseite und meldest dich mit **deinen
   bisherigen Admin-Zugangsdaten** an (die aus dem Backup).

**Upload-Limit**: die Seite zeigt das aktuelle PHP-Limit
(`upload_max_filesize` / `post_max_size`). Bei zu grossen Backups:
ZIP per FTP nach `storage/app/backups/` schieben und ueber CLI
`php artisan backup:restore <datei>` einspielen — danach manuell
`storage/app/.installed` anlegen oder einfach im Browser
`/install/finish` aufrufen.

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
