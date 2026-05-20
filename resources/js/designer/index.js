import Drawflow from 'drawflow';
import 'drawflow/dist/drawflow.min.css';
import './designer.css';

const NODE_TEMPLATES = {
    start: {
        label: 'Start',
        shortLabel: 'S',
        color: '#10b981',
        category: 'Start & Ende',
        help: 'Einstiegspunkt des Workflows. Wird vom Trigger automatisch ausgeloest.',
        inputs: 0,
        outputs: 1,
        defaults: () => ({ label: 'Start' }),
        outputClasses: () => ['weiter'],
    },
    approval: {
        label: 'Genehmigung',
        shortLabel: 'G',
        color: '#6366f1',
        category: 'Entscheidung',
        help: 'Aufgabe an eine Person oder Rolle. Genehmigt / Abgelehnt / (Weiterleitung).',
        inputs: 1,
        outputs: 2,
        defaults: () => ({
            label: 'Genehmigung',
            recipient_type: 'supervisor_of_initiator',
            recipient_role_id: null,
            recipient_user_id: null,
            grace_value: 3,
            grace_unit: 'days',
            escalation_type: 'none',
            escalation_role_id: null,
            allow_forward: false,
            // Pro Approval konfigurierbare Zusatzfelder, die der Genehmiger
            // beim Entscheiden ausfuellen muss/kann. Jedes Feld:
            //   key:      maschinen-lesbar (a-z0-9_)
            //   label:    Anzeige
            //   type:     text|number|date|select|checkbox|textarea
            //   options:  fuer select (Array)
            //   required: bool
            //   target:   'doc' (Indexfelder am Doku) | 'instance' (Workflow-Daten)
            extra_fields: [],
        }),
        outputClasses: (data) => data.allow_forward
            ? ['genehmigt', 'abgelehnt', 'weitergeleitet']
            : ['genehmigt', 'abgelehnt'],
    },
    condition: {
        label: 'Bedingung',
        shortLabel: '?',
        color: '#f59e0b',
        category: 'Entscheidung',
        help: 'Verzweigt nach Werten aus dem Formular. Plus immer einem Else-Ausgang.',
        inputs: 1,
        outputs: 2, // 1 branch + else; grows dynamically
        defaults: () => ({
            label: 'Bedingung',
            branches: [
                { label: 'Zweig 1', field: '', operator: 'eq', value: '' },
            ],
        }),
        outputClasses: (data) => [
            ...(data.branches || []).map((_, i) => `zweig_${i + 1}`),
            'sonst',
        ],
    },
    notify: {
        label: 'Benachrichtigung',
        shortLabel: '@',
        color: '#0ea5e9',
        category: 'Kommunikation',
        help: 'Sendet eine E-Mail. Workflow laeuft danach weiter.',
        inputs: 1,
        outputs: 1,
        defaults: () => ({
            label: 'Benachrichtigung',
            recipient_type: 'initiator',
            recipient_role_id: null,
            recipient_user_id: null,
            subject: 'Workflow-Aktualisierung',
            body: 'Hallo {{ initiator }},\n\ndein Vorgang wurde aktualisiert.',
        }),
        outputClasses: () => ['weiter'],
    },
    http: {
        label: 'HTTP-Request',
        shortLabel: 'H',
        color: '#a855f7',
        category: 'Integration',
        help: 'Ruft eine externe API auf (z. B. Ticketsystem). Ausgaenge: OK / Fehler.',
        inputs: 1,
        outputs: 2,
        defaults: () => ({
            label: 'HTTP-Request',
            method: 'POST',
            url: 'https://api.example.com/endpoint',
            auth_type: 'none',
            auth_token: '',
            auth_username: '',
            auth_password: '',
            auth_header_name: 'X-API-Key',
            headers: [{ key: 'Accept', value: 'application/json' }],
            body_type: 'json',
            body_template: '{\n  "title": "{{ workflow_name }} #{{ instance_id }}",\n  "description": "{{ beschreibung }}",\n  "requester": "{{ initiator_email }}"\n}',
            body_form: [],
            response_mapping: [{ path: 'id', save_as: 'external_id' }],
            timeout_seconds: 30,
            continue_on_error: false,
        }),
        outputClasses: () => ['ok', 'fehler'],
    },
    pdf_render: {
        label: 'PDF erzeugen',
        shortLabel: 'P',
        color: '#dc2626',
        category: 'Daten',
        help: 'Erzeugt aus einem HTML-Template ein PDF und haengt es revisionssicher an die Instanz an.',
        inputs: 1,
        outputs: 1,
        defaults: () => ({
            label: 'PDF erzeugen',
            html_template: '<h1>Beleg #{{ instance_id }}</h1>\n<p>Workflow: {{ workflow_name }}</p>\n<p>Antragsteller: {{ initiator_name }} ({{ initiator_email }})</p>\n<p>Datum: {{ instance_started_at }}</p>',
            filename: 'beleg-{{ instance_id }}.pdf',
            document_type: '',
            label: '',
        }),
        outputClasses: () => ['weiter'],
    },
    wait: {
        label: 'Warten',
        shortLabel: '⏳',
        color: '#0d9488',
        category: 'Logik & Timing',
        help: 'Pausiert den Workflow fuer einen Zeitraum (Minuten/Stunden/Tage/Wochen/Monate). Der Scheduler weckt automatisch auf.',
        inputs: 1,
        outputs: 1,
        defaults: () => ({ label: 'Warten', wait_value: 3, wait_unit: 'days' }),
        outputClasses: () => ['weiter'],
    },
    set_field: {
        label: 'Feld setzen',
        shortLabel: '=',
        color: '#0891b2',
        category: 'Daten',
        help: 'Berechnet/setzt ein oder mehrere Felder in den Instanz-Daten. Werte koennen Platzhalter enthalten.',
        inputs: 1,
        outputs: 1,
        defaults: () => ({
            label: 'Feld setzen',
            assignments: [{ field: 'berechnet', value: '{{ feld_a }}', as_number: false }],
        }),
        outputClasses: () => ['weiter'],
    },
    end: {
        label: 'Ende',
        shortLabel: 'E',
        color: '#64748b',
        category: 'Start & Ende',
        help: 'Beendet den Workflow.',
        inputs: 1,
        outputs: 0,
        defaults: () => ({ label: 'Ende', result: 'completed' }),
        outputClasses: () => [],
    },
};

