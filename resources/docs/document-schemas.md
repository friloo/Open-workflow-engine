# Felder-Schemas pro Dokumenttyp

Damit aus dem PDF einer Rechnung nicht nur ein Volltext, sondern auch
**strukturierte Daten** werden (Rechnungsnummer, Datum, Brutto, IBAN,
USt-ID, ...), kann pro Dokumenttyp ein Feld-Schema definiert werden.

## Konfiguration

*Verwaltung -> Dokument-Schemas -> Dokumenttyp bearbeiten*

Pro Feld:

- **Schluessel** — interner Name (Slug, z. B. `rechnungsnummer`).
- **Bezeichnung** — Anzeige-Label.
- **Typ** — `string`, `number`, `date`, `currency`, `iban`, `email`.
- **Erkennung**:
  - **Heuristik: Rechnungsnummer** — sucht nach „Rechnungsnr.",
    „Invoice", „Belegnr." etc.
  - **Heuristik: Datum (deutsch)** — bevorzugt Datum in der Naehe von
    „Rechnungsdatum:" / „Datum:"; normalisiert zu `YYYY-MM-DD`.
  - **Heuristik: EUR-Betrag** — bevorzugt Betrag bei
    „Gesamt/Brutto/Rechnungsbetrag"; sonst groesster €-Betrag.
    Normalisiert auf Dezimalpunkt (z. B. `119.00`).
  - **Heuristik: IBAN** — DE- und international, mit oder ohne Spaces.
  - **Heuristik: USt-IdNr. (DE)** — `DE\d{9}`.
  - **Heuristik: E-Mail** — einfache Pattern.
  - **Eigener Regex** — Wert kommt aus Capture-Group 1. Delimiter sind
    optional (ohne `/.../i` wird automatisch in `#...#i` gewrappt —
    Slash muss nicht escaped werden).
  - **KI** — KI-only.
- **KI als Fallback** — bei Heuristik/Regex zusaetzlich KI versuchen,
  wenn nichts gefunden wurde. Nur wirksam, wenn KI in den
  Systemeinstellungen konfiguriert ist.

## Wann es laeuft

- **Beim Upload** — direkt nach OCR. Synchron, best-effort.
- **Bei IMAP-Mail-Ingestion** — wenn das Postfach einen `document_type`
  hat, wird sofort danach extrahiert.
- **Manuell**:
  - In der Schema-Edit-Seite: **Jetzt neu indexieren** fuer alle
    Dokumente dieses Typs.
  - Konsole:
    ```bash
    php artisan documents:reindex --type=Rechnung
    php artisan documents:reindex --missing
    php artisan documents:reindex --id=42 --id=43
    ```

## Wo es landet

In der Spalte `attachments.indexed_fields` (JSON) inklusive
`indexed_at`. Sichtbar in der Dokumenten-Detailansicht als Karte
„Erkannte Felder".

## KI nur optional

Default: rein regelbasiert. KI wird nur aufgerufen, wenn:

1. AI in den Systemeinstellungen konfiguriert ist, **und**
2. das Feld `extractor=ki` hat oder `KI als Fallback` aktiv ist und
   die Heuristik nichts gefunden hat.

Ohne konfigurierte KI laeuft alles ueber Heuristik/Regex weiter — kein
Aussetzer, kein Netzwerk-Call.

## Audit

- `settings.document_schema.updated` — Schema-Aenderung
- `attachments.reindexed` — Massen-Reindex inkl. Anzahl
