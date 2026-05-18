# Formulare

Es gibt zwei Wege, Formulare zu nutzen:

- **Inline im Workflow** — der Workflow-Trigger ist *Formular*; im
  Designer definierst du Felder auf dem Tab *Formularfelder*.
- **Stand-Alone-Formulare** unter *Automatisierung -> Formulare* —
  unabhaengige Formulare mit eigenem CRUD, optional an einen Workflow
  gekoppelt.

## Feldtypen

- Text, mehrzeiliger Text, Zahl, Datum
- Dropdown, Radio
- Checkbox (ja/nein)
- **Datei-Upload** (PDF, Bild, Office; max. 15 MB)

Felder lassen sich per Drag-and-Drop sortieren.

## Bedingte Anzeige (show_if)

Jedes Feld kann eine Sichtbarkeits-Bedingung haben:

- *Feld* (auswaehlen aus den anderen Schema-Feldern)
- *Operator*: `ist gleich`, `ist ungleich`, `enthaelt`, `ist angekreuzt`,
  `nicht angekreuzt`, `nicht leer`, `leer`
- *Wert* (bei Operatoren die einen Wert brauchen)

Beispiel: Feld „Begruendung" wird nur eingeblendet, wenn „Betrag" > 500.

Im Frontend kontrolliert Alpine.js die Anzeige live (`x-show`). Im
Backend ueberspringt der Validator die Pflichtfeld-Pruefung fuer
ausgeblendete Felder.

## Pflichtfelder + Validierung

Die Validierung erfolgt server-seitig anhand des Schemas. Pflichtfelder
werden im Frontend mit `required` markiert und im Backend erzwungen.

## Oeffentliche Formulare

Stand-Alone-Formulare koennen mit `is_public` veroeffentlicht werden.

- URL: `/formular/{slug}`
- Ohne Login erreichbar
- Honeypot-Feld schuetzt vor Bots
- Datei-Uploads landen als Attachment an der Workflow-Instanz

Workflows haben kein eigenes `is_public` mehr — alle oeffentlichen
Formulare laufen ueber die Form-Entitaet, optional mit `workflow_id`.

## Eingaenge

Jede Abgabe wird in `form_submissions` gespeichert. Liste und Detail
unter *Formulare -> Eingaenge*. **CSV-Export** mit UTF-8 BOM und
Semikolon-Trennzeichen ist eingebaut.