const PALETTE = Object.entries(NODE_TEMPLATES).map(([type, t]) => ({
    type,
    label: t.label,
    shortLabel: t.shortLabel,
    color: t.color,
    help: t.help,
    category: t.category || 'Sonstiges',
}));

// Kategorien-Reihenfolge im Sidebar — von "ich brauche das immer" bis "Spezialfaelle".
const CATEGORY_ORDER = ['Start & Ende', 'Entscheidung', 'Kommunikation', 'Daten', 'Logik & Timing', 'Integration', 'Sonstiges'];

const PALETTE_GROUPED = CATEGORY_ORDER
    .map(cat => ({ category: cat, items: PALETTE.filter(p => p.category === cat) }))
    .filter(g => g.items.length > 0);

const TRIGGER_LABELS = {
    form: 'Formular',
    manual: 'Manuell',
    recurring: 'Wiederkehrend',
};

function nodeHtml(type, data) {
    const tpl = NODE_TEMPLATES[type];
    return `
        <div class="owe-node owe-node--${type}" style="--node-color:${tpl.color}">
            <div class="owe-node__head">
                <span class="owe-node__chip">${tpl.shortLabel}</span>
                <span class="owe-node__title" data-bind="label">${escapeHtml(data.label || tpl.label)}</span>
            </div>
            <div class="owe-node__type">${tpl.label}</div>
        </div>
    `;
}

function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[c]));
}

