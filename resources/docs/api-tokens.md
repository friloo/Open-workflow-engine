# API-Tokens & externe Zugriffe

OWE bietet eine **REST-API** mit Token-Auth, damit andere Systeme
(n8n, Zapier, Eigenbau-Scripte, Mobile-Apps, Buchhaltungssoftware)
Dokumente auslesen, Aufgaben bearbeiten, Workflows starten oder
Vorgaenge abfragen koennen.

> [!TIP]
> Die vollstaendige API-Referenz mit Swagger-UI findest du unter
> [/admin/api-docs](app:admin.api_docs.index) (nur Admins).
> Hier auf dieser Seite der Schnelleinstieg.

## Token anlegen

In **Profil → API-Tokens** (`/tokens`):

1. „Neues Token" anklicken
2. Namen vergeben (z. B. „n8n Integration")
3. **Abilities** auswaehlen — welche Endpoints darf das Token nutzen:
   - `workflows.read` — Workflows + Instances lesen
   - `workflows.run` — Workflow starten
   - `tasks.read` — eigene Aufgaben listen
   - `tasks.write` — Entscheidungen treffen
   - `documents.read` — Dokumente suchen + lesen + download
   - `documents.write` — Upload + Indexfeld-Aenderungen
4. Optional Ablauf-Datum setzen.
5. Speichern → Token wird **einmalig** angezeigt (kopieren!).

Format: `owe_XXXXXXXXXXXXXXXXXX…`

> [!WARNING]
> Der Token wird nur als SHA-256-Hash in der DB gespeichert — wir
> koennen ihn nicht wiederherstellen. Verloren = neuen anlegen.

## Authentifizierung

Header bei jedem Request:

```
Authorization: Bearer owe_XXXXXXXXXXXXXXXXXX…
Accept: application/json
```

## Endpunkte (Schnellreferenz)

| Methode | Pfad | Ability | Zweck |
|---|---|---|---|
| GET | `/api/v1/me` | — | Wer bin ich + Token-Info |
| GET | `/api/v1/workflows` | `workflows.read` | Aktive Workflows listen |
| POST | `/api/v1/workflows/{id}/start` | `workflows.run` | Workflow starten |
| GET | `/api/v1/workflow-instances` | `workflows.read` | Eigene Vorgaenge |
| GET | `/api/v1/workflow-instances/{id}` | `workflows.read` | Vorgang-Detail mit Schritten |
| GET | `/api/v1/tasks` | `tasks.read` | Eigene offene Aufgaben |
| POST | `/api/v1/tasks/{step}/decide` | `tasks.write` | Entscheidung treffen |
| GET | `/api/v1/documents` | `documents.read` | Dokument-Suche |
| GET | `/api/v1/documents/{id}` | `documents.read` | Metadata + Indexfelder |
| GET | `/api/v1/documents/{id}/download` | `documents.read` | Datei-Stream |
| POST | `/api/v1/documents` | `documents.write` | Multipart-Upload |
| PATCH | `/api/v1/documents/{id}` | `documents.write` | Indexfelder / Label / Typ updaten |

Plus der oeffentliche Incoming-Webhook (ohne Token-Auth, aber mit
HMAC + Token in der URL):

| Methode | Pfad | Zweck |
|---|---|---|
| POST | `/api/incoming/{token}` | Workflow von extern triggern |

## Beispiele

### Aufgabe genehmigen via API

```bash
# Eigene offene Aufgaben listen
curl https://owe.example.org/api/v1/tasks \
  -H "Authorization: Bearer owe_…" \
  -H "Accept: application/json"

# Antwort:
# { "data": [{ "id": 42, "label": "Genehmigung", ... }], "count": 1 }

# Aufgabe 42 genehmigen
curl -X POST https://owe.example.org/api/v1/tasks/42/decide \
  -H "Authorization: Bearer owe_…" \
  -H "Content-Type: application/json" \
  -d '{"decision":"approved","comment":"Sachlich richtig."}'
```

### Dokument hochladen

```bash
curl -X POST https://owe.example.org/api/v1/documents \
  -H "Authorization: Bearer owe_…" \
  -F "file=@rechnung.pdf" \
  -F "document_type=Rechnung" \
  -F "label=Mueller GmbH"
```

Bei Duplikat (gleicher SHA-256 schon vorhanden) bekommst du HTTP 409:

```json
{
  "error": "duplicate",
  "message": "Datei bereits hochgeladen am 19.05.2026 14:32 von Anna.",
  "original_id": 4711
}
```

### Indexfeld nachpflegen

```bash
curl -X PATCH https://owe.example.org/api/v1/documents/4711 \
  -H "Authorization: Bearer owe_…" \
  -H "Content-Type: application/json" \
  -d '{"indexed_fields":{"kostenstelle":"K-100","bemerkung":"manuell korrigiert"}}'
```

### Workflow starten + Status pollen

```bash
# Workflow starten
INSTANCE_ID=$(curl -s -X POST https://owe.example.org/api/v1/workflows/7/start \
  -H "Authorization: Bearer owe_…" \
  -H "Content-Type: application/json" \
  -d '{"data":{"kostenstelle":"K-100","betrag":"1234.50"}}' \
  | jq -r '.instance_id')

# Status pollen
curl https://owe.example.org/api/v1/workflow-instances/$INSTANCE_ID \
  -H "Authorization: Bearer owe_…"
```

## Rate-Limits + Sicherheit

- Tokens koennen einzeln widerrufen werden (Profil → API-Tokens →
  „Widerrufen") — Sofort-Wirkung.
- Pro Token sind **Abilities** harte Grenzen: ein `documents.read`-Token
  kann nichts schreiben, selbst wenn es die API-Endpoints kennt.
- Permission-Checks identisch zum Web-UI: ein Token kann nur, was der
  zugehoerige User auch im Browser duerfte. Doku-Type-Permissions
  greifen genauso.
- [Anonymisierung eines Users](app:help.show?topic=dsgvo) loescht alle
  seine Tokens automatisch.

## Audit

- `api_token.created` — beim Anlegen, ohne Klartext, nur Prefix +
  Abilities
- `api_token.revoked` — beim Widerrufen
- `api.document.uploaded` / `api.document.updated` — pro Upload bzw.
  PATCH mit Token-Name

## OpenAPI-Spec exportieren

Unter [/admin/api-docs/openapi.yaml](app:admin.api_docs.spec) findest du
die komplette Spec im YAML-Format — kannst du in Postman/Insomnia
importieren und sofort alle Endpoints durchklicken.
