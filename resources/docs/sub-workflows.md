# Sub-Workflows & For-each-Loops

Mit Sub-Workflows und Loops baust du komplexe Geschaeftsprozesse aus
kleinen wiederverwendbaren Bausteinen. Statt einen 50-Knoten-Monolithen
hast du dann z. B. einen Workflow „Rechnung pruefen" der zwei
Sub-Workflows ruft („Lieferanten-Check" und „Buchung in DATEV").

> [!TIP]
> Vorteil gegenueber einem alleinstehenden Workflow: Kommunikation
> ueber **Mapping** statt globalem State. Die Eltern-Instanz pausiert,
> der Sub-Workflow laeuft, beim Abschluss werden Werte aus der
> Child- in die Parent-Instance gemapped.

## Knoten 1: Sub-Workflow

Findest du im Designer in der Palette unter **Logik & Timing →
Sub-Workflow** (Icon `⊂`). Im Settings-Panel:

| Feld | Bedeutung |
|---|---|
| **Ziel-Workflow** | Workflow den du starten willst. Muss aktiv sein. Der Workflow auf dem du gerade baust ist ausgenommen (sonst Endlos-Rekursion). |
| **Eingabe-Mapping** (Parent → Child) | Felder, die in die Child-Instance gehen. Schluessel = Feld in der Child-Form, Wert = Pfad in der Parent-Instance oder Literal. |
| **Ausgabe-Mapping** (Child → Parent) | Werte aus der Child-Instance, die nach deren Abschluss zurueck in die Parent-Daten geschrieben werden. |
| **Bei Fehler weitermachen** | Auch wenn der Child-Workflow fehlschlaegt, geht's auf Ausgang **OK** statt **Fehler**. |

**Ausgaenge**: zwei Stueck — `OK` (Child completed) und `Fehler`
(Child failed/cancelled).

### Mapping-Syntax

Im Eingabe-Mapping wird der „Wert" so interpretiert:

- Beginnt mit `$.` → Pfad in der aktuellen Instanz-Daten.
  Beispiel: `$.kostenstelle` zieht `kostenstelle` aus den Parent-Daten.
  Verschachtelt geht auch: `$.doc.indexed_fields.rechnungsnummer`.
- Sonst → Literal-Wert. Beispiel: `"DE"` oder `42`.

Im Ausgabe-Mapping ist:

- `target` = Schluessel in den Parent-Daten
- `source` = Pfad in der Child-Instance (ohne `$.`-Praefix)

### Beispiel: „Beleg ablegen + DATEV-Buchung"

```
Parent-Workflow „Rechnung pruefen":
  Start → Genehmigung → [Sub-Workflow „DATEV-Buchung"] → Ende

Input-Mapping zum Sub:
  betrag          ← $.betrag
  kostenstelle    ← $.kostenstelle
  invoice_doc_id  ← $.doc_attachment_id

Output-Mapping zurueck:
  datev_buchung_id  ← buchung_nr
```

Wenn der Sub-Workflow im Knoten „Set Feld" `buchung_nr = "B-2026-04711"`
setzt, hat danach die Parent-Instanz `datev_buchung_id = "B-2026-04711"`
und kann das z. B. in einer Mail-Bestaetigung verwenden:

```
Deine Rechnung wurde gebucht: {{ datev_buchung_id }}
```

## Knoten 2: For-each (Loop)

In der Palette: **Logik & Timing → For-each** (Icon `⟳`). Iteriert ueber
eine Liste in der aktuellen Instanz und startet pro Element einen
Sub-Workflow.

| Feld | Bedeutung |
|---|---|
| **Quell-Feld (Liste)** | Pfad zur Liste, z. B. `items` oder `doc.indexed_fields.positions`. Muss auf einen Array zeigen, sonst macht der Loop nichts. |
| **Sub-Workflow pro Iteration** | Welcher Workflow pro Element gestartet wird. |
| **Element-Feldname** | Unter welchem Key das aktuelle Element in der Child-Instance landet (default `_item`). |
| **Max. Iterationen** | Sicherheitsgrenze gegen Endlos-Listen (default 100). |

**Verhalten**: alle Iterationen laufen **parallel**. Der Loop-Knoten
wartet bis alle Children fertig sind, dann geht der Workflow auf seinem
einzigen Ausgang `weiter`.

> [!NOTE]
> Wenn das Quell-Feld leer oder keine Liste ist, wird der Loop
> uebersprungen und es geht direkt auf `weiter`. Kein Fehler.

### Beispiel: „Positionsweise Genehmigung"

Eine Rechnung hat in `doc.indexed_fields.positions` ein Array:

```json
[
  {"pos": 1, "betrag": "500", "kostenstelle": "IT"},
  {"pos": 2, "betrag": "200", "kostenstelle": "MK"}
]
```

For-each-Knoten mit `source_field = doc.indexed_fields.positions`
und Sub-Workflow „Einzel-Position genehmigen" startet **2 parallele**
Sub-Instanzen. Jede hat `_item = {pos:..., betrag:..., kostenstelle:...}`
in ihren Daten und kann das im Approval-Empfaenger-Lookup nutzen:

```
Lookup-Empfaenger: $.{_item.kostenstelle}
```

## Best Practices

- **Klein halten**: Sub-Workflows sollten **eine** klar abgegrenzte
  Aufgabe machen, nicht 20 Knoten lang sein.
- **Versionierung**: aendert sich ein Sub-Workflow, betrifft das alle
  laufenden Parent-Instanzen die ihn noch aufrufen werden. Bestehende
  pausierte Parents behalten die Version, mit der der Sub-Workflow zum
  Startzeitpunkt aktiv war.
- **Endlos-Rekursion**: der Designer schliesst den aktuellen Workflow
  aus der Auswahl aus. Aber: A → B → A ist trotzdem moeglich. Pass auf.
- **Loops nicht ueberstrapazieren**: alle Iterationen laufen parallel —
  bei 100 Items werden 100 Sub-Instanzen + alle ihre Steps gleichzeitig
  angelegt. Bei groesseren Listen die `max_iterations` runter setzen
  oder die Liste vorab filtern.
- **Output-Mapping bei Loops**: nicht moeglich. Wenn du Aggregations-
  Ergebnisse brauchst (Summe aller Iterationen), bau einen eigenen
  „Aufrechnen"-Knoten danach mit `set_field`.

## Tracking & Verlauf

In `[Vorgang-Detail](app:workflow-instances.index)` siehst du
Parent-Instances mit allen Child-Steps in der Historie. Jede
Child-Instance hat einen eigenen Eintrag in der Vorgangs-Liste und
zeigt im Verlauf den Verweis auf ihren Parent-Step.

Audit-Log dokumentiert:

- `workflow.subworkflow.started` mit Parent- und Child-Instance-ID
- `workflow.loop.started` mit Iterations-Anzahl

## Was du heute *nicht* machen kannst (geplant)

- **Sequentielle Loops**: aktuell sind For-each-Iterationen immer
  parallel. Wer streng sequentiell will (z. B. weil jede Iteration auf
  dem Ergebnis der vorherigen aufbaut), baut das via Set-Feld-Knoten +
  Condition-Knoten von Hand.
- **Loop-Aggregation**: kein eingebautes „Sum/Avg ueber alle Children".
- **Tiefer als 2 Ebenen**: technisch moeglich, aber visuell schwer
  nachzuvollziehen. Limit per Best-Practice statt Hard-Limit.
