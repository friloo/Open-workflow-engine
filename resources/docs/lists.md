# Listen — Kostenstellen, Standorte, Abteilungs-Mappings

Eine Liste ist eine Lookup-Tabelle. Sie hat Spalten mit Rollen:

- **Schluessel** — der Wert, der nachgeschlagen wird (genau eine Spalte)
- **Verantwortlich** — E-Mail des Standard-Empfaengers
- **Eskalation** — E-Mail bei Karenzzeit-Ueberschreitung
- **Sonstige** — fuer reine Informations-Felder

## Beispiel: Kostenstellen

| Kostenstelle | Verantwortlich | Eskalation |
|--------------|----------------|------------|
| 1000 | chef-it@firma.de | cio@firma.de |
| 2000 | chef-office@firma.de | coo@firma.de |

## CSV-Import

Spaltennamen im CSV-Header muessen den Spalten-Keys entsprechen
(case-insensitiv, snake-case). Trennzeichen ist standardmaessig `;`.
Bestehende Eintraege werden anhand des Schluessels aktualisiert.

## Verwendung im Workflow

Im Genehmigungs-Knoten:

- Empfaenger-Typ: **Aus Liste nachschlagen**
- Liste: die definierte Liste
- **Schluessel aus Feld**: ein Formularfeld-Name (z. B. `kostenstelle`)
  **oder Punktnotation** in einen anderen Kontext, z. B.
  `doc.indexed_fields.kostenstelle` (Wert aus dem erkannten Feld
  eines Dokuments — siehe **Felder-Schemas** und **Cookbook:
  Rechnungseingang**).
- **Fallback-Rolle / Fallback-Benutzer**: falls der Lookup nichts
  findet (z. B. die Kostenstelle steht nicht in der Liste), geht die
  Aufgabe an diesen Empfaenger.

Die Engine zieht zur Laufzeit die Verantwortlich-Spalte und mappt die
E-Mail auf einen Benutzer. Eskalation funktioniert analog ueber die
Eskalations-Spalte.

## Verwendung als Lerngrundlage fuer Felder-Schemas

Eine Lookup-Liste kann ohne Workflow auch dafuer benutzt werden, dass
Werte automatisch aus dem OCR-Text eines Dokuments erkannt werden.

Im Felder-Schema (Verwaltung -> Dokument-Schemas) wird pro Feld die
Erkennung **Lookup-Liste** gewaehlt. Das System durchsucht dann jeden
OCR-Text nach Eintraegen aus der Liste; bei mehreren Treffern gewinnt
der laengste (spezifischste) Schluessel. Aenderungen an der Liste
wirken sofort — kein Training, keine KI.

Damit ist das Anlernen schlicht: die ohnehin gepflegte
Kostenstellen-Liste reicht aus, damit eingehende Rechnungen ihre
Kostenstelle „lernen". Siehe **Cookbook: Rechnungseingang** fuer das
volle Setup.
