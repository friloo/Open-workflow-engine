# Sharing-Links

Dokumente koennen ueber einen tokenbasierten Link mit Externen geteilt
werden — ohne dass diese einen Account brauchen. Inline-PDF-Viewer im
Browser, optional Passwortschutz, optional Download-Limit, immer mit
Ablaufdatum (vom Admin gedeckelt) und turnusmaessiger Pruefungs-Mail an
den Ersteller.

## Anlegen

Permission `shares.create` (Default: Admin, Workflow-Designer).

Auf der **Detail-Seite eines Dokuments** erscheint die Karte „Link
teilen":

- **Gueltig (Tage)** — Default und Hoechstwert kommen aus den
  Systemeinstellungen. Eingaben werden vom Admin-Cap nie ueberschritten.
- **Max. Zugriffe** — optional, danach wird der Link inaktiv.
- **Passwort** — optional, Bcrypt-Hash gespeichert, Empfaenger gibt es
  einmal pro Browser-Session ein.
- **Notiz (intern)** — z. B. „fuer Anwalt Mueller bis Ende Juni".
- **Immer die aktuelle Version freigeben** (Default an) — wenn das Doku
  versioniert wird, sieht der Empfaenger die jeweils neueste Fassung.
  Ist es aus, bleibt es bei der konkreten Version, mit der der Link
  erstellt wurde.

Nach dem Anlegen wird der Link direkt in der Karte angezeigt und kann
in die Zwischenablage kopiert werden.

## Was der Empfaenger sieht

- Schlanker Viewer mit App-Branding, Dateiname, optionaler Notiz und
  Ablaufdatum.
- PDF: inline im iframe (kein Download noetig, kein Login).
- Bild: inline.
- Andere Dateitypen: Download-Button.

## Periodische Pruefung

Standardmaessig erhaelt der Ersteller alle 7 Tage eine E-Mail:

- Datei, Notiz, Ablaufdatum, Anzahl Zugriffe
- Zwei Buttons (signierte URLs, kein Login noetig):
  - **„Freigabe behalten"** — oeffnet Formular mit Begruendung
  - **„Jetzt widerrufen"** — Link wird sofort widerrufen

**Wird nicht reagiert**, wird die Freigabe nach der Reaktions-Frist (Default 3 Tage) **automatisch widerrufen**. Der Ersteller bekommt eine Mail mit Hinweis.

Audit-Log dokumentiert:
- `share.created` mit Token (gehasht), Ablauf, Max-Downloads, Password-Flag
- `share.review.sent`, `.confirmed`, `.revoked`, `.auto_revoked`
- jedes Aufrufen ueber den Link landet in `share_link_accesses` (IP,
  User-Agent, Aktion, Erfolg, Zeitstempel).

## Verwaltung

- *Freigaben* in der Sidebar (Stammdaten-Gruppe) zeigt die eigenen Links
  inkl. Status, Zugriffen, letzter Pruefung.
- Admins mit `shares.manage_all` sehen alle Freigaben.
- Pro Zeile gibt es **Link kopieren** und **Widerrufen**.

## Systemeinstellungen (Admin)

*Verwaltung -> Systemeinstellungen -> Sharing-Links*:

- **Max. Ablauf (Tage)** — Cap. Wert kann von individuellen Links nie ueberschritten werden. Default 90.
- **Default-Ablauf (Tage)** — Vorschlag beim Anlegen. Wird automatisch auf den Max gecappt.
- **Pruefungs-Intervall (Tage)** — wie oft die Mail kommt. Default 7.
- **Reaktions-Frist (Tage)** — wie lange auf eine Antwort gewartet wird. Default 3.

## Cron

Der Scheduler ruft `shares:review` taeglich um 07:00 auf. Ein Cron-Eintrag genuegt fuer alle Aufgaben:

```cron
* * * * * cd /pfad && php artisan schedule:run >> /dev/null 2>&1
```
