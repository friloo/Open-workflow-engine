# Workflows entwerfen

## Knotentypen

| Knoten | Zweck | Ausgaenge |
|--------|-------|-----------|
| **Start** | Einstiegspunkt | 1 |
| **Genehmigung** | Aufgabe an Person/Rolle | Genehmigt / Abgelehnt / (Weitergeleitet) |
| **Bedingung** | Verzweigt nach Formularwerten | beliebig viele + Else |
| **HTTP-Request** | Externe API aufrufen | OK / Fehler |
| **Benachrichtigung** | E-Mail senden | 1 |
| **Ende** | Beendet die Instanz | 0 |

## Empfaenger-Typen

- **Vorgesetzter des Antragstellers** — der Chef der Person, die das Formular ausgefuellt hat.
- **Vorgesetzter des vorherigen Bearbeiters** — Kette aufwaerts.
- **Asset-Inhaber / Subject-User** — bei wiederkehrenden Workflows (Fuehrerschein) die Person, um die es geht.
- **Vorgesetzter des Subject-Users**
- **Rolle** — alle Mitglieder einer Rolle.
- **Konkrete Person** — fester Benutzer.
- **Aus Liste nachschlagen** — Schluessel aus einem Formularfeld in einer Lookup-Liste; Engine zieht die Verantwortlich-E-Mail.

## KI-Entwurf

Oben im Designer gibt es den Button **„KI-Entwurf"**. Beschreibe in
eigenen Worten, was der Workflow tun soll — die KI generiert ein
komplettes Geruest (Formularfelder, Knoten, Verbindungen). Du pruefst
und passt an. Erst Klick auf **Speichern** legt eine Version an.

Details siehe Abschnitt *KI im Designer*.

## Karenzzeit und Eskalation

Pro Genehmigungs-Knoten konfigurierst du eine Karenzzeit (Stunden, Tage, Monate).
Vor Ablauf bekommt der Empfaenger eine Erinnerungsmail (24 Stunden vorher).
Wird nach Ablauf nicht reagiert, eskaliert die Aufgabe an die konfigurierte
Eskalations-Person/-Rolle/-Liste.

## Versionierung

Jeder Klick auf **Speichern** legt eine neue, unveraenderbare Version an.
Aelter Versionen koennen unter *Versionen* wiederhergestellt werden — dabei
entsteht erneut eine neue Version.

## Aktivierung

Solange ein Workflow im Status *Entwurf* ist, kann er nicht gestartet werden.
Erst nach Klick auf **Aktivieren** ist er ueber das Formular erreichbar.
