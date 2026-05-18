# Formulare

Es gibt zwei Wege, Formulare zu nutzen:

- **Inline im Workflow** — der Workflow-Trigger ist *Formular*; im Designer
  definierst du Felder auf dem Tab *Formularfelder*.
- **Stand-Alone-Formulare** unter *Automatisierung -> Formulare* —
  unabhaengige Formulare mit eigenem CRUD, optional an einen Workflow gekoppelt.

## Feldtypen

- Text, mehrzeiliger Text, Zahl, Datum
- Dropdown, Radio
- Checkbox (ja/nein)
- **Datei-Upload** (PDF, Bild, Office; max. 15 MB)

## Pflichtfelder + Validierung

Die Validierung erfolgt server-seitig anhand des Schemas. Pflichtfelder
werden im Frontend mit `required` markiert und im Backend erzwungen.

## Oeffentliche Formulare

Workflow oder Stand-Alone-Formular kann ueber `is_public` veroeffentlicht
werden. Die URL hat den Stil:

- Workflow: `/f/{slug}`
- Stand-Alone: `/formular/{slug}`

Beide Routen sind ohne Login erreichbar; ein Honeypot-Feld schuetzt vor Bots.

## Eingaenge

Jede Abgabe wird in `form_submissions` gespeichert. Liste und Detail
unter *Formulare -> Eingaenge*. **CSV-Export** mit UTF-8 BOM und
Semikolon-Trennzeichen ist eingebaut.

## Drag-and-Drop

Felder werden im Editor per Drag-and-Drop am Griff sortiert.
