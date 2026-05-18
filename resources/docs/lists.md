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
- Schluessel-Feld: das Formularfeld, das den Schluesselwert liefert (z. B. `kostenstelle`)

Die Engine zieht zur Laufzeit die Verantwortlich-Spalte und mappt die
E-Mail auf einen Benutzer. Eskalation funktioniert analog ueber die
Eskalations-Spalte.
