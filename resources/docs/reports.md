# Reports & KPI-Dashboard

Workflow-uebergreifende Auswertungen — die globale Sicht ueber alle
Workflows, die der Statistik pro Workflow ergaenzt.

> Zugriff: **[Automatisierung → Reports](app:reports.index)** (Rechte:
> `reports.view`, hat per Default Admin und Workflow-Designer)

## Was wird gezeigt

### KPI-Karten
- **Gestartet** — wie viele Instanzen im Zeitraum begonnen wurden
- **Abgeschlossen** + Abschlussquote — wie viele davon erfolgreich
  durchliefen
- **Laufend** — aktuell offene Vorgaenge
- **Abgebrochen / Fehlgeschlagen** — fuer Stabilitaets-Sicht

### Volumen-Trend
Tagesweise Linie der Starts vs. Abschluesse. Hilft Spitzen zu sehen
(z. B. Monats-Ende, Quartals-Reporting).

### Durchlaufzeiten pro Workflow
Median + Mittel in Stunden, fuer **abgeschlossene** Instanzen im
Zeitraum. Median ist robuster gegen einzelne Ausreisser (z. B. ein
Vorgang der ueber Weihnachten lag).

### SLA-Verletzungen jetzt
Aktuell offene Aufgaben, deren Faelligkeit ueberschritten ist. Ein
Frueh-Indikator: wer wird wahrscheinlich nicht puenktlich liefern?

### Top-Verzoegerer-Knoten
Welcher Knoten in welchem Workflow blockiert am laengsten (Median vom
Zuweisen bis zum Abschluss)? So findest du:

- Bottlenecks („Freigabe Geschaeftsleitung" dauert immer 3 Tage)
- Approver mit Backlog
- Knoten die fuer Eskalation reif sind (Karenzzeit verkuerzen?)

### Top-Empfaenger
Wer hat aktuell die meisten offenen Aufgaben? Hilft bei:

- Workload-Verteilung
- Wer ist gerade ausgelastet, wem koennen wir noch was geben?
- Wer hat besonders viele ueberfaellige Aufgaben → vielleicht in
  Urlaub, Vertretung anstossen

## Zeitraum

Per Default 30 Tage. Wahlweise 7, 14, 30, 90, 180 oder 365 Tage.
Die KPI-Karten und Trend-Linie beziehen sich auf den Zeitraum.
SLA-Verletzungen und Top-Empfaenger sind „jetzt"-Snapshots.

## Daten-Quelle

Alle Zahlen kommen direkt aus den Workflow-Tabellen
(`workflow_instances`, `workflow_step_executions`) — keine separate
Aggregations-Schicht. Bei hohen Datenmengen kann das Dashboard
langsam werden; dann lohnt sich ein nachgelagerter Reporting-Cache
(z. B. via Cron auf eine `reports_daily_snapshots`-Tabelle schreiben).

## Tipp

Die Reports-Seite ist absichtlich **read-only**. Wenn du auf einen
Workflow-Namen klickst, kommst du zur per-Workflow-Detail-Statistik.
Daher: Reports = globale Bird's-Eye, Stats pro Workflow = Drill-Down.
