# Benachrichtigungen anpassen

Jeder OWE-Event (neue Aufgabe, Erinnerung, abgeschlossener Vorgang …)
kann pro User und pro Kanal individuell angeschaltet oder unterdrueckt
werden. Standard fuer alle: **alles an**. Wer nichts aendert, bekommt
weiter alle Benachrichtigungen wie bisher.

## Wo einstellen?

[Profil → Benachrichtigungen](app:profile.edit). Eine Tabelle mit:

- **Spalten** = Kanaele (In-App via Glocken-Icon / E-Mail)
- **Zeilen** = Event-Typen

Ein Klick auf die Checkbox aktiviert / deaktiviert diese Kombination.

## Welche Ereignisse?

| Event | Wann? |
|---|---|
| Aufgabe zugewiesen | Wenn dir oder einer deiner Rollen eine neue Aufgabe zukommt. |
| Erinnerung an offene Aufgabe | Wenn eine Aufgabe ueberfaellig ist oder kurz vor der Frist. |
| Eigene Aufgabe abgeschlossen | Bestaetigung dass deine Entscheidung gespeichert ist. |
| Aufgabe eskaliert | Wenn die Karenzzeit ablaeuft und die Aufgabe an einen anderen geht. |
| Vorgang abgeschlossen | Wenn ein von dir gestarteter Workflow durchgelaufen ist. |
| Vorgang fehlgeschlagen | Wenn ein von dir gestarteter Workflow mit Fehler stehen bleibt. |
| Sharing-Link erstellt | Wenn jemand einen oeffentlichen Freigabe-Link zu einem Dokument erstellt. |
| Erwaehnung in Kommentar | Wenn du in einem Vorgangs-Kommentar mit @ erwaehnt wirst (Threading-Feature in Planung). |

## Master-Switch fuer Mails

Im Profil gibt's zusaetzlich den Schalter „**Mails dieser Anwendung**".
Wenn der aus ist, bekommst du **keine** Mails — egal was in der Matrix
oben steht. Praktisch fuer Urlaub: einmal aus, alle Mail-Haken bleiben
erhalten, kommen automatisch zurueck wenn du den Master wieder anhakst.

## Empfehlungen

- **Genehmiger / Vorgesetzte**: Mail + In-App fuer „Aufgabe zugewiesen"
  und „Erinnerung". Rest: nur In-App.
- **Antragsteller**: Mail nur fuer „Vorgang abgeschlossen / fehlgeschlagen".
  Alles andere stoert nur.
- **Admins**: alles an. Bei viel Workload den Mail-Master abschalten und
  nur die Glocke nutzen.

## Was OWE *nicht* macht

- **Daily-Digest**: aktuell schickt OWE eine Mail pro Event. Bei viel
  Volumen empfehlen wir den Mail-Master aus und stattdessen die In-App-
  Glocke. Echter Digest (eine Sammel-Mail morgens um 8) ist als Feature
  in Planung.
- **Web-Push**: Push direkt aufs Handy via Browser-Notification API ist
  in Planung als Teil des PWA-Features.
- **Slack/Teams pro User**: aktuell nur Channel-weite Teams-Notifications
  via Webhook (siehe [Teams-Anleitung](app:help.show?topic=teams)).