window.designerApp = function designerApp() {
    return {
        editor: null,
        payload: {},
        workflow: {},
        triggerType: 'form',
        triggerLabel: '',
        currentVersion: null,
        directory: { roles: [], users: [] },
        formSchema: [],
        nodePalette: PALETTE,
        nodePaletteGrouped: PALETTE_GROUPED,
        tab: 'canvas',
        selectedNode: null,
        saving: false,
        saveMessage: '',
        saveError: false,
        changeSummary: '',
        // KI-Workflow-Generierung
        aiOpen: false,
        aiDesc: '',
        aiBusy: false,
        aiError: '',
        _urls: {},

        boot() {
            const payloadEl = document.getElementById('designer-payload');
            this.payload = JSON.parse(payloadEl.textContent);
            this.workflow = this.payload.workflow;
            this.triggerType = this.workflow.trigger_type;
            this.triggerLabel = TRIGGER_LABELS[this.triggerType] || this.triggerType;
            this.currentVersion = this.workflow.current_version_number || null;
            this.directory = this.payload.directory || { roles: [], users: [] };
            this._urls = this.payload.urls;

            this.formSchema = (this.payload.form_schema || []).map(f => ({
                ...f,
                _optionsText: Array.isArray(f.options) ? f.options.join('\n') : '',
                options: Array.isArray(f.options) ? f.options : [],
                show_if_field: f.show_if?.field || '',
                show_if_op: f.show_if?.operator || 'eq',
                show_if_value: f.show_if?.value ?? '',
            }));

            if (this.triggerType !== 'form') {
                this.tab = 'canvas';
            }

            this.$nextTick(() => this.initDrawflow());
        },

        initDrawflow() {
            const container = document.getElementById('drawflow');
            this.editor = new Drawflow(container);
            this.editor.reroute = true;
            this.editor.start();

            this.editor.on('nodeSelected', (id) => this.onNodeSelected(id));
            this.editor.on('nodeUnselected', () => { this.selectedNode = null; });
            this.editor.on('nodeRemoved', () => { this.selectedNode = null; });

            // Restore previous definition or insert a start node
            const def = this.payload.definition;
            if (def && def.drawflow) {
                this.editor.import(def);
            } else {
                this.addNodeAt('start', 80, 120);
            }
        },

        paletteFor(type) {
            const p = this.nodePalette.find(p => p.type === type);
            return p || { label: type, help: '' };
        },

        onDragStart(event, type) {
            event.dataTransfer.effectAllowed = 'copy';
            event.dataTransfer.setData('text/x-node-type', type);
        },

        onDrop(event) {
            event.preventDefault();
            const type = event.dataTransfer.getData('text/x-node-type');
            if (!type || !NODE_TEMPLATES[type]) return;

            // Translate browser coords to drawflow canvas coords.
            const rect = event.currentTarget.getBoundingClientRect();
            const zoom = this.editor.zoom;
            const canvasX = (event.clientX - rect.left - this.editor.canvas_x) / zoom;
            const canvasY = (event.clientY - rect.top - this.editor.canvas_y) / zoom;
            this.addNodeAt(type, canvasX, canvasY);
        },

        addNodeAt(type, x, y) {
            const tpl = NODE_TEMPLATES[type];
            const data = tpl.defaults();
            const html = nodeHtml(type, data);
            const id = this.editor.addNode(
                type, tpl.inputs, tpl.outputs,
                x, y, type, data, html,
            );
            return id;
        },

        onNodeSelected(id) {
            const node = this.editor.getNodeFromId(id);
            if (!node) { this.selectedNode = null; return; }
            // Selected node bundles type + reactive data reference
            this.selectedNode = {
                id: node.id,
                type: node.class,
                data: node.data,
            };
            // Watch for branch count changes on condition nodes
            if (node.class === 'condition' || node.class === 'approval') {
                this.$watch('selectedNode.data', () => this.syncOutputs(node.id), { deep: true });
            }
        },

        updateNodeLabel() {
            if (!this.selectedNode) return;
            const id = this.selectedNode.id;
            const el = document.querySelector(`#node-${id} [data-bind="label"]`);
            if (el) el.textContent = this.selectedNode.data.label || '';
            this.editor.updateNodeDataFromId(id, { ...this.selectedNode.data });
        },

        deleteSelected() {
            if (!this.selectedNode) return;
            const id = this.selectedNode.id;
            this.selectedNode = null;
            this.editor.removeNodeId(`node-${id}`);
        },

        addBranch() {
            if (!this.selectedNode || this.selectedNode.type !== 'condition') return;
            this.selectedNode.data.branches.push({
                label: `Zweig ${this.selectedNode.data.branches.length + 1}`,
                field: '',
                operator: 'eq',
                value: '',
            });
        },

        removeBranch(idx) {
            if (!this.selectedNode || this.selectedNode.type !== 'condition') return;
            this.selectedNode.data.branches.splice(idx, 1);
        },

        /**
         * Match the number of drawflow output ports to the configured branches.
         * For approvals: 2 ports (approved, rejected) plus optional "forwarded".
         */
        syncOutputs(nodeId) {
            const node = this.editor.getNodeFromId(nodeId);
            if (!node) return;
            const tpl = NODE_TEMPLATES[node.class];
            if (!tpl) return;
            const target = tpl.outputClasses(node.data);
            const currentCount = Object.keys(node.outputs).length;
            const targetCount = target.length;

            while (Object.keys(node.outputs).length < targetCount) {
                this.editor.addNodeOutput(nodeId);
            }
            while (Object.keys(node.outputs).length > targetCount) {
                const last = Object.keys(node.outputs).pop();
                this.editor.removeNodeOutput(nodeId, last);
            }
            this.editor.updateNodeDataFromId(nodeId, { ...node.data });
        },

        zoom(dir) {
            if (dir === 'in') this.editor.zoom_in();
            else if (dir === 'out') this.editor.zoom_out();
            else this.editor.zoom_reset();
        },

        // -- Form schema -----------------------------------------------------

        slugify(s) {
            return (s || '').toString().toLowerCase()
                .replace(/[^a-z0-9_]+/g, '_')
                .replace(/^_+|_+$/g, '')
                .substring(0, 64);
        },

        addField() {
            const idx = this.formSchema.length + 1;
            this.formSchema.push({
                label: `Feld ${idx}`,
                key: `feld_${idx}`,
                type: 'text',
                required: false,
                options: [],
                _optionsText: '',
            });
        },

        removeField(idx) {
            this.formSchema.splice(idx, 1);
        },

        // -- KI-Workflow-Generierung -----------------------------------------

        async generateFromAI(suggestWorkflowUrl) {
            if (this.aiBusy || !this.aiDesc.trim()) return;
            this.aiBusy = true;
            this.aiError = '';
            try {
                const res = await fetch(suggestWorkflowUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        description: this.aiDesc,
                        trigger_type: this.triggerType,
                    }),
                });
                const body = await res.json();
                if (! res.ok) {
                    this.aiError = body.error || `HTTP ${res.status}`;
                    return;
                }
                this.applyAIDraft(body.draft);
                this.aiOpen = false;
                this.aiDesc = '';
                this.saveMessage = 'KI-Entwurf geladen — bitte pruefen und speichern.';
                this.saveError = false;
                setTimeout(() => { this.saveMessage = ''; }, 6000);
            } catch (e) {
                this.aiError = e.message || 'Unbekannter Fehler';
            } finally {
                this.aiBusy = false;
            }
        },

        /**
         * Wandelt den abstrakten KI-Entwurf in Drawflow-Format und ersetzt
         * den aktuellen Canvas-Inhalt.
         */
        applyAIDraft(draft) {
            const nodes = Array.isArray(draft.nodes) ? draft.nodes : [];
            const edges = Array.isArray(draft.edges) ? draft.edges : [];
            const schema = Array.isArray(draft.form_schema) ? draft.form_schema : [];

            // Form-Schema setzen
            this.formSchema = schema.map(f => ({
                key: f.key, label: f.label || f.key, type: f.type || 'text',
                required: !! f.required, options: Array.isArray(f.options) ? f.options : [],
                _optionsText: Array.isArray(f.options) ? f.options.join('\n') : '',
            }));

            // Topologisches Layout: bestimme Rang jedes Knotens
            const idMap = {}, byId = {};
            nodes.forEach(n => { byId[n.id] = n; });
            const indeg = {};
            nodes.forEach(n => indeg[n.id] = 0);
            edges.forEach(e => { if (indeg[e.to] !== undefined) indeg[e.to]++; });

            const rank = {};
            const queue = nodes.filter(n => indeg[n.id] === 0 || n.type === 'start').map(n => n.id);
            queue.forEach(id => rank[id] = 0);
            const inflight = [...queue];
            while (inflight.length) {
                const id = inflight.shift();
                edges.filter(e => e.from === id).forEach(e => {
                    const r = (rank[id] || 0) + 1;
                    if (rank[e.to] === undefined || rank[e.to] < r) {
                        rank[e.to] = r;
                        inflight.push(e.to);
                    }
                });
            }
            // Fallback fuer unverbundene Knoten
            nodes.forEach(n => { if (rank[n.id] === undefined) rank[n.id] = 0; });

            // Vertikal stapeln pro Rang
            const perRank = {};
            nodes.forEach(n => {
                const r = rank[n.id];
                perRank[r] = perRank[r] || [];
                perRank[r].push(n.id);
            });

            // Drawflow-Definition aufbauen
            const data = {};
            const numericId = {};
            let nextNum = 1;
            nodes.forEach(n => { numericId[n.id] = nextNum++; });

            nodes.forEach(n => {
                const tpl = NODE_TEMPLATES[n.type] || NODE_TEMPLATES.start;
                const r = rank[n.id];
                const rowIndex = (perRank[r] || []).indexOf(n.id);
                const x = 40 + r * 260;
                const y = 60 + rowIndex * 180;

                const nodeData = { label: n.label || tpl.label, ...tpl.defaults(), ...(n.data || {}) };
                // Bei Condition: outputs anhand branches berechnen
                let outputs = tpl.outputs;
                if (n.type === 'condition') outputs = ((nodeData.branches || []).length) + 1;
                if (n.type === 'approval') outputs = nodeData.allow_forward ? 3 : 2;

                const id = numericId[n.id];
                data[id] = {
                    id, name: n.type, class: n.type, typenode: false,
                    data: nodeData,
                    html: nodeHtml(n.type, nodeData),
                    inputs: tpl.inputs ? { input_1: { connections: [] } } : {},
                    outputs: {},
                    pos_x: x, pos_y: y,
                };
                for (let i = 1; i <= outputs; i++) {
                    data[id].outputs['output_' + i] = { connections: [] };
                }
            });

            // Kanten setzen
            edges.forEach(e => {
                const fromId = numericId[e.from], toId = numericId[e.to];
                if (! fromId || ! toId) return;
                const oKey = 'output_' + (e.from_output || 1);
                const iKey = 'input_' + (e.to_input || 1);
                if (! data[fromId] || ! data[toId]) return;
                if (! data[fromId].outputs[oKey]) data[fromId].outputs[oKey] = { connections: [] };
                if (! data[toId].inputs[iKey]) data[toId].inputs[iKey] = { connections: [] };
                data[fromId].outputs[oKey].connections.push({ node: String(toId), output: iKey });
                data[toId].inputs[iKey].connections.push({ node: String(fromId), input: oKey });
            });

            // Editor leeren und neu importieren
            this.selectedNode = null;
            this.editor.clear();
            this.editor.import({ drawflow: { Home: { data } } });
        },

        // -- Save ------------------------------------------------------------

        async save() {
            if (this.saving) return;
            this.saving = true;
            this.saveMessage = '';
            this.saveError = false;

            try {
                // Strip die Editor-Hilfsfelder und baue show_if in der Server-Form.
                const formSchemaOut = this.formSchema.map(({ _optionsText, show_if_field, show_if_op, show_if_value, ...rest }) => {
                    if (show_if_field) {
                        rest.show_if = { field: show_if_field, operator: show_if_op || 'eq', value: show_if_value ?? '' };
                    }
                    return rest;
                });

                const payload = {
                    definition: this.editor.export(),
                    form_schema: this.triggerType === 'form' ? formSchemaOut : null,
                    change_summary: this.changeSummary || null,
                };

                const res = await fetch(this._urls.save, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(payload),
                });

                if (!res.ok) {
                    const body = await res.json().catch(() => ({}));
                    const msg = body.message || `Speichern fehlgeschlagen (HTTP ${res.status})`;
                    throw new Error(msg);
                }

                const body = await res.json();
                this.currentVersion = body.version_number;
                this.changeSummary = '';
                this.saveMessage = `Version v${body.version_number} gespeichert.`;
                this.saveError = false;
            } catch (e) {
                this.saveMessage = e.message || 'Fehler beim Speichern.';
                this.saveError = true;
            } finally {
                this.saving = false;
                setTimeout(() => { this.saveMessage = ''; }, 4000);
            }
        },
    };
};
