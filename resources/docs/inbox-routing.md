# Postkorb und Routing per Lookup

Kombiniert *erkannte Felder* + *Lookup-Listen* + *Workflows*. So
funktioniert ein typischer Rechnungs-Flow ganz ohne KI:

## 1. Anlernen: Kostenstellen pflegen

Listen-Verwaltung -> *Kostenstellen* (Lookup-Liste).
Mindestens zwei Spalten:

- **Kostenstelle** mit Rolle „Schluessel"
- **Verantwortlich** mit Rolle „Verantwortlich" (E-Mail-Adresse des
  Kostenstellen-Verantwortlichen)

Die Liste pflegst du wie immer (CSV-Import oder einzeln). Aenderungen
sind sofort wirksam — es muss nichts neu trainiert werden.

## 2. Schema fuer „Rechnung" konfigurieren

Verwaltung -> Dokument-Schemas -> *Rechnung*:

- Feld `kostenstelle`, Typ `string`, Erkennung **Lookup-Liste** ->
  Liste `kostenstellen`.
- Optional weitere Felder: `rechnungsnummer` (Heuristik), `datum`
  (Heuristik), `betrag_brutto` (Heuristik), `iban` (Heuristik).

Sobald eine Rechnung eingeht (Upload oder per IMAP), wird der
OCR-Text gegen die Lookup-Liste gehalten — bei einem Treffer landet
der Wert in `attachments.indexed_fields.kostenstelle`.

## 3. Workflow „Rechnungseingang"

Im Designer:

- **Bedingung** auf Feld `doc.indexed_fields.kostenstelle`,
  Operator `not_empty` -> Pfad „mit Kostenstelle".
- Im „mit Kostenstelle"-Pfad: **Genehmigung** mit Empfaenger-Typ
  **Aus Liste nachschlagen**, Liste = `Kostenstellen`,
  Schluessel-Feld = `doc.indexed_fields.kostenstelle`, **Fallback-
  Rolle** = `Einkauf` (Sicherheitsnetz, falls die Liste den Code
  nicht (mehr) kennt).
- Im Else-Pfad: **Genehmigung** mit Rolle = `Buchhaltung`.

## 4. Postkorb fuer manuelle Faelle

Wenn ein IMAP-Postfach **ohne** Workflow konfiguriert ist (oder das
Postfach einen Auto-Workflow hat, aber jemand laedt einen PDF
manuell), landet das Dokument unter *Dokumente -> Postkorb*. Pro
Zeile:

- Erkannte Felder als Chips
- Workflow-Picker + Button **Starten** -> Workflow startet mit
  `doc.*`-Kontext (Anhang wird automatisch an die Instanz gehaengt)

## 5. Korrekturen lernen

Auf der Detailseite eines Dokuments koennen die erkannten Felder
manuell ueberschrieben werden (nur Schluessel aus dem Schema). Wird
im Audit-Log dokumentiert (`document.fields.corrected`). Damit kannst
du Fehlmessungen einfach gerade ziehen, ohne den ganzen Anhang neu
hochzuladen.

## doc.\*-Platzhalter

Verfuegbar ueberall:

- `{{ doc.id }}`, `{{ doc.original_name }}`, `{{ doc.document_type }}`,
  `{{ doc.mime_type }}`, `{{ doc.size }}`
- `{{ doc.indexed_fields.<feld> }}` (alle Schema-Felder)

Auch in **Bedingungs-Knoten** (Feld = `doc.indexed_fields.kostenstelle`)
und im **Approval-Empfaenger** „Aus Liste nachschlagen" (Schluessel-
Feld in Punktnotation).
