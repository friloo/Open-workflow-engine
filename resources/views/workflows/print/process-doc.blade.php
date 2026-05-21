<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Prozessbeschreibung: {{ $workflow->name }}</title>
    <style>
        @page { margin: 20mm 18mm 18mm 18mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #1f2937; line-height: 1.45; }
        h1 { font-size: 18pt; margin: 0 0 6px 0; color: #111827; }
        h2 { font-size: 12pt; margin: 18px 0 6px 0; color: #1f2937; border-bottom: 1px solid #d1d5db; padding-bottom: 3px; }
        h3 { font-size: 10pt; margin: 12px 0 4px 0; color: #374151; }
        p { margin: 0 0 8px 0; }
        .meta { font-size: 9pt; color: #6b7280; }
        .badge { display: inline-block; padding: 1px 6px; border-radius: 3px; font-size: 8pt; font-weight: bold; }
        .badge-active { background: #d1fae5; color: #065f46; }
        .badge-draft  { background: #fef3c7; color: #92400e; }
        .badge-arch   { background: #e5e7eb; color: #4b5563; }
        table { width: 100%; border-collapse: collapse; margin: 6px 0 10px 0; }
        th { background: #f3f4f6; text-align: left; padding: 5px 6px; font-size: 9pt; border: 1px solid #d1d5db; }
        td { padding: 5px 6px; border: 1px solid #d1d5db; font-size: 9pt; vertical-align: top; }
        td.idx { width: 18px; color: #6b7280; }
        .node-box { border: 1px solid #d1d5db; padding: 8px 10px; margin: 6px 0; background: #fafafa; }
        .node-head { font-weight: bold; font-size: 10pt; }
        .node-type { color: #6366f1; font-size: 8.5pt; text-transform: uppercase; letter-spacing: 0.05em; }
        .kv { width: 100%; margin: 4px 0; }
        .kv td { border: 0; padding: 1px 6px 1px 0; font-size: 9pt; }
        .kv td.k { color: #6b7280; width: 32%; }
        .kv td.v { color: #111827; }
        code { font-family: DejaVu Sans Mono, monospace; background: #f3f4f6; padding: 1px 4px; border-radius: 2px; font-size: 9pt; }
        pre { background: #1f2937; color: #f9fafb; font-family: DejaVu Sans Mono, monospace; padding: 6px 8px; font-size: 8pt; white-space: pre-wrap; border-radius: 3px; }
        .footer { position: fixed; bottom: 8mm; left: 18mm; right: 18mm; font-size: 8pt; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 4px; }
        .footer .hash { font-family: DejaVu Sans Mono, monospace; }
        .empty { color: #9ca3af; font-style: italic; }
        .row-pair { display: table; width: 100%; }
        .row-pair > div { display: table-cell; width: 50%; vertical-align: top; padding-right: 8px; }
    </style>
</head>
<body>

<h1>Prozessbeschreibung</h1>
<p class="meta">
    <strong>{{ $workflow->name }}</strong>
    @switch($workflow->status)
        @case('active') <span class="badge badge-active">aktiv</span> @break
        @case('draft') <span class="badge badge-draft">Entwurf</span> @break
        @default <span class="badge badge-arch">{{ $workflow->status }}</span>
    @endswitch
    <br>Version {{ $version->version_number }} · erstellt {{ $created_at?->format('d.m.Y') }} von {{ $created_by }}
</p>

@if($workflow->description)
    <p>{{ $workflow->description }}</p>
@endif

<h2>1. Allgemein</h2>
<table class="kv">
    <tr><td class="k">Workflow-ID</td><td class="v">#{{ $workflow->id }}</td></tr>
    <tr><td class="k">Trigger</td><td class="v">{{ $trigger_label }}</td></tr>
    <tr><td class="k">Status</td><td class="v">{{ $workflow->status }}</td></tr>
    <tr><td class="k">Aktive Version</td><td class="v">v{{ $version->version_number }}</td></tr>
    <tr><td class="k">Knoten</td><td class="v">{{ count($nodes) }}</td></tr>
</table>

@if(! empty($form_schema))
    <h2>2. Antrags-Formular</h2>
    <table>
        <thead>
            <tr><th>Schlüssel</th><th>Bezeichnung</th><th>Typ</th><th>Pflicht</th></tr>
        </thead>
        <tbody>
            @foreach($form_schema as $f)
                <tr>
                    <td><code>{{ $f['key'] ?? '?' }}</code></td>
                    <td>{{ $f['label'] ?? $f['key'] ?? '' }}</td>
                    <td>{{ $f['type'] ?? 'text' }}</td>
                    <td>{{ ! empty($f['required']) ? 'ja' : 'nein' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

<h2>{{ ! empty($form_schema) ? '3' : '2' }}. Ablauf-Übersicht</h2>
<table>
    <thead>
        <tr><th class="idx">#</th><th>Knoten</th><th>Typ</th><th>Folgt</th></tr>
    </thead>
    <tbody>
        @foreach($nodes_summary as $i => $s)
            <tr>
                <td class="idx">{{ $i + 1 }}</td>
                <td><strong>{{ $s['label'] }}</strong> <span class="meta">(#{{ $s['id'] }})</span></td>
                <td>{{ $s['type_human'] }}</td>
                <td>{{ $s['follows'] }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<h2>{{ ! empty($form_schema) ? '4' : '3' }}. Knoten im Detail</h2>

@foreach($nodes as $i => $node)
    @php
        $label = data_get($node, 'data.label', '—');
        $type = $node['class'] ?? '?';
        $d = (array) ($node['data'] ?? []);
        $service = app(\App\Services\WorkflowProcessDocService::class);
    @endphp
    <div class="node-box">
        <div class="node-head">
            {{ $i + 1 }}. {{ $label }}
            <span class="node-type">{{ $type }}</span>
        </div>

        @switch($type)
            @case('start')
                <p class="empty">Einstieg des Workflows. Wird vom Trigger automatisch ausgelöst.</p>
                @break

            @case('end')
                <table class="kv">
                    <tr><td class="k">Ergebnis</td><td class="v">{{ $d['result'] ?? 'completed' }}</td></tr>
                </table>
                @break

            @case('approval')
                <table class="kv">
                    <tr><td class="k">Empfänger-Typ</td><td class="v">{{ $d['recipient_type'] ?? '—' }}</td></tr>
                    @if(! empty($d['recipient_role_id']))<tr><td class="k">Rolle-ID</td><td class="v">#{{ $d['recipient_role_id'] }}</td></tr>@endif
                    @if(! empty($d['recipient_user_id']))<tr><td class="k">User-ID</td><td class="v">#{{ $d['recipient_user_id'] }}</td></tr>@endif
                    @if(! empty($d['list_id']))<tr><td class="k">Lookup-Liste</td><td class="v">#{{ $d['list_id'] }} · Schlüssel <code>{{ $d['lookup_source'] ?? '' }}</code></td></tr>@endif
                    <tr><td class="k">Karenzzeit</td><td class="v">{{ $d['grace_value'] ?? 3 }} {{ $d['grace_unit'] ?? 'days' }}</td></tr>
                    <tr><td class="k">Eskalation</td><td class="v">{{ $d['escalation_type'] ?? 'none' }}@if(! empty($d['escalation_role_id'])) → Rolle #{{ $d['escalation_role_id'] }}@endif</td></tr>
                    <tr><td class="k">Weiterleiten erlaubt</td><td class="v">{{ ! empty($d['allow_forward']) ? 'ja' : 'nein' }}</td></tr>
                    <tr><td class="k">Quorum</td><td class="v">{{ $d['quorum_mode'] ?? 'single' }}@if(($d['quorum_mode'] ?? '') === 'n_of_m') (mind. {{ $d['quorum_min'] ?? 2 }})@endif</td></tr>
                    <tr><td class="k">Kommentar bei Approve</td><td class="v">{{ ! empty($d['require_comment_on_approval']) ? 'Pflicht' : 'optional' }}</td></tr>
                    <tr><td class="k">Kommentar bei Reject</td><td class="v">{{ ! empty($d['require_comment_on_rejection']) ? 'Pflicht' : 'optional' }}</td></tr>
                </table>
                @if(! empty($d['extra_fields']))
                    <h3>Zusatzfelder beim Entscheiden</h3>
                    <table>
                        <thead><tr><th>Schlüssel</th><th>Label</th><th>Typ</th><th>Pflicht</th><th>Speichern in</th></tr></thead>
                        <tbody>
                            @foreach($d['extra_fields'] as $f)
                                <tr>
                                    <td><code>{{ $f['key'] ?? '' }}</code></td>
                                    <td>{{ $f['label'] ?? '' }}</td>
                                    <td>{{ $f['type'] ?? 'text' }}</td>
                                    <td>{{ ! empty($f['required']) ? 'ja' : 'nein' }}</td>
                                    <td>{{ ($f['target'] ?? 'doc') === 'instance' ? 'Workflow-Daten' : 'Dokument-Indexfeld' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
                @break

            @case('condition')
                <table>
                    <thead><tr><th>#</th><th>Feld</th><th>Operator</th><th>Wert</th><th>Label</th></tr></thead>
                    <tbody>
                        @foreach(($d['branches'] ?? []) as $bi => $b)
                            <tr>
                                <td>{{ $bi + 1 }}</td>
                                <td><code>{{ $b['field'] ?? '' }}</code></td>
                                <td>{{ $b['operator'] ?? '?' }}</td>
                                <td>{{ $b['value'] ?? '' }}</td>
                                <td>{{ $b['label'] ?? '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <p class="meta">Zusaetzlicher Else-Ausgang am Ende.</p>
                @break

            @case('switch_node')
                <table class="kv">
                    <tr><td class="k">Ausdruck</td><td class="v"><code>{{ $d['expression'] ?? '' }}</code></td></tr>
                </table>
                <table>
                    <thead><tr><th>#</th><th>Label</th><th>Wert</th></tr></thead>
                    <tbody>
                        @foreach(($d['cases'] ?? []) as $ci => $c)
                            <tr>
                                <td>{{ $ci + 1 }}</td>
                                <td>{{ $c['label'] ?? '' }}</td>
                                <td><code>{{ $c['value'] ?? '' }}</code></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <p class="meta">Plus Default-Ausgang am Ende.</p>
                @break

            @case('notify')
                <table class="kv">
                    <tr><td class="k">Empfänger</td><td class="v">{{ $d['recipient_type'] ?? '—' }}@if(! empty($d['recipient_role_id'])) (Rolle #{{ $d['recipient_role_id'] }})@endif</td></tr>
                    <tr><td class="k">Betreff</td><td class="v">{{ $d['subject'] ?? '' }}</td></tr>
                </table>
                @if(! empty($d['body']))
                    <h3>Body-Template</h3>
                    <pre>{{ $d['body'] }}</pre>
                @endif
                @break

            @case('http')
                <table class="kv">
                    <tr><td class="k">Methode</td><td class="v">{{ $d['method'] ?? 'POST' }}</td></tr>
                    <tr><td class="k">URL</td><td class="v"><code>{{ $d['url'] ?? '' }}</code></td></tr>
                    <tr><td class="k">Authentifizierung</td><td class="v">{{ $d['auth_type'] ?? 'none' }}@if(($d['auth_type'] ?? '') === 'bearer' && ! empty($d['auth_token'])) (Token: {{ $service->maskSecret($d['auth_token']) }})@endif</td></tr>
                    <tr><td class="k">Timeout</td><td class="v">{{ $d['timeout_seconds'] ?? 30 }}s</td></tr>
                    <tr><td class="k">Body-Typ</td><td class="v">{{ $d['body_type'] ?? 'json' }}</td></tr>
                </table>
                @if(! empty($d['headers']))
                    <h3>Header</h3>
                    <table>
                        <thead><tr><th>Name</th><th>Wert</th></tr></thead>
                        <tbody>
                            @foreach($d['headers'] as $h)
                                <tr>
                                    <td><code>{{ $h['key'] ?? '' }}</code></td>
                                    <td>{{ str_starts_with(strtolower($h['key'] ?? ''), 'authorization') ? $service->maskSecret($h['value'] ?? '') : ($h['value'] ?? '') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
                @if(! empty($d['body_template']))
                    <h3>Body-Template</h3>
                    <pre>{{ $d['body_template'] }}</pre>
                @endif
                @if(! empty($d['response_mapping']))
                    <h3>Antwort-Mapping</h3>
                    <table>
                        <thead><tr><th>JSON-Pfad</th><th>Speichern als</th></tr></thead>
                        <tbody>
                            @foreach($d['response_mapping'] as $m)
                                <tr><td><code>{{ $m['path'] ?? '' }}</code></td><td><code>{{ $m['save_as'] ?? '' }}</code></td></tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
                @break

            @case('pdf_render')
                <table class="kv">
                    <tr><td class="k">Dateiname</td><td class="v"><code>{{ $d['filename'] ?? '' }}</code></td></tr>
                    <tr><td class="k">Doku-Typ</td><td class="v">{{ $d['document_type'] ?: '—' }}</td></tr>
                    <tr><td class="k">Label</td><td class="v">{{ $d['label'] ?: '—' }}</td></tr>
                </table>
                <h3>HTML-Template (gekürzt)</h3>
                <pre>{{ \Illuminate\Support\Str::limit($d['html_template'] ?? '', 800) }}</pre>
                @break

            @case('wait')
                <table class="kv">
                    <tr><td class="k">Wartezeit</td><td class="v">{{ $d['wait_value'] ?? 1 }} {{ $d['wait_unit'] ?? 'days' }}</td></tr>
                </table>
                @break

            @case('set_field')
                <table>
                    <thead><tr><th>Feld</th><th>Wert</th><th>Als Zahl</th></tr></thead>
                    <tbody>
                        @foreach(($d['assignments'] ?? []) as $a)
                            <tr>
                                <td><code>{{ $a['field'] ?? '' }}</code></td>
                                <td>{{ $a['value'] ?? '' }}</td>
                                <td>{{ ! empty($a['as_number']) ? 'ja' : 'nein' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @break

            @case('subworkflow')
                <table class="kv">
                    <tr><td class="k">Ziel-Workflow-ID</td><td class="v">#{{ $d['target_workflow_id'] ?? '—' }}</td></tr>
                    <tr><td class="k">Bei Fehler weitermachen</td><td class="v">{{ ! empty($d['continue_on_failure']) ? 'ja' : 'nein' }}</td></tr>
                </table>
                @if(! empty($d['input_mapping']))
                    <h3>Eingabe-Mapping (Parent → Child)</h3>
                    <table>
                        <thead><tr><th>Child-Feld</th><th>Quelle (Parent)</th></tr></thead>
                        <tbody>
                            @foreach($d['input_mapping'] as $m)
                                <tr><td><code>{{ $m['target'] ?? '' }}</code></td><td><code>{{ $m['source'] ?? '' }}</code></td></tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
                @if(! empty($d['output_mapping']))
                    <h3>Ausgabe-Mapping (Child → Parent)</h3>
                    <table>
                        <thead><tr><th>Parent-Feld</th><th>Quelle (Child)</th></tr></thead>
                        <tbody>
                            @foreach($d['output_mapping'] as $m)
                                <tr><td><code>{{ $m['target'] ?? '' }}</code></td><td><code>{{ $m['source'] ?? '' }}</code></td></tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
                @break

            @case('loop')
                <table class="kv">
                    <tr><td class="k">Quell-Feld (Liste)</td><td class="v"><code>{{ $d['source_field'] ?? '' }}</code></td></tr>
                    <tr><td class="k">Ziel-Workflow</td><td class="v">#{{ $d['target_workflow_id'] ?? '—' }}</td></tr>
                    <tr><td class="k">Element-Feldname</td><td class="v"><code>{{ $d['item_field_name'] ?? '_item' }}</code></td></tr>
                    <tr><td class="k">Max. Iterationen</td><td class="v">{{ $d['max_iterations'] ?? 100 }}</td></tr>
                    @if(! empty($d['collect_field']))
                        <tr><td class="k">Sammeln aus Child</td><td class="v"><code>{{ $d['collect_field'] }}</code> → <code>{{ $d['collect_into'] ?? '_loop_results' }}</code></td></tr>
                    @endif
                </table>
                @break

            @case('aggregator')
                <table class="kv">
                    <tr><td class="k">Quellfeld</td><td class="v"><code>{{ $d['source_field'] ?? '' }}</code></td></tr>
                    <tr><td class="k">Operation</td><td class="v">{{ $d['operation'] ?? 'sum' }}</td></tr>
                    <tr><td class="k">Zielfeld</td><td class="v"><code>{{ $d['target_field'] ?? '' }}</code></td></tr>
                    @if(($d['operation'] ?? '') === 'concat')
                        <tr><td class="k">Trennzeichen</td><td class="v"><code>{{ $d['separator'] ?? ', ' }}</code></td></tr>
                    @endif
                </table>
                @break

            @default
                <p class="empty">Keine spezielle Darstellung für diesen Knotentyp.</p>
        @endswitch
    </div>
@endforeach

<div class="footer">
    Generiert am {{ $generated_at->format('d.m.Y H:i') }} · {{ config('app.name') }}
    · Definition-SHA-256: <span class="hash">{{ substr($definition_hash, 0, 32) }}…</span>
    · Diese Prozessbeschreibung ist Bestandteil der Verfahrensdokumentation.
</div>

</body>
</html>
