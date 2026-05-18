# PDF-Knoten

Erzeugt am Workflow-Ende ein PDF aus einem HTML-Template und haengt es
revisionssicher (SHA-256, Audit-Eintrag) an die Instanz an.

## Konfiguration

Im Designer den Knoten **PDF erzeugen** auf den Canvas ziehen.
Felder:

- **HTML-Template** — beliebiges HTML inkl. Inline-CSS in einem
  `<style>`-Tag. Platzhalter wie `{{ initiator_name }}`,
  `{{ workflow_name }}`, beliebige Formularfelder werden ersetzt.
- **Dateiname** — z. B. `beleg-{{ instance_id }}.pdf`. Falls die Endung
  `.pdf` fehlt, wird sie automatisch angehaengt.
- **Dokumenttyp** *(optional)* — landet im Attachment-Datensatz,
  sodass es z. B. fuer Rollen-Berechtigungen filterbar ist.
- **Bezeichnung** *(optional)* — zusaetzliches Label fuer das Attachment.

## Beispiel

```html
<style>
  body { font-family: sans-serif; }
  h1 { color: #4f46e5; }
</style>
<h1>Antrag #{{ instance_id }}</h1>
<p>Antragsteller: {{ initiator_name }} ({{ initiator_email }})</p>
<p>Kostenstelle: {{ kostenstelle }}</p>
<p>Datum: {{ instance_started_at }}</p>
```

## Verfuegbare Felder nach Generierung

Im weiteren Workflow stehen:

- `{{ pdf.last_attachment_id }}` — Datenbank-ID
- `{{ pdf.last_filename }}` — Datei-Name
- `{{ pdf.last_hash }}` — SHA-256

Beispiel: an einen HTTP-Knoten danach senden:

```json
{ "attachment_id": "{{ pdf.last_attachment_id }}", "sha256": "{{ pdf.last_hash }}" }
```

## Audit

- `workflow.pdf.generated` — bei jedem Erfolg, mit SHA und Dateiname
- `workflow.pdf.failed` — bei leerem Template oder Render-Fehler
