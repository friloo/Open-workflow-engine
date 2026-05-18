# Dokumente

Zentraler Ort fuer alle hochgeladenen Dateien — Asset-Scans, Workflow-
Anhaenge, Standalone-Dokumente. Mit Volltextsuche, Versionierung,
Vorschau und rollenbasiertem Zugriff.

## Zugriff

Unter *Stammdaten -> Dokumente*. Benoetigt die Permission
`documents.search`.

## Dokumenttypen

Admins definieren in *Systemeinstellungen -> Dokumenttypen* eine flache
Liste (z. B. *Vertrag*, *Rechnung*, *Fuehrerschein*, *Krankmeldung*).

In *Systemeinstellungen -> Berechtigungen je Rolle* legst du dann fest,
welche Rolle welche Typen sieht. Beispiel:

| Rolle | Sichtbare Typen |
|-------|-----------------|
| Buchhaltung | Rechnung, Mahnung |
| HR | Fuehrerschein, Krankmeldung, Arbeitsvertrag |
| Admin | (alle) |

Admin sieht immer alles. Unklassifizierte Dokumente sind per Default nur
fuer Admins sichtbar — Schalter
`attachments.unclassified_visible_for_all` kann das umkehren.

## Versionierung

Jeder Upload startet eine **Versions-Kette** (UUID). Eine spaetere
Aktualisierung haengt sich an dieselbe Kette an:

- `version_number` zaehlt: v1, v2, v3, ...
- nur die juengste Version ist als `is_current` markiert
- In der Dokumenten-Liste erscheint **nur die aktuelle Version** pro Kette
- Auf der Detail-Seite sind **alle Versionen** sichtbar und herunterladbar
- Vorherige Versionen werden **niemals geloescht** — revisionssicher

Eine neue Version laedst du ueber die Schaltflaeche „Neue Version hochladen"
auf der Detail-Seite hoch.

## Bulk-Upload

*Dokumente -> Bulk-Upload* erlaubt das Hochladen von bis zu 50 Dateien
auf einmal mit gemeinsamem Dokumenttyp und Beschriftung. Drag-and-Drop
oder Datei-Picker. Diese Dokumente sind Stand-Alone (keinem Asset/Vorgang
zugeordnet) und tauchen direkt in der Suche auf.

## Inline-Preview

PDFs werden direkt im Browser angezeigt (iframe mit `Content-Disposition:
inline`), Bilder als `<img>`. Klick auf das Dokument oeffnet die Detailseite
mit Preview + extrahiertem OCR-Text + Versionen + Metadaten.

Wer nur die Datei in einem neuen Tab oeffnen oder herunterladen will, hat
beide Optionen unter der Preview.

## Volltextsuche (OCR)

Beim Upload extrahiert das System Text:

- **PDFs mit eingebettetem Text**: per `pdftotext` (poppler-utils)
- **Bild-PDFs**: per `pdftoppm` + `tesseract`
- **Bilder**: direkt per `tesseract`
- **Office-Dokumente** werden derzeit uebersprungen

Sind die Tools auf dem Server nicht installiert, wird die Datei mit
Status `skipped` markiert — die Suche funktioniert dann nur ueber
Dateinamen und Beschriftung.

Suche durchforstet Dateinamen, Beschriftung und OCR-Text. Treffer im
Volltext werden hervorgehoben angezeigt.

`php artisan ocr:run-pending` holt OCR-Extraktionen nach. Laeuft taeglich
um 02:30 ueber den Laravel-Scheduler.

## Strukturierte Felder pro Dokumenttyp

Zusaetzlich zum Volltext koennen pro Dokumenttyp **strukturierte
Felder** definiert werden (Rechnungsnummer, Datum, Brutto,
Kostenstelle, IBAN, USt-ID, ...). Werte werden direkt nach OCR
automatisch erkannt — entweder per Heuristik, eigenem Regex,
**Lookup-Liste** (Anlernen ueber eine gepflegte Liste) oder optional KI.

Siehe **Felder-Schemas pro Dokumenttyp** in der Hilfe und das
**Cookbook: Rechnungseingang** fuer das durchgaengige Beispiel.

Erkannte Felder sind:

- in der Dokumenten-Detailansicht **manuell korrigierbar** (Audit-
  Eintrag `document.fields.corrected`),
- als `{{ doc.indexed_fields.<feld> }}` in Workflow-Templates und
  Bedingungen verwendbar,
- via *Jetzt neu indexieren* pro Dokumenttyp komplett neu auslesbar
  (oder per Konsole `php artisan documents:reindex --type=Rechnung`).

## Postkorb

*Dokumente -> Postkorb* zeigt alle Dokumente, die noch keinem Workflow
oder Asset zugeordnet sind — typischerweise per IMAP eingegangene
Dateien ohne Auto-Workflow am Postfach. Pro Zeile siehst du die
erkannten Felder als Chips und kannst direkt einen Workflow starten.
Die Felder wandern als Kontext in den Workflow (`{{ doc.* }}`).

## Sicherheit

- SHA-256 fuer jede Datei beim Upload (`content_hash`).
- Hash, Pfad und Groesse sind immutable — Aenderungen ueber Eloquent
  werfen eine `RuntimeException`.
- Soft-Delete: einmal geloescht ist die Datei nicht physisch weg, sondern
  nur ausgeblendet. ForceDelete entfernt sie wirklich.
- *Audit-Log -> Datei-Integritaet pruefen* berechnet alle Hashes neu
  und meldet Manipulationen.

## Empfohlene Cron-Setups

Bereits durch den Scheduler abgedeckt (ein einziger Cron):

```cron
* * * * * cd /pfad/zur/app && php artisan schedule:run >> /dev/null 2>&1
```

Damit laufen:
- `ocr:run-pending` taeglich 02:30
- `audit:cleanup` monatlich (anonymisiert IP/UA aelter als 2 Jahre)
- Workflow-Eskalation alle 5 Minuten, Wiedervorlagen stuendlich,
  Assets taeglich 06:00.
