# Genehmigung per Mail (signierter Link)

Empfaenger von Workflow-Aufgaben koennen direkt aus der E-Mail
entscheiden — **ohne Login**. Praktisch fuer Vorgesetzte, externe
Genehmiger oder Mobilgeraete.

## Was passiert

Die Mail „Neue Aufgabe" enthaelt zwei Buttons:

- **Genehmigen** (gruen)
- **Ablehnen** (rot)

Beide Links sind **signiert** und gelten nur fuer den jeweiligen
Empfaenger:

- Pro Rollen-Empfaenger wird eine eigene Mail mit eigenem Link
  verschickt — der Link traegt die User-ID des Adressaten.
- Laravel-`temporarySignedRoute`: HMAC-signiert, mit `expires`-
  Parameter. Manipulation -> 403.
- Default-Gueltigkeit: `due_at + 2 Tage`, sonst 14 Tage.

## Klick-Flow

1. Klick im Mail-Programm -> Bestaetigungs-Seite auf OWE.
2. Workflow-Name, Schritt, Antragsteller, Frist und ggf. Antragsdaten
   werden angezeigt.
3. Optional ein Kommentar eintragen, dann **Genehmigen** /
   **Ablehnen** klicken.
4. Der Schritt wird geschlossen; eine Erfolgs-Seite bestaetigt es.

Die Zwischenseite verhindert, dass Spam-/Vorschau-Scanner aus
Versehen genehmigen.

## Sicherheit

- Signatur-Validierung via Laravel `hasValidSignature()`.
- Step muss noch **offen** sein — bereits geschlossene -> 410.
- User muss dem Step zugewiesen sein (direkt oder ueber die Rolle).
- Inaktive Konten -> 403.
- Audit-Eintrag: `workflow.step.completed_via_mail` mit User, Decision
  und Step-ID.

## Limits

- **Forwarding** geht nicht per Mail-Link. Wer weiterleiten will,
  oeffnet die Aufgabe ueber den Login-Link in der Mail.
- Der Link funktioniert genau einmal — sobald der Schritt geschlossen
  ist, ergibt jeder weitere Aufruf 410.
