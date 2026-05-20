# Notizen & Stempel an Dokumenten

Jedes Dokument kann mit kurzen Notizen oder Stempeln (z. B. „Geprueft",
„Genehmigt", „Bezahlt") versehen werden. Das ist die klassische DMS-
Funktion fuer den papierlosen Posteingang: „Stempel drauf, fertig" —
ohne die Original-Datei zu veraendern.

## Wo finde ich das?

In der Dokumenten-Detail-Seite (`/dokumente/{id}`) findest du eine
Karte **„Notizen & Stempel"** zwischen der Vorschau und den Tags/Akten.

Sie zeigt:

- Sechs Stempel-Buttons mit voreingestellten Texten und Farben:
  *Geprueft, Genehmigt, Bezahlt, Storniert, Rueckfrage, Archiviert*
- Ein Freitext-Feld fuer Notizen
- Optional eine Seitenzahl (fuer mehrseitige PDFs)
- Die Liste aller bisherigen Eintraege mit Author + Datum

## Was passiert beim Stempeln?

Das **Original-PDF wird NICHT veraendert**. Stempel und Notizen sind
separate Datenbank-Eintraege, die zur Anzeige unter dem Dokument
geladen werden.

Das ist Absicht:

- Revisionssicherheit: SHA-256 des Originals bleibt unveraendert.
- Historie: jede Notiz ist eine Zeile mit Author + Datum.
- Loeschen: eine Notiz entfernen ist kein Eingriff in die Datei.

> [!NOTE]
> Wer **pixel-genaue Stempel im PDF selbst** braucht (z. B. fuer
> rechtsverbindliche Archivierung mit eingebrannten Genehmigungs-
> Stempeln), muss zur QES-Integration greifen — siehe geplante
> Erweiterung in der Compliance-Roadmap.

## Berechtigung

- **Notiz / Stempel anlegen**: jeder, der das Dokument sehen darf
  (`visibleTo()` — also Type-Permission oder Workflow-Assignee).
- **Eigene loeschen**: immer.
- **Fremde loeschen**: nur mit Permission `workflows.design` oder
  `documents.search`.

## Audit

Jede Aktion landet im Audit-Log:

- `pdf.annotation.added` — Hinzufuegen, mit Text + Page
- `pdf.annotation.removed` — Loeschen, mit Snapshot des Eintrags

Damit ist auch nachvollziehbar wer wann was gestempelt hat.

## Beispiel-Workflow

1. Rechnung kommt im IMAP-Postfach an, wird automatisch als
   Dokument-Typ „Rechnung" abgelegt + Workflow startet.
2. Buchhaltung bekommt die Aufgabe „Rechnung pruefen".
3. Sie oeffnet das Dokument-Detail, prueft das PDF im Preview.
4. Klick auf den Stempel **„Geprueft"**, Seitenzahl 1, „Hinzufuegen".
5. Geht zurueck zur Aufgabe, klickt **Genehmigen**.

Die Rechnung hat danach im DMS sichtbar einen „Geprueft"-Stempel
plus den Audit-Eintrag — egal ob jemand spaeter den Vorgang
zurueckverfolgt oder einfach das Dokument oeffnet.

## Hinweis fuer Workflow-Designer

In Approval-Knoten kannst du **Zusatzfelder beim Genehmigen**
definieren, die als Indexfelder am Dokument landen — siehe
[Erste Schritte](app:help.show?topic=first-steps).
Notizen + Stempel sind das Komplement davon: was *neben* der
Genehmigung als sichtbare Markierung am Dokument hangen bleibt,
ohne das Schema zu erweitern.
