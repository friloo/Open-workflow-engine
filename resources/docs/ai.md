# KI im Workflow-Designer

Die OWE kann die KI an mehreren Stellen unterstuetzen.

## Voraussetzung

Unter *Systemeinstellungen -> KI-Integration* einen Anbieter hinterlegen:

- **OpenAI** (`https://api.openai.com/v1`, API-Key)
- **DeepSeek** (`https://api.deepseek.com/v1`, API-Key)
- **Ollama** lokal (`http://localhost:11434/v1`, ohne API-Key)
- **Custom** — jeden OpenAI-kompatiblen Endpunkt

Verbindungs-Test-Button prueft die Konfiguration.

## 1. Kompletten Workflow-Entwurf generieren

Im Workflow-Designer oben rechts gibt es den Button **„KI-Entwurf"**.

1. Beschreibe in Klartext, was der Workflow tun soll. Beispiel:

   > *Bestellantrag. Mitarbeiter fuellt Formular mit Kostenstelle, Beschreibung
   > und Betrag aus. Bei IT-Kostenstelle (1000) geht es zur IT-Leitung, bei
   > Office (2000) an den Office-Manager. Karenzzeit 3 Tage, eskaliert an
   > Admin-Rolle. Nach Genehmigung wird via HTTP-POST ein Ticket im Jira
   > (URL `example.atlassian.net/rest/api/3/issue`, Bearer-Token) erstellt
   > und der Antragsteller per Mail informiert. Bei Ablehnung Mail an den
   > Antragsteller mit dem Grund.*

2. Klick **Entwurf generieren**.
3. Die KI erzeugt:
    - Form-Schema mit passenden Feldern
    - Knoten (Start, Genehmigung, Bedingung, HTTP, Notify, Ende)
    - Edges/Verbindungen
4. Der Canvas wird ersetzt — du pruefst und passt an. **Erst Speichern legt eine Version an.**

> Tipp: Je konkreter du formulierst (Feldnamen, Bedingungswerte, Empfaenger),
> desto besser das Ergebnis.

## 2. HTTP-Knoten aus API-Beschreibung

Innerhalb des HTTP-Knotens gibt es eine eigene KI-Karte. Hier paste die
API-Doku, die KI baut Method, URL, Header, Auth-Type, Body-Template und
Response-Mapping passend zur Ziel-API.

## Was die KI weiss

Die KI kennt alle Knotentypen, alle Empfaenger-Typen (Vorgesetzte, Listen,
Rollen, Subject-User), alle Operatoren der Bedingungs-Knoten und alle
Mustache-Platzhalter (`{{ initiator_email }}`, `{{ subject_user_name }}`,
`{{ initiator_custom.kostenstelle }}` usw.).

## Datenschutz

Deine API-Beschreibungen und Workflow-Texte werden an den konfigurierten
Anbieter geschickt. Fuer maximale Datenhoheit nutze einen lokalen
Ollama-Server.
