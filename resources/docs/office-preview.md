# Office-Dateien als Vorschau (LibreOffice)

OWE zeigt im Doku-Split-View und auf der Aufgabe-Detail-Seite
Vorschauen von **PDFs und Bildern** direkt im Browser an. Office-
Dateien (Word, Excel, PowerPoint, OpenDocument) konnten bisher nur
heruntergeladen werden — dabei sind sie in vielen DMS-Szenarien
genauso wichtig wie PDFs.

Mit LibreOffice als Konverter im Hintergrund werden DOCX/XLSX/PPTX
& Co automatisch nach PDF konvertiert und genauso wie PDFs angezeigt.

> [!IMPORTANT]
> LibreOffice ist **optional**. Ohne dieses Paket funktioniert OWE
> wie bisher — Office-Dateien werden zum Download angeboten. Mit
> installiertem LibreOffice schalten sich die Vorschauen automatisch
> ein, es ist nichts in OWE zu konfigurieren.

## Welche Dateitypen?

- Word: `.doc`, `.docx`, `.rtf`
- Excel: `.xls`, `.xlsx`
- PowerPoint: `.ppt`, `.pptx`
- OpenDocument: `.odt`, `.ods`, `.odp`

## Installation auf dem Server

### Debian / Ubuntu

```bash
sudo apt update
sudo apt install libreoffice-core libreoffice-writer libreoffice-calc libreoffice-impress
```

Wer es schlank halten will: nur die Programme installieren die wirklich
gebraucht werden. `libreoffice-core` ist Pflicht. `writer`/`calc`/`impress`
fuer die jeweiligen Formate.

### RHEL / Rocky / Alma

```bash
sudo dnf install libreoffice-headless
```

### Docker / Container

Im offiziellen `php`-Container ist LibreOffice nicht drin. Wer mit
einem Custom-Dockerfile arbeitet:

```dockerfile
RUN apt-get update && apt-get install -y --no-install-recommends \
    libreoffice-core libreoffice-writer libreoffice-calc libreoffice-impress \
 && rm -rf /var/lib/apt/lists/*
```

(+ ca. 600 MB Image-Groesse — wer kein Office braucht, weglassen.)

## Wie pruefe ich ob's funktioniert?

```bash
which libreoffice
libreoffice --version
```

OWE sucht den Binary in dieser Reihenfolge:

1. `LIBREOFFICE_BIN` aus der `.env` (falls gesetzt)
2. `/usr/bin/libreoffice`
3. `/usr/bin/soffice`
4. `/opt/libreoffice/program/soffice`
5. `command -v libreoffice` / `soffice` aus `$PATH`

Falls keine dieser Optionen findet, gibt's keine Office-Preview —
auch kein Fehler. Du kannst manuell forcieren:

```ini
LIBREOFFICE_BIN=/usr/bin/soffice
```

## Cache-Verhalten

Konvertierte PDFs landen in `storage/app/preview-cache/{hash}.pdf` —
der Hash ist der SHA-256 des Originals. Vorteile:

- Zweite Anfrage auf dieselbe Datei: keine erneute Konversion (Cache-Hit
  in Millisekunden statt ~2 Sekunden).
- Aenderungen an Indexfeldern / Tags / etc. invalidieren den Cache
  nicht — das Original-File bleibt ja gleich.
- Neue Version derselben Datei: neuer Hash, eigener Cache-Eintrag.
- Bei Speicherplatz-Knappheit reicht ein `rm -rf storage/app/preview-cache/`
  — wird beim naechsten Aufruf wieder befuellt.

## Performance / Timeouts

- LibreOffice braucht beim ersten Start nach Server-Reboot ca. 2-3 s
  zum Hochfahren (User-Profil-Setup). Folge-Konversionen ca. 0.5-1 s.
- Conversion-Timeout ist auf 60 Sekunden gesetzt — wer hat denn Office-
  Files die das ueberschreiten? Bei Bedarf `OfficePreview::convertToPdf()`
  anpassen.
- Bei Bulk-Operationen (z. B. 100 Files in der Liste durchscrollen)
  empfiehlt es sich, mit aktiviertem [Queue-Worker](app:help.show?topic=queue-worker)
  zu arbeiten — sonst blockiert Browser-Caching parallele Klicks.

## Deaktivieren

Wenn LibreOffice installiert ist, du es aber **nicht** fuer Previews
nutzen willst:

```ini
LIBREOFFICE_PREVIEW=false
```

Greift sofort. Office-Files werden wieder als Download ausgeliefert.

## Sicherheit

LibreOffice oeffnet Dateien — also kommt potentiell beliebiges Office-
Format in den Konverter. CVE-Historie ist ueberschaubar, aber:

- Container-Isolierung: wer paranoid ist, packt LibreOffice in einen
  separaten Service-Container und ruft ihn via HTTP. Aktuell nicht
  vorbereitet — wuerde einen Adapter brauchen.
- `--norestore`, `--nolockcheck`, `--nodefault` sind gesetzt → keine
  Persistenz-Zustaende zwischen Aufrufen.
- Konvertierung laeuft als der OWE-User, mit dem gleichen Rechtekontext
  wie alle anderen PHP-Operationen.
