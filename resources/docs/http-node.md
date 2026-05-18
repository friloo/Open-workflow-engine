# HTTP-Knoten + KI-Assistenz

Mit dem HTTP-Knoten rufst du externe APIs auf — typisches Beispiel:
am Ende eines Bestell-Workflows automatisch ein IT-Ticket im
Ticketsystem anlegen.

## Konfigurations-Felder

- **Methode**: GET, POST, PUT, PATCH, DELETE
- **URL**: vollstaendige URL, Platzhalter erlaubt (`{{ instance_id }}`)
- **Authentifizierung**:
    - *Keine*
    - *Bearer-Token* (z. B. `Authorization: Bearer xxxx`)
    - *Basic-Auth* (Username + Passwort)
    - *API-Key Header* (frei waehlbarer Header-Name)
- **Header**: beliebig viele Key/Value-Paare, Platzhalter erlaubt
- **Body-Typ**:
    - *Kein Body*
    - *JSON* — frei editierbar, du schreibst genau das Format, das die Ziel-API erwartet
    - *Form* (x-www-form-urlencoded) als Key/Value-Liste
    - *Raw* (du setzt Content-Type via Header selbst, z. B. fuer XML)
- **Response-Mapping**: Pfade aus der JSON-Antwort uebernehmen
    (`data.id` → `ticket_id`), spaeter via `{{ ticket_id }}` weiter nutzbar
- **Timeout** (Sekunden)
- **Bei Fehler weiterleiten** — Workflow folgt dem Fehler-Ausgang statt zu scheitern

## Ausgaenge

- **OK** — HTTP 2xx
- **Fehler** — HTTP 4xx/5xx oder Netzwerkfehler

Damit kannst du z. B. nach einer Genehmigung am IT-Endpunkt das Ticket
anlegen und bei Erfolg den Initiator informieren, sonst eine
Eskalations-Mail an den Admin senden.

## KI-Assistenz

Wenn unter *Systemeinstellungen -> KI* ein Provider hinterlegt ist
(OpenAI, DeepSeek oder lokales Ollama), erscheint im HTTP-Knoten die
violette Karte **"KI-Vorschlag aus API-Beschreibung"**.

1. Beschreibe in eigenen Worten oder paste die Doku der Ziel-API:
   > *POST an https://example.atlassian.net/rest/api/3/issue mit
   > Bearer-Token. JSON-Body mit `fields.project.key='IT'`,
   > `fields.summary` aus Antragstitel, `fields.description` aus dem
   > Formular-Feld `beschreibung`. Antwort enthaelt `key` als Ticket-ID.*
2. Klick **Generieren**.
3. Die KI fuellt URL, Methode, Header, Body und Response-Mapping aus —
   du kontrollierst und passt an, falls noetig.

## Platzhalter

Im Body, in URL, in Headern und in Auth-Tokens kannst du Mustache-
Platzhalter nutzen:

- `{{ initiator_email }}`, `{{ initiator_name }}`
- `{{ subject_user_email }}`, `{{ subject_user_name }}` — bei Asset-Workflows der Inhaber
- `{{ initiator_custom.kostenstelle }}` — benutzerdefinierte Felder
- `{{ instance_id }}`, `{{ workflow_name }}`
- Beliebige Formularfeld-Keys: `{{ betrag }}`, `{{ kategorie }}`, ...
- Felder, die ein vorheriger HTTP-Knoten gemappt hat: `{{ ticket_id }}`

## Sicherheit

API-Keys und Bearer-Tokens stehen in der Workflow-Definition. Wenn du sie
nicht hart codieren willst, lege sie in den Systemeinstellungen ab und
nutze einen Platzhalter, oder verwende eine M365-/Azure-Identitaet ueber
eine vorgeschaltete Authentifizierung im HTTP-Knoten.

Audit-Log protokolliert jeden HTTP-Aufruf (URL, Methode, Status) ohne
Body-Inhalt.
