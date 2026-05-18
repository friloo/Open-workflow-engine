# Workflow-Statistik

Reporting-Bereich, der zeigt, wie effizient eure Workflows tatsaechlich
laufen. Permission `workflows.view` (alle Workflow-Lesenden).

## Globale Uebersicht

*Workflows -> Statistik*

- **Laufend / Abgeschlossen / Fehlgeschlagen** — Instanz-Zaehler.
- **Ueberfaellige Aufgaben** — offene Schritte mit ueberschrittenem
  `due_at`.
- **Throughput letzte 12 Wochen** — Balkendiagramm: pro Kalenderwoche
  gestartete (indigo) vs. abgeschlossene (gruen) Instanzen. Zeigt
  Backlogs und saisonale Spitzen.

## Pro Workflow

*Workflows -> Statistik -> auf einen Workflow klicken*

- **Durchlaufzeiten** auf Basis der letzten 500 abgeschlossenen
  Instanzen:
  - **Median (p50)** — typischer Fall.
  - **Durchschnitt** — beeinflusst von Ausreissern.
  - **p95** — die langsamsten 5%. Gute Ziel-Metrik fuer SLAs.
- **Engpaesse** — Top 5 Schritte mit der laengsten durchschnittlichen
  Bearbeitungszeit (`assigned_at -> completed_at`). Praktisch um zu
  sehen, wo die Aufgaben liegenbleiben.
- **Throughput** — wie oben, gefiltert auf diesen Workflow.

## Hinweise

- Aggregationen laufen live aus `workflow_instances` und
  `workflow_step_executions`. Kein Caching — bei sehr grossen
  Datenmengen kann das ein paar Sekunden dauern.
- `p50/p95` werden ueber die letzten 500 abgeschlossenen Instanzen
  bzw. 1000 abgeschlossenen Schritte berechnet (Speicher- und
  Geschwindigkeits-Limit).
