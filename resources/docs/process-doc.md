# Prozessbeschreibung als PDF

Fuer jeden Workflow kannst du auf Knopfdruck eine **druckfertige
Prozessbeschreibung** als PDF erzeugen. Ideal fuer:

- QM-Handbuch / ISO-9001-Audit
- GoBD-Verfahrensdokumentation
- Onboarding neuer Mitarbeiter („was macht dieser Workflow?")
- Externe Audits (WP, Datenschuetzer)

## Wo finde ich den Button?

Drei Stellen:

- **[Workflows-Uebersicht](app:workflows.index)** — pro Zeile ein
  Link **„PDF-Doku"** rechts neben „Designer".
- **Workflow-Designer-Toolbar** — neben „Versionen" der gleiche Link
  („PDF-Doku"). Oeffnet die Doku der aktuell gespeicherten Version
  in einem neuen Tab.
- **[Versions-Liste](app:workflows.versions)** — pro Version eigener
  Druck-Button. So kannst du auch alte Stände auditieren.

## Was steht drin?

1. **Header**: Workflow-Name, Status (aktiv/Entwurf/archiviert), Version,
   Ersteller, Beschreibung.
2. **Allgemein**: ID, Trigger-Typ, Knoten-Anzahl.
3. **Antrags-Formular** (wenn definiert): Tabelle mit Schluessel, Label,
   Typ, Pflicht-Markierung.
4. **Ablauf-Uebersicht**: Tabelle aller Knoten mit Nummer, Label, Typ
   und „folgt auf"-Spalte. So sieht man auf einen Blick die Reihenfolge,
   auch ohne Diagramm.
5. **Knoten im Detail**: pro Knoten ein eigener Block mit allen
   relevanten Konfig-Werten. Pro Knotentyp eigene Darstellung:
   - **Approval**: Empfaenger, Karenzzeit, Eskalation, Quorum-Modus,
     Zusatzfelder.
   - **Bedingung**: Branches-Tabelle mit Feld / Operator / Wert / Label.
   - **Switch**: Cases-Tabelle.
   - **HTTP**: Method, URL, Auth (Token **maskiert**), Header (Auth-
     Header maskiert), Body-Template, Response-Mapping.
   - **Notify**: Empfaenger, Betreff, Body-Template.
   - **PDF**: Filename, Doku-Typ, HTML-Template (gekuerzt).
   - **Sub-Workflow**: Ziel, Input/Output-Mapping.
   - **For-each**: Quellfeld, Iter-Limit, Sammler-Konfig.
   - **Aggregator**: Operation + Felder.
6. **Footer**: Generierungs-Datum, App-Name, **SHA-256-Hash der
   Definition** (erste 32 Zeichen) — damit ein Auditor merkt, ob die
   ausgedruckte Beschreibung noch dem Live-Stand entspricht.

## Was nicht im PDF steht

- **Keine Geheimnisse**: HTTP-Tokens, Basic-Auth-Passwoerter und
  Authorization-Header werden als `****abcd` maskiert. Die
  Prozessbeschreibung darf weitergegeben werden, ohne Zugangsdaten zu
  leaken.
- **Kein Diagramm** im PDF — DomPDF rendert das Drawflow-Canvas nicht.
  Stattdessen die nummerierte Ablauf-Tabelle (die ist fuer Auditoren
  ohnehin besser lesbar).

## Audit

Jeder Druck wird im Audit-Log als `workflow.process_doc.printed`
festgehalten — mit Version-ID und Definition-Hash. So weisst du im
Zweifel wer wann welchen Stand ausgedruckt hat.

## Tipp fuer GoBD-Verfahrensdokumentation

In der [GoBD-Verfahrensdokumentations-Vorlage](app:help.show?topic=gobd)
ist Kapitel 4 „Workflows" vorgesehen — pro aktivem Workflow eine
eigene Beschreibung. Die PDF-Doku ist genau das, was du dort
einheften kannst:

1. Pro relevantem Workflow „PDF-Doku" anklicken
2. PDF speichern unter `verfahrensdokumentation/workflows/`
3. In der Verfahrensdokumentation auf die Anlage verweisen

Beim naechsten Workflow-Update einfach erneut drucken — der
Definition-Hash im Footer dokumentiert die Versions-Aenderung.
