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
| GET | `/api/v1/contracts` | `contracts.view` | Vertraege (visibleTo-Filter) |
| GET | `/api/v1/contracts/{id}` | `contracts.view` | Vertragsdetail + Anhaenge + Rollen |
| POST | `/api/v1/contracts` | `contracts.manage` | Vertrag anlegen |
| PATCH | `/api/v1/contracts/{id}` | `contracts.manage` | Vertrag aktualisieren |
| POST | `/api/v1/contracts/{id}/attachments` | `contracts.manage` | PDF/Datei am Vertrag anhaengen |
| GET | `/api/v1/cases` | `documents.search` | Akten-Liste |
| GET | `/api/v1/cases/{id}` | `documents.search` | Akten-Detail mit Inhalten |
| POST | `/api/v1/cases` | `documents.search` | Akte anlegen |
| POST | `/api/v1/cases/{id}/contracts` | `documents.search` | Vertrag an Akte heften |
| POST | `/api/v1/cases/{id}/workflow-instances` | `documents.search` | Vorgang an Akte heften |
| POST | `/api/v1/cases/{id}/notes` | `documents.search` | Notiz anlegen |
| GET | `/api/v1/reports/kpis` | `reports.view` | KPI-Snapshot fuer BI-Tools |
| GET | `/api/v1/audit-logs` | `audit.view` | Audit-Log lesen (Compliance/SIEM) |
| GET | `/api/v1/users` | `users.view` | Benutzerliste (Sync mit HR-System) |
| GET | `/api/v1/users/{id}` | `users.view` | User-Detail |
| GET | `/api/v1/lists` | `lists.view` | Alle Lookup-Listen |
| GET | `/api/v1/lists/{slug}/entries` | `lists.view` | Eintraege lesen |
| POST | `/api/v1/lists/{slug}/entries` | `lists.manage` | Eintrag upserten (z. B. Kostenstelle aus DATEV) |
| GET | `/api/v1/notifications` | — (eigene) | Eigene In-App-Notifications |
| POST | `/api/v1/notifications/{id}/read` | — (eigene) | Als gelesen markieren |
| POST | `/api/v1/notifications/read-all` | — (eigene) | Alle als gelesen |
| GET | `/api/v1/search?q=…` | — (interne ACL) | Globale Suche ueber alle Bereiche, permission-gehaertet |

Plus der oeffentliche Incoming-Webhook (ohne Token-Auth, aber mit
HMAC + Token in der URL):

| Methode | Pfad | Zweck |
|---|---|---|
| POST | `/api/incoming/{token}` | Workflow von extern triggern |

## Service-Accounts (Tokens fuer Maschinen)

Fuer System-zu-System-Integrationen (n8n, Power-BI, DATEV-Bridge,
externer Reporter, ...) ist es sauberer, einen dedizierten
**Service-Account** statt eines persoenlichen Tokens zu verwenden:

1. **Benutzer anlegen** unter **[Admin → Benutzer → Neu](app:admin.users.create)**:
   - Name: z. B. „API: n8n-Bridge", „Service: Power-BI"
   - E-Mail: `n8n@deine-firma.intern` (eindeutig, muss nicht echt sein)
   - Checkbox **„Service-Account"** anhaken
   - Rolle: nur was die Integration wirklich braucht
     (z. B. „BI-Reader" mit `reports.view` statt Admin)
2. **Token vergeben** auf der Benutzer-Detailseite ueber den Button
   „API-Tokens verwalten" (Admin-Endpoint:
   `/admin/users/{user}/tokens`). Kein Login als der Service-User
   noetig — der erstellende Admin sieht den Klartext-Token genau wie
   beim eigenen Token-Erstellen.
3. **Abilities einschraenken**: Selbst wenn der User mehr darf, kann
   der Token explizit nur eine Teilmenge.

Was der Service-Account-Flag bewirkt:

- Erscheint **nicht** in Empfaenger-Dropdowns (Approval-Knoten,
  Supervisor-Auswahl, Workflow-Forwarding, Vertrags-Owner)
- Wird **nicht** in der globalen Schnellsuche oder der `/api/v1/users`-
  Liste mitgeliefert (Override: `?include_service=1`)
- Erscheint in der Benutzer-Liste mit Badge „Service"

Was der Flag NICHT macht:

- Login bleibt grundsaetzlich moeglich — wenn du das verhindern willst,
  setze ein langes Zufalls-Passwort und/oder aktiviere 2FA im
  Service-User. Beides reduziert die Angriffsflaeche.
- Audit-Eintraege heissen weiterhin „von <Service-Username>" — das
  ist sogar ein Vorteil: du erkennst sofort, welche Integration was
  geaendert hat.

### Beispiel-Aufteilung

| Service-Account | Rolle | Abilities |
|----|----|----|
| `API: n8n-Bridge` | Workflow-Designer | `workflows.run`, `tasks.write`, `documents.write` |
| `Service: PowerBI` | BI-Reader (nur `reports.view`+`audit.view`) | `reports.view`, `audit.view` |
| `Service: DATEV-Sync` | Stammdaten-Pfleger | `lists.manage` |
| `API: ext. Vertragstool` | Vertragsverwalter | `contracts.view`, `contracts.manage` |

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
