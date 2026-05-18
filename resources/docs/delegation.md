# Vertretungsregelung

Jeder Benutzer kann in seinem Profil eine Vertretung mit Zeitraum
hinterlegen. Solange die Vertretung aktiv ist, werden **neue**
Workflow-Aufgaben automatisch an die Vertretung adressiert.

## Einrichten

*Topbar -> Profil -> Vertretung*

- **Vertretung durch** — beliebiger aktiver Benutzer (nicht man selbst).
- **Von / Bis** — Datumsbereich inklusive. Mit beidem leer ist keine
  Vertretung aktiv.
- **Grund** — frei, intern. Wird im Audit-Log mit gespeichert.

Mit **Vertretung beenden** wird die Regel sofort geloescht.

## Was ist betroffen

- **Neue Aufgaben** (`approval`-Knoten) — wenn der Empfaenger ein
  konkreter Benutzer ist und an dem Tag eine Vertretung gilt, wird
  die Aufgabe direkt der Vertretung zugeordnet.
- **Bereits offene Aufgaben** bleiben unberuehrt. Wer noch offene
  Aufgaben vor dem Urlaub abgeben moechte, nutzt im Aufgaben-Bereich
  den Button **Weiterleiten** — wie gehabt.
- **Rollen-Empfaenger** sind nicht betroffen: ist eine Aufgabe an
  z. B. „Buchhaltung" adressiert, koennen alle anderen Buchhalter
  die Aufgabe weiterhin annehmen.

## Ketten und Zyklen

Wenn die Vertretung selbst vertreten ist, folgen wir der Kette bis zu
drei Schritte. Zyklen (A vertritt B, B vertritt A) werden erkannt und
abgebrochen.

## Audit

- `user.delegate.updated` — Vertretung gesetzt/geaendert
- `user.delegate.cleared` — manuell beendet
- `workflow.task.delegated` — pro umgeleiteter Aufgabe, mit Original-
  und Vertretungs-Adresse
