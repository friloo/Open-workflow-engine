# Auto-Stempel auf PDFs bei Approval

Bei jedem Approval-Knoten kannst du einstellen, dass nach der
finalen Entscheidung **alle PDF-Anhaenge des Vorgangs automatisch
mit einem visuellen Stempel versehen** werden — „Genehmigt von Max
Mustermann am 15.05.2026 14:32, Vorgang Rechnungseingang #42".

> Konfigurierbar pro Approval-Knoten im
> **[Designer](app:help.show?topic=workflows)**, Reiter
> „PDF-Anhaenge des Vorgangs automatisch stempeln".

## Was passiert

1. Approval-Step wird abgeschlossen (finale Entscheidung steht — bei
   Quorum erst wenn das Mehrheits-Kriterium erfuellt ist).
2. OWE sammelt alle PDF-Anhaenge der Workflow-Instance, die als
   **aktuelle Version** markiert sind und MIME `application/pdf` haben.
3. Auf der letzten Seite jeder PDF wird unten rechts ein **rechteckiger
   Stempel** gerendert:
   - **Titel**: „Genehmigt" (gruen) / „Abgelehnt" (rot) / „Entschieden" (orange)
   - **Zeilen**: Approver-Name, Datum + Uhrzeit, Workflow-Name + Vorgangs-ID,
     Schritt-Key, optional Bemerkung (gekuerzt)
4. Die gestempelte PDF wird als **neue Version** desselben
   Attachments gespeichert (`GESTEMPELT_<originalname.pdf>`). Das
   Original bleibt unveraendert in der Versions-Historie — fuer Audit
   wichtig.
5. Audit-Log-Eintrag `attachment.stamped` mit Verweis auf den Approver.

## Optionen

Im Knoten-Properties-Panel:

- **Stempel aktivieren**: Checkbox an
- **Wann stempeln?**: nur bei Genehmigung / nur bei Ablehnung / bei jeder
  Entscheidung

Default-Empfehlung: **„nur bei Genehmigung"** — damit faengst du
Rechnungen, Vertraege, formale Freigaben ab. Ablehnungen brauchen
selten einen sichtbaren Stempel.

## Wo der Stempel sichtbar ist

- **In der OWE-Dokumentenliste**: das gestempelte PDF ist die
  aktuelle Version. Wer das Dokument oeffnet, sieht den Stempel.
- **Beim Download**: das heruntergeladene PDF traegt den Stempel.
  Auch ausserhalb von OWE (z. B. wenn jemand das PDF per Mail
  weiterleitet) ist die Genehmigung sichtbar.
- **In der Versions-Historie**: Original (v1, ohne Stempel) bleibt
  einsehbar, daneben v2 mit Stempel.

## Wann es nicht klappt

- **Verschluesselte PDFs**: koennen nicht importiert werden — werden
  uebersprungen, Audit-Log enthaelt einen Hinweis.
- **Sehr neue PDFs (Format > 1.4 mit moderner Compression)**: FPDI
  kann nicht alle Varianten lesen. Bei einer Fehlermeldung im Log
  steht der Grund.
- **Kein PDF unter den Anhaengen**: Stempel-Schritt laeuft, hat aber
  nichts zu tun (kein Fehler).
- **Auto-Stempel-Checkbox nicht gesetzt**: passiert ueberhaupt nichts
  (Default-Verhalten).

## Manuelle Stempel

Fuer manuelle, pixel-genaue Stempel (Signatur-Position via Drag &
Drop): siehe
[Notizen & Stempel](app:help.show?topic=annotations).
Der Auto-Stempel ist die *automatisierte* Variante fuer den Workflow.

## GoBD / Revisions-Sicherheit

- Originaldatei bleibt unveraendert (Hash + Version 1 in der Datenbank)
- Gestempeltes PDF hat eigenen Hash + Version 2 (eigene Audit-Spur)
- Audit-Event `attachment.stamped` ist hash-kettengesichert
- Der Stempel im PDF nennt: Approver, Datum, Workflow, Vorgang-ID — das
  reicht fuer GoBD-konforme Nachvollziehbarkeit, selbst wenn die
  OWE-Datenbank verloren gehen sollte
