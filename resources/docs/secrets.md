# Secrets-Vault

API-Tokens, Bearer-Keys und andere geheime Werte werden **nicht** in die
Workflow-Definition geschrieben, sondern zentral verschluesselt unter
*Verwaltung -> Secrets* abgelegt.

## Anlegen

Permission: `secrets.manage` (per Default bei Admin).

- **Key**: nur a-z, 0-9, Unterstrich. Beispiel: `jira_api_token`,
  `slack_webhook`, `s3_secret`.
- **Wert**: wird mit `Crypt::encryptString` (AES-256-CBC via Laravels
  APP_KEY) verschluesselt gespeichert.
- **Beschreibung**: nur zur Doku.

Per Rotate-Formular auf der Liste wird der Wert ueberschrieben — der alte
Wert ist danach nicht mehr verfuegbar.

## Verwendung

Im Workflow-Designer (HTTP-Knoten, Notify-Knoten, Bedingungen — ueberall
wo Platzhalter erlaubt sind) referenzierst du den Wert als:

```
@{{ secret.jira_api_token }}
```

Wird zur Laufzeit aufgeloest. Audit-Log dokumentiert **nicht** den Wert,
sondern nur dass der HTTP-Knoten gelaufen ist (Status, URL ohne Query).

## Best Practices

- Pro Umgebung eigene Tokens (kein „prod"-Token in einer Test-Workflow-
  Definition).
- Tokens mit minimal noetigen Scopes.
- Bei Mitarbeiter-Wechsel: Secret rotieren.
- Sensible Werte nicht in Body-Templates direkt eintragen — immer ueber
  den Vault.

## Audit-Log

Erfasst:
- `secret.created`
- `secret.updated`
- `secret.deleted`

Niemals der Wert selbst.
