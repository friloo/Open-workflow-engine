# ZUGFeRD / Factur-X / XRechnung

Eingebauter Parser fuer hybride elektronische Rechnungen, die als
PDF/A-3 mit eingebetteter XML-Datei vorliegen. Seit **01.01.2025
Pflicht** fuer B2B-Rechnungen in Deutschland (XRechnung im B2G-
Bereich schon seit 2020).

## Was OWE auslesen kann

Aus der eingebetteten CII- bzw. UBL-XML zieht OWE diese Felder
ohne Heuristik-Raterei:

| Schluessel | Feld |
|---|---|
| `invoice_number` | Rechnungsnummer |
| `invoice_date` | Datum (normalisiert auf YYYY-MM-DD) |
| `amount_net` | Netto-Summe |
| `amount_tax` | USt-Betrag |
| `amount_gross` | Brutto-/Gesamtbetrag |
| `currency` | Waehrung (z. B. EUR) |
| `vendor_name` | Lieferanten-Name |
| `vendor_vat_id` | USt-IdNr des Lieferanten |
| `iban` | IBAN |
| `bic` | BIC |
| `buyer_reference` | Leitweg-ID / Kaeufer-Referenz |

Profil-Erkennung: namespace-unabhaengig per `local-name()` XPath —
funktioniert mit Factur-X (Minimum/Basic-WL/Basic/Comfort/Extended),
ZUGFeRD 2.x, XRechnung CII und UBL.

## Konfiguration

*Verwaltung -> Dokument-Schemas -> Rechnung*. Neue Felder anlegen,
als Erkennung **ZUGFeRD/XRechnung: ...** waehlen. Beispiel-Setup:

| Schluessel | Erkennung |
|---|---|
| `rechnungsnummer` | `ZUGFeRD/XRechnung: Rechnungsnummer (XML)` |
| `datum` | `ZUGFeRD/XRechnung: Datum (XML)` |
| `brutto` | `ZUGFeRD/XRechnung: Brutto (XML)` |
| `lieferant` | `ZUGFeRD/XRechnung: Lieferant (XML)` |
| `iban` | `ZUGFeRD/XRechnung: IBAN (XML)` |

**Tipp**: Kombinier ZUGFeRD mit `KI als Fallback` — falls eine
Papier-Rechnung kommt (keine XML), springt die KI ein. Falls eine
e-Rechnung kommt (mit XML), wird sie sofort strukturiert ausgelesen,
ohne KI-Aufruf.

## Extraktions-Strategie

1. **Bevorzugt**: `pdfdetach -saveall` aus poppler-utils — entpackt
   eingebettete Dateien sauber. Sucht nach `factur-x.xml`,
   `ZUGFeRD-invoice.xml`, `xrechnung.xml`.
2. **Fallback**: Regex-basierte Suche im PDF-Stream nach
   `<?xml ... ?><CrossIndustryInvoice ...>...</...>`. Klappt fuer
   nicht-komprimierte PDF/A-3.

Beide Wege landen am selben XPath-Parser, der den Inhalt aufdroeselt.

## ZUGFeRD-Karte im Dokumenten-Viewer

Sobald ein PDF strukturierte ZUGFeRD/XRechnung-Daten enthaelt (oder
eine separate XML aus derselben Mail dazu gepairt wurde), erscheint
in der Dokumenten-Detail-Ansicht eine eigene **„ZUGFeRD / XRechnung"
Karte** mit allen erkannten Feldern (Rechnungsnummer, Datum, Betraege
formatiert in EUR, Lieferant, IBAN, USt-IdNr., Leitweg-ID).

## PDF + XML separat in Mail

Eingehende Rechnungen kommen oft als **zwei Anhaenge in einer Mail**:
ein Sicht-PDF und eine `xrechnung.xml`/`factur-x.xml` daneben (statt
eingebettet). Der **MailboxFetcher** erkennt diesen Fall:

1. XML-Anhang wird identifiziert (per Dateiname oder Inhalt: CII-/
   UBL-Root-Element).
2. ZUGFeRD-Felder werden aus dem XML extrahiert.
3. PDF-Anhang wird normal archiviert; die XML-Felder werden **am
   PDF** in `indexed_fields._zugferd` gespeichert (+ Standardfelder
   als first-class `indexed_fields.invoice_number` etc., damit
   Workflow-Bedingungen sie direkt sehen).
4. Die XML-Datei wird **nicht** separat archiviert — die Sicht-PDF
   ist der eine, durchsuchbare und revisionssichere Beleg.

So bekommst du das Beste aus beiden Welten: schoenes PDF zum Ansehen
+ strukturierte Daten zum Filtern und Routen.

## Performance

Das XML wird **einmal pro Anhang** geparst (Cache nach
`content_hash`). Auch wenn mehrere Felder im Schema ZUGFeRD nutzen,
wird die Datei nur einmal verarbeitet.
