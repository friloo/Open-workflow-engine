# Workflow-Vorlagen (Import / Export)

Fertige Workflows lassen sich als JSON aus dem System exportieren und in
andere Instanzen — oder einfach in eine Kopie — importieren. Drei
Vorlagen sind eingebaut.

## Eingebaute Vorlagen

*Workflows -> Vorlagen.* Permission `workflows.design`.

- **Rechnungseingang** — Bedingung auf `doc.indexed_fields.kostenstelle`,
  Lookup-Empfaenger zum Kostenstellen-Verantwortlichen mit Fallback an
  Buchhaltung. Setup-Vollbild siehe Cookbook.
- **Urlaubsantrag** — Formular -> Genehmigung Vorgesetzter -> HR-Mail
  bzw. Antragsteller-Mail bei Ablehnung.
- **Bestellfreigabe** — Vorgesetzten-Genehmigung; bei Betraegen > 1000 EUR
  zusaetzliche Freigabe durch Einkauf.

Nach **Importieren** landest du im Designer mit einem **Entwurf**.
Dort bitte:

1. Empfaenger korrigieren (Rolle setzen, Lookup-Liste zuordnen,
   Fallback-Rolle waehlen).
2. Optional Formularfelder anpassen.
3. **Speichern**, dann **Aktivieren**.

## Eigene Vorlage exportieren

Auf der Edit-Seite eines Workflows: **Als Vorlage exportieren**. Lade
das JSON herunter — die aktuelle Workflow-Version (Definition +
Form-Schema) ist enthalten.

Format:

```json
{
  "owe_workflow_template": 1,
  "name": "...",
  "description": "...",
  "trigger_type": "form|manual|recurring",
  "definition": { "drawflow": { "Home": { "data": { ... } } } },
  "form_schema": [ ... ],
  "exported_at": "...",
  "exported_from_version": 3
}
```

## Import

*Workflows -> Vorlagen -> Eigene Vorlage importieren.* Drei Quellen:

- **Datei** — JSON hochladen.
- **Einfuegen** — JSON in ein Textfeld kleben.
- **Eingebaute** — direkt aus der Liste.

Optional kannst du beim Import einen anderen Namen vergeben.

## Was wird NICHT mit-exportiert

- **Lookup-Listen** (`list_id` ist im Template `null` — nach Import in
  jedem Lookup-Knoten neu auswaehlen).
- **Rollen / Benutzer** (`recipient_role_id`, `recipient_user_id`,
  `fallback_role_id`, `fallback_user_id` sind `null`).
- Workflow-Schedules, Webhooks, Form-Bindings.
- Audit-Log, Instanzen.

Ziel ist eine portable Vorlage, die in jeder Instanz funktioniert —
ohne falsche IDs zu uebernehmen.

## Audit

- `workflow.imported` — beim Import, mit Quelle (`builtin`/`upload`/`paste`).
