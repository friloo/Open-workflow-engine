# Volltext-Suche skalieren mit MeiliSearch

OWE sucht in Dokumenten standardmaessig mit klassischem SQL-`LIKE`
ueber Dateinamen, Labels und OCR-Text. Das funktioniert gut bis ca.
**50 000 Anhaenge**, danach werden Suchanfragen merklich langsamer
(jede Query scannt im worst case alle Spalten-Volltexte).

Wer mehr braucht — oder fuzzy / Typo-tolerant suchen will — schaltet
auf **MeiliSearch**, einen freien Volltext-Suchserver. Liefert:

- Antwort in <10ms auch bei 1M Dokumenten
- Fuzzy / Typo-Toleranz („rechnng" findet „Rechnung")
- Eigene Relevanz-Sortierung
- Phrasen-Suche („genau dieser Satz")

> [!IMPORTANT]
> MeiliSearch ist **optional**. Bei `SEARCH_DRIVER=database` (Default)
> braucht's nichts. Wenn du es aktivierst aber MeiliSearch nicht
> erreichbar ist, fallen wir automatisch auf LIKE zurueck — die Suche
> klappt weiter, nur langsamer.

## Setup

### 1. MeiliSearch installieren

```bash
# Docker (einfachste Variante)
docker run -d --name meilisearch \
    -p 7700:7700 \
    -v meili_data:/meili_data \
    -e MEILI_MASTER_KEY=eineLangeZufallsZeichenkette \
    --restart unless-stopped \
    getmeili/meilisearch:v1.10
```

Oder als Binary:

```bash
curl -L https://install.meilisearch.com | sh
./meilisearch --master-key=eineLangeZufallsZeichenkette
```

> [!TIP]
> In Produktion `MEILI_MASTER_KEY` immer setzen — sonst ist der
> Server offen.

### 2. OWE konfigurieren

In der `.env`:

```ini
SEARCH_DRIVER=meilisearch
MEILISEARCH_HOST=http://127.0.0.1:7700
MEILISEARCH_KEY=eineLangeZufallsZeichenkette
```

Dann `php artisan config:clear`.

### 3. Initial befuellen

```bash
php artisan search:reindex
```

Liest alle vorhandenen Anhaenge aus der DB, schickt sie in Batches von
200 an MeiliSearch. Bei 100k Docs dauert das ein paar Minuten.

Idempotent — kannst du jederzeit wiederholen, wenn der Index verloren
gegangen ist (z. B. Volume geloescht).

## Was passiert dann automatisch?

- **Neue Uploads**: AttachmentStorage indexiert sie automatisch nach
  OCR + Feld-Extraktion.
- **OCR-Reindex** (`php artisan ocr:run-pending`): nach OCR-Update
  geht auch ein Update an MeiliSearch.
- **Loeschen**: aktuell **nicht** automatisch (soft-delete-Pattern).
  Wer regelmaessig hart loescht, sollte ein `search:reindex` als Cron
  taeglich laufen lassen.

## Was wird indexiert?

Pro Anhang:

```json
{
  "id": 4711,
  "original_name": "rechnung-mueller-2026.pdf",
  "label": "Mueller GmbH",
  "ocr_text": "Rechnungsnummer 12345 …",
  "document_type": "Rechnung",
  "created_at": 1716113400
}
```

Searchable: `original_name`, `label`, `ocr_text`.
Filterable: `document_type`.
Sortable: `created_at`.

Indexfeld-Filter (z. B. `betrag > 1000`) laufen weiter ueber MySQL —
MeiliSearch liefert nur die Treffer-IDs, der Rest passiert in
Eloquent. Damit bleiben alle Permission-Checks intakt.

## Status pruefen

In [Admin → System-Health](app:admin.health.index) sollte der Search-
Status auftauchen sobald MeiliSearch konfiguriert ist (Erweiterung
geplant). Manuell via CLI:

```bash
curl -H "Authorization: Bearer $MEILI_MASTER_KEY" http://127.0.0.1:7700/health
```

## Backup

Der MeiliSearch-Index ist *rebuild-baar* aus der DB — also kein
kritisches Backup-Ziel. Wenn der Index weg ist:

```bash
php artisan search:reindex
```

Tut's. Trotzdem empfehlenswert den Container/Volume mit zu sichern,
weil's bei 1M Docs deutlich schneller ist als ein Rebuild.

## Zurueck auf 'database'

```ini
SEARCH_DRIVER=database
```

`php artisan config:clear`. Fertig. Die DB-Spalten waren ja nie weg.

## Sicherheits-Hinweise

- MeiliSearch sollte nur **lokal** erreichbar sein (`127.0.0.1`), nicht
  oeffentlich. OWE spricht direkt mit dem Daemon — kein Bedarf, ihn
  ins Internet zu stellen.
- Der **Master-Key** ist quasi root fuer den Server. In `.env`
  nur lesbar fuer den App-User, niemals in Git committen.
- Wer **Search-API-Keys** (eingeschraenkte Read-only-Keys fuer einzelne
  Indizes) nutzen will: per MeiliSearch-Web-UI generieren und in
  `MEILISEARCH_KEY` setzen statt dem Master.
