# Workflow-Simulation (Trockenlauf)

Bevor ein Workflow live geht, kann er mit Testdaten durchgespielt
werden — **ohne** echte Mails, HTTP-Calls, Webhooks oder Instanzen.

## Aufrufen

Workflow-Edit-Seite -> Button **Trockenlauf**. Permission
`workflows.design`.

## So funktioniert es

Im linken Bereich kannst du die Formularfelder des Workflows mit
Werten fuellen (und optional freie Zusatzfelder wie
`doc_attachment_id` setzen).

Nach **Simulieren** zeigt der rechte Bereich eine Timeline jedes
besuchten Knotens:

- **Start** -> gestartet
- **Bedingung** -> welche Verzweigung greift und warum
- **Genehmigung** -> wer der Empfaenger waere (Vorgesetzter, Rolle,
  Lookup-Treffer aus Liste, ...)
- **HTTP** / **Notify** / **PDF** -> wird angezeigt, aber **nicht**
  ausgefuehrt
- **Ende** -> mit gesetztem Ergebnis (`completed`/`rejected`/...)

## Approval-Verhalten

Bei Approval-Knoten geht die Simulation **immer den Genehmigt-Pfad**.
Wenn du den Ablehnungs-Pfad testen willst, verwendest du am besten
einen Bedingungs-Knoten auf ein Test-Feld davor — so siehst du beide
Pfade reproduzierbar.

## Was die Simulation NICHT prueft

- Echte Permissions des spaeteren Bearbeiters
- Network-Fehler in HTTP-Knoten
- Tatsaechlichen OCR-Inhalt eines Dokuments
- Lookup-Werte, die zur Laufzeit aus der Lookup-Liste kommen (es
  wird zur Simulationszeit nachgeschlagen — das passt also)

## Audit

Der Trockenlauf erzeugt **keine** Audit-Eintraege. Wenn du fuer
Compliance dokumentieren willst, dass du getestet hast, mach einen
Screenshot.
