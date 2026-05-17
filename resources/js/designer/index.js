import Drawflow from 'drawflow';
import 'drawflow/dist/drawflow.min.css';
import './designer.css';

const NODE_TEMPLATES = {
    start: {
        label: 'Start',
        shortLabel: 'S',
        color: '#10b981',
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
        }),
        outputClasses: (data) => data.allow_forward
            ? ['genehmigt', 'abgelehnt', 'weitergeleitet']
            : ['genehmigt', 'abgelehnt'],
    },
    condition: {
        label: 'Bedingung',
        shortLabel: '?',
        color: '#f59e0b',
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
    end: {
        label: 'Ende',
        shortLabel: 'E',
        color: '#64748b',
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
}));

const TRIGGER_LABELS = {
    form: 'Formular',
    manual: 'Manuell',
    schedule: 'Zeitgesteuert',
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
        tab: 'canvas',
        selectedNode: null,
        saving: false,
        saveMessage: '',
        saveError: false,
        changeSummary: '',
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

        // -- Save ------------------------------------------------------------

        async save() {
            if (this.saving) return;
            this.saving = true;
            this.saveMessage = '';
            this.saveError = false;

            try {
                // Strip the helper fields we keep only in the editor
                const formSchemaOut = this.formSchema.map(({ _optionsText, ...rest }) => rest);

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
