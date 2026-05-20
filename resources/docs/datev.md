# DATEV-Export

Mit dem DATEV-Export schickst du im Stapel klassifizierte Belege
(Rechnungen, Eingangsbelege) als **Buchungsstapel-CSV plus Original-
PDFs** an die Buchhaltung — DATEV Unternehmen Online importiert das
in einem Rutsch.

## Wer braucht das?

Typisches DE-Mittelstands-Setup:

- OWE archiviert eingehende Rechnungen, OCR + ZUGFeRD lesen die
  Felder aus.
- Workflow lässt sie genehmigen.
- Monatlich werden alle freigegebenen Belege ans Steuerbüro
  geschickt — heute oft als Mail mit Excel-Liste + PDFs.

Mit dem DATEV-Export wird das ein Klick:
[Admin → DATEV-Export](app:admin.datev.index), Zeitraum waehlen,
„ZIP herunterladen". Die Datei kann direkt im DATEV Belegimport
hochgeladen werden.

## Was ist im ZIP drin?

```
DATEV-Export-2026-04-01-bis-2026-04-30.zip
├── README.txt            Erstellungs-Metadaten + Hinweise
├── buchungsstapel.csv    Buchungssaetze im DATEV-Format
└── belege/
    ├── 0001_4711.pdf     Original-PDFs, fortlaufend nummeriert
    ├── 0002_4712.pdf       + Attachment-ID im Dateinamen
    └── ...
```

## CSV-Format

Standard-DATEV-Buchungsstapel:

- UTF-8 mit BOM (Excel-kompatibel)
- Semikolon-getrennt
- Zahlen mit Komma-Dezimal
- Datum als `dd.mm.yyyy`

Spalten (in Reihenfolge):

| # | Spalte | Quelle |
|---|---|---|
| 1 | Umsatz | `indexed_fields.betrag_brutto` |
| 2 | Soll/Haben | Fix `S` (Aufwand) |
| 3 | WKZ Umsatz | `EUR` |
| 4 | Kurs | leer |
| 5 | Basis-Umsatz | leer |
| 6 | WKZ Basis | leer |
| 7 | Konto | aus Config (Default 4400 Aufwand) |
| 8 | Gegenkonto | aus Config (Default 70000 Kreditor) |
| 9 | BU-Schluessel | aus Config (Default leer) |
| 10 | Belegdatum | `indexed_fields.datum` |
| 11 | Belegfeld 1 | `indexed_fields.rechnungsnummer` |
| 12 | Belegfeld 2 | `indexed_fields.ustid` |
| 13 | Skonto | leer |
| 14 | Buchungstext | `indexed_fields.lieferant` (oder `Rg. <nr>`) |
| 15 | Postensperre | leer |
| 16 | KOST1 | `indexed_fields.kostenstelle` |
| 17 | KOST-Menge | leer |
| 18 | EU-USt-IdNr | `indexed_fields.ustid` |

## Konfiguration

[Admin → DATEV-Export](app:admin.datev.index) hat eine Karte mit
Default-Konten:

- **Konto Lieferant** — Kreditoren-Konto, default `70000`
- **Gegenkonto** — Aufwandskonto, default `4400`
- **BU-Schluessel** — z. B. `9` fuer 19% Vorsteuer

Plus das **Indexfeld → DATEV-Spalte-Mapping**. Wenn deine Indexfelder
anders heissen (z. B. `total_brutto` statt `betrag_brutto`), trag
das hier ein und der Export holt automatisch die richtigen Werte.

## Voraussetzungen

Damit der Export sinnvolle Daten liefert, muessen die Indexfelder
am Doku-Typ „Rechnung" gepflegt sein. Schau dazu in
[Felder-Schemas](app:admin.document_schemas.index) — pro Feld
einen Extractor (Heuristik, ZUGFeRD oder Lookup).

Empfohlene Schema-Felder fuer DATEV-Export:

- `datum` (Datum, Heuristik: Datum (deutsch))
- `rechnungsnummer` (string, Heuristik: Rechnungsnummer oder ZUGFeRD)
- `betrag_brutto` (currency, ZUGFeRD oder Heuristik EUR-Betrag)
- `betrag_netto` (currency, ZUGFeRD)
- `ustid` (string, Heuristik USt-IdNr.)
- `iban` (iban, Heuristik IBAN)
- `kostenstelle` (string, Lookup auf deine Kostenstellen-Liste)
- `lieferant` (string, ZUGFeRD vendor_name)

> [!TIP]
> ZUGFeRD/XRechnung-Eingangsrechnungen sind ideal — die Felder
> kommen direkt aus dem XML und sind dadurch immer korrekt.

## Audit + Wiederholbarkeit

Jeder Export wird im Audit-Log unter `datev.exported` festgehalten
(Zeitraum, Doku-Typ, Anzahl Belege). So weisst du im Zweifel, was
wann an wen geschickt wurde.

Du kannst denselben Zeitraum mehrfach exportieren — der Stapel ist
nicht „verbraucht" oder gesperrt. Wenn du das Doppel-Senden
verhindern willst, leg in DATEV einen Buchungs-Sperr-Marker an
oder filter im naechsten Export auf nur-noch-nicht-Exportierte
(geplant).

## Limitierungen

- **Kein EXTF-Header**: aktuell ist nur der Daten-Block in der CSV,
  ohne den Stammdaten-Header aus Zeile 1 des DATEV-EXTF-Formats.
  Fuer den DATEV-Belegimport in Unternehmen-Online reicht das, fuer
  Buchungs-Import muss ggf. der Header manuell ergaenzt werden.
- **Eine Buchung pro Beleg**: keine Split-Buchungen mit mehreren
  Kostenstellen oder Aufwandskonten. Wer das braucht, baut sich
  einen Workflow mit dem [For-each-Knoten](app:help.show?topic=sub-workflows)
  ueber `indexed_fields.positions`.
- **Default-Konto-Mapping**: fuer Mandanten mit individuellen
  Kontoplaenen muss man die Settings entsprechend anpassen.
- **Keine Stornos**: rejected Workflows werden trotzdem ins ZIP
  geschrieben. Aktuell auf das Doku-Typ-Filter abgestuetzt, kein
  Workflow-Status-Filter.
