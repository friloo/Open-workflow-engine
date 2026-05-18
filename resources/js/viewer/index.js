import Drawflow from 'drawflow';
import 'drawflow/dist/drawflow.min.css';
import '../designer/designer.css';
import './viewer.css';

/**
 * Read-only Drawflow viewer that highlights completed/current/pending
 * nodes of a running workflow instance.
 */
window.workflowViewer = function () {
    return {
        boot() {
            const el = document.getElementById('workflow-viewer');
            const payloadEl = document.getElementById('viewer-payload');
            if (! el || ! payloadEl) return;

            const payload = JSON.parse(payloadEl.textContent);
            const editor = new Drawflow(el);
            editor.editor_mode = 'fixed';
            editor.reroute = true;
            editor.start();

            if (payload.definition && payload.definition.drawflow) {
                editor.import(payload.definition);
            }

            this.$nextTick(() => this.applyHighlights(payload));
        },

        applyHighlights(payload) {
            const completed = new Set((payload.completed_step_keys || []).map(String));
            const current = String(payload.current_step_key || '');
            const status = payload.status;

            document.querySelectorAll('#workflow-viewer .drawflow-node').forEach(node => {
                const id = String(node.id || '').replace('node-', '');
                node.classList.remove('viewer-node-current', 'viewer-node-completed', 'viewer-node-pending', 'viewer-node-cancelled');
                if (status === 'cancelled' || status === 'failed') {
                    if (completed.has(id)) {
                        node.classList.add('viewer-node-completed');
                    } else {
                        node.classList.add('viewer-node-cancelled');
                    }
                } else if (id === current) {
                    node.classList.add('viewer-node-current');
                } else if (completed.has(id)) {
                    node.classList.add('viewer-node-completed');
                } else {
                    node.classList.add('viewer-node-pending');
                }
            });
        },
    };
};
