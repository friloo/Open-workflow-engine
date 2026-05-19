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

## Performance

Das XML wird **einmal pro Anhang** geparst (Cache nach
`content_hash`). Auch wenn mehrere Felder im Schema ZUGFeRD nutzen,
wird die Datei nur einmal verarbeitet.
