# Webhooks (outgoing)

Webhooks rufen externe Systeme auf, wenn Workflow-Ereignisse eintreten.

## Konfiguration

Unter *Verwaltung -> Webhooks*:

- **Name**, **URL**
- **Ereignisse** (mehrere moeglich):
    - `instance.started`
    - `instance.completed`
    - `instance.cancelled`
    - `instance.failed`
    - `step.completed`
    - `step.escalated`
- **Zusatz-Header** beliebig (z. B. fuer Tokens)
- **HMAC-Secret** (optional) — Header `X-OWE-Signature: sha256=<hex>` ueber dem Body

## Payload

```json
{
  "event": "instance.completed",
  "timestamp": "2026-05-21T08:00:00+02:00",
  "instance": {
    "id": 42,
    "status": "completed",
    "workflow_id": 7,
    "workflow_name": "Bestellantrag",
    "started_by": "max@firma.de",
    "data": { "kostenstelle": "1000", "betrag": 250 }
  }
}
```

Bei `step.completed` enthaelt `extra` die Step-Referenz und Entscheidung.

## Signaturpruefung im Empfaenger

```php
$expected = 'sha256=' . hash_hmac('sha256', $rawBody, $secret);
hash_equals($expected, $request->header('X-OWE-Signature'));
```

## Tests

Pro Webhook gibt es einen *Test*-Button, der einen Probe-Aufruf macht.
