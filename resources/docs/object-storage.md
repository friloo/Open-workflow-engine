# Object-Storage (S3 / MinIO / Wasabi)

OWE legt Dokument-Anhaenge standardmaessig lokal im
`storage/app/private/attachments/`-Verzeichnis ab. Das reicht fuer
einzelne Server, klassisches Webhosting oder bis ca. 50 GB Dokumente.

Bei groesseren Mengen oder beim Wechsel auf Multi-Server-Setups (Load-
Balancer, mehrere App-Pods) brauchst du **Object-Storage**: einen
S3-kompatiblen Bucket (AWS S3, MinIO, Wasabi, Backblaze B2, …). OWE
nutzt dafuer das eingebaute Laravel-Filesystem.

> [!IMPORTANT]
> Object-Storage ist **optional**. Wer den lokalen Disk nutzt, braucht
> nichts zu konfigurieren — alles funktioniert wie bisher. Du kannst
> jederzeit umstellen (auch nachtraeglich).

## Wann lohnt sich der Wechsel?

- Mehrere App-Server (Load-Balancer, Auto-Scaling)
- Lokaler Disk knapp am Limit
- Versionsicheres Speichern mit S3 Object-Lock (WORM) gewuenscht
- Backup soll automatisch in eine andere Region replizieren
- Lifecycle-Rules (z. B. „nach 5 Jahren auf Glacier verschieben")

## Voraussetzungen

- Ein S3-kompatibler Endpunkt + Zugangsdaten (Access-Key / Secret)
- Ein Bucket. Bei MinIO/Wasabi: vorher anlegen.
- `aws/aws-sdk-php` muss installiert sein:

```bash
composer require league/flysystem-aws-s3-v3
```

(Der Composer-Befehl gehoert ins Release oder ins deployment, nicht
in den Live-Server.)

## Konfiguration

Empfohlen: **[Admin → Einstellungen → Infrastruktur](app:admin.settings.infrastructure)**.
Dort waehlst du den Disk (Lokal / S3) und traegst die Credentials ein.
Aenderungen sind sofort aktiv, kein SSH-Zugriff noetig.

Alternativ in der `.env` (gleicher Effekt, ueberschreibt aber nicht
das UI):

```ini
ATTACHMENTS_DISK=s3

AWS_ACCESS_KEY_ID=AKIA…
AWS_SECRET_ACCESS_KEY=…
AWS_DEFAULT_REGION=eu-central-1
AWS_BUCKET=owe-dokumente

# MinIO / Wasabi / B2: zusaetzlich
AWS_ENDPOINT=https://s3.eu-central-1.wasabisys.com
AWS_USE_PATH_STYLE_ENDPOINT=true
```

> [!NOTE]
> Werte aus dem UI haben Vorrang vor .env. Das UI traegt Overrides in
> die Settings-Tabelle; die .env-Werte sind nur Defaults fuer eine
> frische Installation ohne Admin-Zugriff.

## Bestehende Dateien migrieren

Wenn du auch das schon vorhandene Archiv verlagern willst:

```bash
php artisan attachments:migrate-disk s3 --dry-run
```

Zeigt was migriert wuerde, ohne irgendwas zu schreiben. Wenn das
plausibel aussieht:

```bash
php artisan attachments:migrate-disk s3
```

Der Command:

1. Iteriert ueber alle Anhaenge mit `disk != s3`
2. Liest die Datei vom alten Disk als Stream (Gross-Datei-safe)
3. Schreibt sie zum neuen Disk
4. Hash-prueft die kopierte Datei gegen den gespeicherten SHA-256
5. Loescht die Quelle erst nach erfolgreichem Hash-Check
6. Aktualisiert `attachments.disk` auf den neuen Wert

Idempotent: wer den Lauf abbricht oder Probleme bekommt, kann ihn
beliebig oft wieder starten. Schon migrierte Files werden uebersprungen.

> [!WARNING]
> Mach **zuerst ein Backup**. Auch wenn der Hash-Check rigoros ist —
> ein abgebrochener Lauf mitten in einer 10-GB-Migration ist
> ueberschaubarer wenn du noch ein Snapshot hast.

## Spezialfaelle

### MinIO lokal (Self-Hosted)

```ini
ATTACHMENTS_DISK=s3
AWS_ACCESS_KEY_ID=minio-access-key
AWS_SECRET_ACCESS_KEY=minio-secret
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=owe
AWS_ENDPOINT=http://minio.internal:9000
AWS_USE_PATH_STYLE_ENDPOINT=true
```

### S3 Object-Lock (WORM, GoBD-tauglich)

Object-Lock muss beim Bucket-Anlegen aktiviert sein. Setze pro Datei
einen Retention-Mode `COMPLIANCE` mit Mindest-Aufbewahrung — danach
kann die Datei selbst durch root nicht mehr geloescht werden, bis die
Frist abgelaufen ist. Aktuell nicht UI-konfigurierbar; setze die
Bucket-Default-Retention serverseitig und alles Neue erbt automatisch.

### Backblaze B2 / Wasabi

Funktionieren beide als S3-kompatibel mit
`AWS_USE_PATH_STYLE_ENDPOINT=true` und passendem `AWS_ENDPOINT`.

## Rueckweg: zurueck auf lokal

```bash
php artisan attachments:migrate-disk local
```

Plus `ATTACHMENTS_DISK=local` in der `.env`. Genauso idempotent.

## Was ist mit OCR, Preview, ZUGFeRD?

Alle Pipeline-Schritte arbeiten ueber `Storage::disk($attachment->disk)`
— funktioniert transparent mit lokal und mit S3. OCR-Worker laden die
Datei kurz lokal in `/tmp` herunter, extrahieren Text, schreiben das
Ergebnis in die DB und loeschen das Temp-File.

> [!TIP]
> Wenn du OCR oder ZUGFeRD-Parse beschleunigen willst, kombiniere den
> S3-Storage mit dem Queue-Worker (siehe
> [Queue-Worker](app:help.show?topic=queue-worker)). Uploads sind dann
> sofort fertig — die schwere Verarbeitung passiert im Hintergrund.
