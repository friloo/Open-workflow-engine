# API-Tokens

OWE bietet eine schlanke JSON-API mit Token-Authentifizierung. Tokens
sind **persoenliche** Zugangsmittel: was der zugehoerige Benutzer darf,
darf auch der Token — nie mehr.

## Erstellen

*Topbar -> Profil-Menue -> API-Tokens*

- **Name** — was nutzt den Token? (Bsp. „n8n-Bridge")
- **Berechtigungen** — optional einschraenken. Leer = alle Permissions
  des Benutzers stehen offen.
- **Ablauf (Tage)** — optional. Empfehlung: ja, ablaufen lassen.

Beim Anlegen wird der Klartext **einmalig** angezeigt — z. B.
`owe_abcdef0123456789...`. Danach wird nur noch der SHA-256-Hash in
der DB gespeichert; wir koennen den Token nicht wiederherstellen.

## Verwendung

Header pro Request:

```
Authorization: Bearer owe_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

Endpunkte unter `/api/v1`:

- `GET  /api/v1/me` — Selbstauskunft (Benutzer, Rollen, Token-Berechtigungen)
- `POST /api/v1/workflows/{workflow}/start` — Workflow-Instanz starten
  - Body: `{ "data": { ...Formularfelder... } }`
  - Ability: `workflows.run`

Beispiel:

```bash
curl -X POST \
  -H 'Authorization: Bearer owe_...' \
  -H 'Content-Type: application/json' \
  -d '{"data": {"kostenstelle": "K-001", "betrag": 250}}' \
  https://owe.example.com/api/v1/workflows/3/start
```

Antwort:

```json
{ "instance_id": 42, "status": "running", "current_step_key": "abc-1" }
```

## Widerrufen

In der Tabelle unter *API-Tokens* den Button **Widerrufen** klicken.
Der Token kann ab sofort nicht mehr genutzt werden.

## Audit

- `api_token.created` — beim Anlegen, ohne Klartext, nur Prefix +
  Berechtigungen
- `api_token.revoked` — beim Widerrufen
