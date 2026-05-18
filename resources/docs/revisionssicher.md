# Revisionssicherheit

Vier Ebenen, die zusammen Manipulation erkennen lassen:

## 1. Audit-Log (Hashkette)

Jede sicherheitsrelevante Aktion (Login, Workflow-Aenderungen, Datei-
Uploads, HTTP-Aufrufe, KI-Aufrufe, Secret-Aenderungen etc.) landet in
`audit_logs`. Jeder Eintrag enthaelt `prev_hash` und einen SHA-256-`hash`
ueber die Felder.

- Eintraege koennen ueber Eloquent NICHT modifiziert oder geloescht werden.
- Unter *Audit-Log -> Integritaetskette pruefen* wird die gesamte Kette
  neu berechnet — Manipulationen fuehren zu einem Bruch und werden
  exakt ausgewiesen (Stelle, erwarteter vs. gespeicherter Hash).
- IP/User-Agent werden via `audit:cleanup` (monatlich) nach 2 Jahren
  anonymisiert — ohne die Hashkette zu zerstoeren.

## 2. Workflow-Versionen (immutable)

Jedes Speichern im Designer erzeugt eine neue `workflow_version`.
Bestehende Versionen sind unveraenderbar (`updating` und `deleting`
werfen eine `RuntimeException`). Workflow-Instanzen referenzieren die
Version, mit der sie gestartet wurden — der Lauf bleibt nachvollziehbar,
auch wenn der Workflow danach geaendert wird.

## 3. Datei-Anhaenge mit SHA-256

Jeder Upload (Asset-Scan, Formular-Datei, Standalone-Dokument) bekommt
beim Speichern einen SHA-256-Hash in `attachments.content_hash`.

- `content_hash`, `path`, `size` sind immutable.
- **Soft-Delete**: einmal geloescht ist die Datei nicht physisch weg,
  sondern ausgeblendet. ForceDelete entfernt sie wirklich.
- Beim Asset-Workflow werden Anhaenge **kopiert** (eigene Datei mit
  eigenem Hash), damit Lebenszyklen von Asset und Instanz entkoppelt sind.
- Unter *Audit-Log -> Datei-Integritaet pruefen* werden alle Dateien
  geoeffnet, neu gehasht und verglichen. Verdaechtige Dateien werden
  namentlich gelistet.
- Im Audit-Log steht der Hash auch im Upload-Event — rueckverfolgbar,
  welche Datei zu welchem Zeitpunkt vorlag.

## 4. Dokumenten-Versionierung

Eine spaetere Version eines Dokuments wird **nicht** durch Ueberschreiben
erzeugt, sondern als neue Version in derselben Versions-Kette:

- `version_chain_id` (UUID) bleibt gleich
- `version_number` zaehlt v1, v2, v3, ...
- Alle Versionen bleiben dauerhaft erhalten
- Nur die aktuellste ist `is_current_version=true`
- Suche zeigt aktuelle Version; Detail-Seite zeigt alle Versionen

So bleibt nachvollziehbar, welche Fassung eines Vertrags wann gueltig war.

## Was das bedeutet

- Eine PDF-Manipulation auf der Platte wird durch *Datei-Integritaet
  pruefen* erkannt.
- Ein Versuch, eine Audit-Zeile in der DB zu aendern, bricht die Kette
  und wird durch *Audit-Kette pruefen* erkannt.
- Eine bereits gespeicherte Workflow-Version aendern, ist ueber das
  System nicht moeglich — nur Wiederherstellen einer alten Version als
  NEUE Version.
- Eine Dokument-Version ist nicht editierbar — neue Fassung = neue
  Version.

## Operativ

- Storage-Backup: `storage/app/attachments` regelmaessig sichern.
- DB-Backup einschliesslich `audit_logs`, `attachments`, `workflow_versions`.
- Beide Integritaetspruefungen koennen auch automatisiert per Cron
  laufen — Erweiterung um eine Console-Variante ist leicht moeglich.
