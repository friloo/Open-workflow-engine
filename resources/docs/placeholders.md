# Platzhalter-Referenz

Mustache-Platzhalter `{{ name }}` koennen in:

- E-Mail-Subject und -Body
- HTTP-Knoten (URL, Headers, Auth-Tokens, Body, Form-Felder)

genutzt werden.

## Verfuegbare Platzhalter

### Instanz

- `{{ instance_id }}` — interne ID des Vorgangs
- `{{ instance_started_at }}` — ISO-8601-Startzeit
- `{{ workflow_name }}`

### Antragsteller

- `{{ initiator }}`, `{{ initiator_name }}`
- `{{ initiator_email }}`
- `{{ initiator_custom.<key> }}` — benutzerdefinierte Felder

### Subject-User (z. B. bei Asset-Workflows der Inhaber)

- `{{ subject_user_name }}`
- `{{ subject_user_email }}`
- `{{ subject_user_custom.<key> }}`

### Asset-Workflows zusaetzlich

- `{{ asset_id }}`, `{{ asset_name }}`, `{{ asset_type }}`
- `{{ asset_valid_until }}`

### Formularfelder

Alle Keys aus dem Form-Schema sind direkt verfuegbar:

- `{{ kostenstelle }}`, `{{ betrag }}`, `{{ beschreibung }}`, ...

### Werte aus vorherigen HTTP-Knoten

Per Response-Mapping zurueckgeschriebene Felder sind ab dem Folgeknoten
nutzbar:

- `{{ ticket_id }}`, `{{ ticket_url }}`, ...

## Punktnotation

Geschachtelte Pfade in Objekten/Arrays sind via Punkt erreichbar:
`{{ initiator_custom.kostenstelle }}`,
`{{ subject_user_custom.standort }}`.

## Fehlende Werte

Fehlt ein Platzhalter im Kontext, wird er durch einen leeren String
ersetzt.
