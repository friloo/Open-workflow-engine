# Workflows entwerfen

## Knotentypen

| Knoten | Zweck | Ausgaenge |
|--------|-------|-----------|
| **Start** | Einstiegspunkt | 1 |
| **Genehmigung** | Aufgabe an Person/Rolle | Genehmigt / Abgelehnt / (Weitergeleitet) |
| **Bedingung** | Verzweigt nach Formularwerten oder `doc.*`-Feldern | beliebig viele + Else |
| **HTTP-Request** | Externe API aufrufen | OK / Fehler |
| **PDF erzeugen** | HTML-Template -> revisionssicheres PDF-Attachment | 1 |
| **Benachrichtigung** | E-Mail senden | 1 |
| **Ende** | Beendet die Instanz | 0 |

## Empfaenger-Typen

- **Vorgesetzter des Antragstellers** — der Chef der Person, die das Formular ausgefuellt hat.
- **Vorgesetzter des vorherigen Bearbeiters** — Kette aufwaerts.
- **Asset-Inhaber / Subject-User** — bei wiederkehrenden Workflows (Fuehrerschein) die Person, um die es geht.
- **Vorgesetzter des Subject-Users**
- **Rolle** — alle Mitglieder einer Rolle.
- **Konkrete Person** — fester Benutzer.
- **Aus Liste nachschlagen** — Schluessel aus einem Formularfeld *oder
  einem Dokument-Feld* in einer Lookup-Liste; Engine zieht die
  Verantwortlich-E-Mail. Mit **Fallback-Rolle / Fallback-Benutzer**,
  wenn der Lookup leer ist.

Der **Vertretung** wird automatisch gefolgt: ist der Empfaenger gerade
im Urlaubszeitraum, geht die Aufgabe direkt an die hinterlegte
Vertretung (siehe *Vertretungsregelung*).

## doc.\* — Routing nach Dokument-Inhalten

Wird ein Workflow aus dem **Postkorb** oder einem IMAP-Postfach mit
zugeordnetem Workflow gestartet, ist das Dokument unter `doc.*`
verfuegbar:

- `doc.id`, `doc.original_name`, `doc.document_type`, `doc.mime_type`,
  `doc.size`
- `doc.indexed_fields.<feld>` — alle Schema-Felder (z. B.
  `kostenstelle`, `rechnungsnummer`, `betrag_brutto`, `iban`, ...)

Beide nutzbar in:

- E-Mail-Subjects/Bodies, HTTP-Templates: `{{ doc.indexed_fields.kostenstelle }}`
- Bedingungs-Knoten: Feld = `doc.indexed_fields.kostenstelle`,
  Operator = `nicht leer`
- Approval-Empfaenger „Aus Liste nachschlagen", Schluessel-Feld =
  `doc.indexed_fields.kostenstelle`

End-to-End-Setup siehe **Cookbook: Rechnungseingang**.

## KI-Entwurf

Oben im Designer gibt es den Button **„KI-Entwurf"**. Beschreibe in
eigenen Worten, was der Workflow tun soll — die KI generiert ein
komplettes Geruest (Formularfelder, Knoten, Verbindungen). Du pruefst
und passt an. Erst Klick auf **Speichern** legt eine Version an.

Details siehe Abschnitt *KI im Designer*.

## Karenzzeit und Eskalation

Pro Genehmigungs-Knoten konfigurierst du eine Karenzzeit (Stunden, Tage, Monate).
Vor Ablauf bekommt der Empfaenger eine Erinnerungsmail (24 Stunden vorher).
Wird nach Ablauf nicht reagiert, eskaliert die Aufgabe an die konfigurierte
Eskalations-Person/-Rolle/-Liste.

Zusaetzlich versendet `tasks:remind` (taeglich 09:00) eine Erinnerung an
Empfaenger, deren Aufgabe laenger als drei Tage (Default) offen ist.
Konfigurierbar ueber Settings-Keys `tasks.reminder_after_days` und
`tasks.reminder_interval_days`.

## Parallel-Quorum (Vier-Augen / N-aus-M)

Pro Genehmigungs-Knoten ist ein **Quorum-Modus** waehlbar (nur wirksam,
wenn der Empfaenger eine **Rolle** ist):

- **Einer reicht (Standard)** — eine Person aus der Rolle uebernimmt.
- **Alle muessen zustimmen** — jede Person in der Rolle bekommt eine
  eigene Aufgabe. Eine Ablehnung bricht direkt ab; erst wenn **alle**
  zugestimmt haben, geht der Workflow weiter (Vier-/Sechs-Augen).
- **N von M** — `quorum_min` konfigurierbar. Sobald `quorum_min`
  Zustimmungen vorliegen -> approved. Wenn nicht mehr genug offene
  Stimmen uebrig sind, um das Quorum zu erreichen -> rejected.

Die uebrigen offenen Stimmen werden bei einer Endentscheidung
automatisch geschlossen (`decision = cancelled_quorum`) — damit sieht
der nicht-mehr-zustaendige Empfaenger die Aufgabe nicht mehr.

Sequenzielle Vier-Augen (Schritt-1 Buchhaltung -> Schritt-2 GF) ist
weiterhin moeglich ueber zwei aufeinanderfolgende Approval-Knoten.

## Warten + Felder setzen

Zwei zusaetzliche Knoten ohne menschliche Interaktion:

- **Warten** (`wait`) — pausiert den Workflow fuer Minuten / Stunden /
  Tage / Wochen / Monate. Der Scheduler-Lauf `workflow:check-due`
  (alle 5 Minuten) weckt automatisch wieder auf. Praktisch fuer
  „warte 7 Tage, dann erinnere".
- **Feld setzen** (`set_field`) — berechnet/setzt ein oder mehrere
  Felder in den Instanz-Daten. Werte koennen Platzhalter enthalten
  (`{{ netto }}`, `{{ doc.indexed_fields.brutto }}`). Mit „n"-
  Checkbox wird der Wert als Zahl interpretiert.

Damit kannst du Wartepunkte, Berechnungen und Variable-Manipulation
ohne Workarounds bauen.

## Trockenlauf / Simulation

Vor der Aktivierung: **Trockenlauf** (Button auf der Workflow-Edit-
Seite). Simuliert mit Testdaten ohne echte Mails / HTTP-Calls /
Persistierung und zeigt eine Timeline jedes besuchten Knotens.
Details: **Workflow-Simulation**.

## Pflichtkommentar bei Genehmigung / Ablehnung

Pro Genehmigungs-Knoten kannst du eine Begruendung erzwingen:

- **Kommentar bei Genehmigung verpflichtend**
- **Begruendung bei Ablehnung verpflichtend**

Wird auch bei *Genehmigung per Mail* respektiert — wer ohne
Pflichtkommentar absendet, bekommt einen Fehler statt einer
Speicherung.

## Versionierung

Jeder Klick auf **Speichern** legt eine neue, unveraenderbare Version an.
Aelter Versionen koennen unter *Versionen* wiederhergestellt werden — dabei
entsteht erneut eine neue Version.

## Aktivierung

Solange ein Workflow im Status *Entwurf* ist, kann er nicht gestartet werden.
Erst nach Klick auf **Aktivieren** ist er ueber das Formular erreichbar.
