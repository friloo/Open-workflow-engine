# Akten (Aktendeckel / E-Akten)

Eine **Akte** ist ein virtueller Aktendeckel — sie buendelt
zusammengehoerige **Dokumente**, **Workflow-Vorgaenge**, **Vertraege**
und **Notizen** an einer Stelle.

> Zugriff: **[Stammdaten → Akten](app:cases.index)**

## Klassische Anwendungsfaelle

- **Personalakte „Mueller"** — Arbeitsvertrag, Krankmeldungen,
  Urlaubsantraege (als Vorgaenge), Bewerbungs-Notizen
- **Kundenakte „Brunner GmbH"** — Angebote, Auftragsbestaetigungen,
  Lieferscheine, Reklamations-Vorgaenge, laufende Wartungsvertraege
- **Projektakte „Webshop-Relaunch"** — alle Belege + Genehmigungen +
  externe Vertraege + Meeting-Notizen
- **Immobilienakte „Schillerstr. 12"** — Mietvertraege, Wartungen,
  Schadensmeldungen

## Was alles in eine Akte gehoert

### Dokumente
Dokumente werden via **Bulk-Aktion** in der Dokumenten-Liste zu einer
Akte hinzugefuegt — mehrere markieren, „Zu Akte" waehlen. Ein Dokument
kann gleichzeitig in mehreren Akten liegen.

### Workflow-Vorgaenge
Auf der Akten-Detailseite kannst du die ID eines bestehenden
Workflow-Vorgangs eintragen, um den an die Akte zu heften. So siehst
du z. B. alle Urlaubsantraege eines Mitarbeiters in seiner
Personalakte.

### Vertraege
Vertraege aus dem **[Vertragsmanagement](app:help.show?topic=contracts)**
koennen ebenfalls per ID an die Akte gehaengt werden. So liegt der
laufende Wartungsvertrag direkt im Aktendeckel der Immobilie.

### Notizen
Freitext-Anmerkungen mit Verfasser + Zeitstempel — fuer alles, was
nicht in ein Dokument oder Vorgang gehoert (Telefon-Notizen,
interne Hinweise, Status-Updates).

## Schliessen vs. Loeschen

- **Schliessen**: Akte bleibt sichtbar, ist aber nicht mehr aktiv —
  z. B. „Personalakte geschlossen am 31.12.2026 (Mitarbeiter
  ausgeschieden)".
- **Loeschen**: Akte selbst weg, aber alle enthaltenen Dokumente,
  Vorgaenge und Vertraege bleiben erhalten — nur die Zuordnung
  loest sich auf.

## Audit

- `case.created` / `case.updated` / `case.deleted`
- `case.workflow_attached` / `case.contract_attached`

Notizen werden nicht im globalen Audit-Log gefuehrt — sie sind als
Akten-interne Konversation gedacht, nicht als Audit-relevant.

## Sichtbarkeit

Akten haben kein eigenes Permission-Modell — wer die Akte sehen darf,
sieht alles drin (Dokumente unterliegen ihrer eigenen
Document-Type-Permission, die unabhaengig wirkt). Wenn du Akten
streng pro Abteilung trennen willst: nimm beredte Namen
(„HR: Mueller", „IT: Server-Wartung") und schule entsprechend.
