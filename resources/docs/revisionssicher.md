# Revisionssicherheit

Drei Ebenen, die zusammen Manipulation erkennen lassen:

## 1. Audit-Log (Hashkette)

Jede sicherheitsrelevante Aktion (Login, User-/Rollen-Aenderung,
Workflow-Anlegen/Speichern/Aktivieren, Datei-Upload, KI-Aufrufe etc.)
landet in `audit_logs`. Jeder Eintrag enthaelt `prev_hash` und einen
SHA-256-`hash` ueber die Felder.

- Eintraege koennen ueber Eloquent NICHT modifiziert oder geloescht werden.
- Unter *Audit-Log -> Integritaetskette pruefen* wird die gesamte Kette
  neu berechnet — Manipulationen fuehren zu einem Bruch und werden
  exakt ausgewiesen (Stelle, erwarteter vs. gespeicherter Hash).

## 2. Workflow-Versionierung (immutable)

Jedes Speichern im Designer erzeugt eine neue `workflow_version`. Bestehende
Versionen sind unveraenderbar (`updating` und `deleting` werfen eine
`RuntimeException`). Workflow-Instanzen referenzieren die Version, mit der
sie gestartet wurden — der Lauf bleibt nachvollziehbar, auch wenn der
Workflow danach geaendert wird.

## 3. Datei-Anhaenge mit SHA-256

Jeder Upload (Asset-Scan, Formular-Datei) bekommt beim Speichern einen
SHA-256-Hash in `attachments.content_hash`. Eigenschaften wie Pfad, Hash
und Groesse sind nach dem Anlegen immutabel.

- Beim Asset-Workflow werden Anhaenge **kopiert** (eigene Datei mit
  eigenem Hash), damit Lebenszyklen von Asset und Instanz entkoppelt sind.
- Unter *Audit-Log -> Datei-Integritaet pruefen* werden alle Dateien
  geoeffnet, deren SHA-256 neu berechnet und mit dem gespeicherten Hash
  verglichen. Verdaechtige Dateien werden namentlich gelistet.
- Im Audit-Log steht der Hash auch im Upload-Event — damit ist
  rueckverfolgbar, welche Datei zu welchem Zeitpunkt vorlag.

## Was das bedeutet

- Eine PDF-Manipulation auf der Platte wird durch *Datei-Integritaet
  pruefen* erkannt.
- Ein Versuch, eine Audit-Zeile in der DB zu aendern, bricht die Kette
  und wird durch *Audit-Kette pruefen* erkannt.
- Eine bereits gespeicherte Workflow-Version aendern, ist ueber das
  System nicht moeglich — nur Wiederherstellen einer alten Version als
  NEUE Version.

## Operativ

- Storage-Backup: `storage/app/attachments` regelmaessig sichern.
- DB-Backup einschliesslich `audit_logs`, `attachments`, `workflow_versions`.
- Beide Integritaetspruefungen koennen auch automatisiert per Cron
  laufen — Erweiterung um eine Console-Variante ist leicht moeglich.
