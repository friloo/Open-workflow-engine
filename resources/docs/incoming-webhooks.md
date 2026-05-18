# Eingehende Webhooks

Externe Systeme (Shop, Buchhaltung, Sensor, Zapier/Make-Szenario)
loesen einen Workflow ueber HTTP-POST aus.

## Anlegen

*Verwaltung -> Webhooks (in) -> Neuer Webhook.* Permission
`incoming_webhooks.manage`.

- **Name** — intern.
- **Workflow** — der Workflow, der ausgeloest wird.
- **HMAC-Secret** *(optional, dringend empfohlen)* — wenn gesetzt,
  muss der Aufrufer in jedem Request den Header
  `X-OWE-Signature: sha256=<HMAC-SHA256 des Bodys mit Secret>` senden.
  Sonst 401.
- **Field-Mapping** — wo im eingehenden JSON liegen die Werte, und in
  welches Formularfeld der Workflow-Instanz sollen sie wandern.
  Pfad ist Punktnotation (`order.customer.email`).

## Endpoint

Nach dem Speichern wird die URL angezeigt:

```
POST https://owe.example.com/api/incoming/<token>
Content-Type: application/json
X-OWE-Signature: sha256=<hex>   (wenn HMAC gesetzt)
```

Antwort `201`:

```json
{ "instance_id": 42, "status": "running", "current_step_key": "n1" }
```

## Beispiel mit curl

```bash
BODY='{"order":{"customer":{"email":"kunde@example.com"},"amount":49.99}}'
SIG=$(printf '%s' "$BODY" | openssl dgst -sha256 -hmac 'topsecret' | awk '{print $2}')
curl -X POST -H 'Content-Type: application/json' \
     -H "X-OWE-Signature: sha256=$SIG" \
     -d "$BODY" \
     https://owe.example.com/api/incoming/abc...123
```

## Mapping-Beispiele

Eingehender Payload:
```json
{ "order": { "customer": { "email": "x@y.de" }, "amount": 49.99 }, "ref": "ABC-1" }
```

Mappings:

| Pfad | Workflow-Feld |
|---|---|
| `order.customer.email` | `customer_email` |
| `order.amount` | `betrag` |
| `ref` | `referenz` |

Zusaetzlich: das **gesamte JSON** ist als `{{ webhook_payload }}`
verfuegbar — praktisch, wenn du im HTTP-Knoten den Original-Payload
weiterleiten willst.

## Token rotieren

Auf der Edit-Seite: **Token rotieren**. Erzeugt einen neuen Token,
alter wird sofort ungueltig.

## Audit + Statistik

- `incoming_webhook.created` / `.updated` / `.deleted` / `.token_rotated`
- `incoming_webhook.received` pro erfolgreichem Aufruf (mit
  `instance_id` und gemappten Feldern)
- Zaehler `call_count` und `failure_count` + `last_error` direkt am
  Webhook
