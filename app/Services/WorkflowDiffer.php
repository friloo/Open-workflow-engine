<?php

namespace App\Services;

use App\Models\WorkflowVersion;

/**
 * Berechnet einen knoten-basierten Diff zwischen zwei
 * Workflow-Versionen. Ergebnis ist eine Liste von Knoten mit
 * Status (added/removed/modified/unchanged) und je nach Status
 * mit Detail-Änderungen pro Feld.
 */
class WorkflowDiffer
{
    /**
     * @return array{
     *   nodes: array<int, array{
     *     step_key: string, status: string, label_a: ?string, label_b: ?string,
     *     class: ?string, fields: array<int, array{key:string, before: mixed, after: mixed}>
     *   }>,
     *   counts: array{added:int, removed:int, modified:int, unchanged:int},
     *   form_changes: array<int, array{key:string, before:mixed, after:mixed}>
     * }
     */
    public function diff(WorkflowVersion $a, WorkflowVersion $b): array
    {
        $nodesA = data_get($a->definition, 'drawflow.Home.data', []);
        $nodesB = data_get($b->definition, 'drawflow.Home.data', []);

        $keys = array_unique(array_merge(array_keys($nodesA), array_keys($nodesB)));
        sort($keys);

        $out = [];
        $counts = ['added' => 0, 'removed' => 0, 'modified' => 0, 'unchanged' => 0];

        foreach ($keys as $k) {
            $na = $nodesA[$k] ?? null;
            $nb = $nodesB[$k] ?? null;
            $row = [
                'step_key' => $k,
                'label_a' => data_get($na, 'data.label'),
                'label_b' => data_get($nb, 'data.label'),
                'class' => data_get($nb, 'class') ?? data_get($na, 'class'),
                'fields' => [],
            ];
            if (! $na && $nb) {
                $row['status'] = 'added';
                $counts['added']++;
            } elseif ($na && ! $nb) {
                $row['status'] = 'removed';
                $counts['removed']++;
            } else {
                $da = data_get($na, 'data', []);
                $db = data_get($nb, 'data', []);
                $allKeys = array_unique(array_merge(array_keys((array) $da), array_keys((array) $db)));
                foreach ($allKeys as $fk) {
                    $va = $da[$fk] ?? null;
                    $vb = $db[$fk] ?? null;
                    if ($va === $vb) continue;
                    if (is_array($va) || is_array($vb)) {
                        if (json_encode($va) === json_encode($vb)) continue;
                    }
                    $row['fields'][] = ['key' => $fk, 'before' => $va, 'after' => $vb];
                }
                // Auch Connection-Änderungen erfassen (kurz)
                $connA = json_encode(data_get($na, 'outputs', []));
                $connB = json_encode(data_get($nb, 'outputs', []));
                if ($connA !== $connB) {
                    $row['fields'][] = ['key' => '__connections', 'before' => $connA, 'after' => $connB];
                }
                if (empty($row['fields'])) {
                    $row['status'] = 'unchanged';
                    $counts['unchanged']++;
                } else {
                    $row['status'] = 'modified';
                    $counts['modified']++;
                }
            }
            $out[] = $row;
        }

        // Form-Schema-Diff (flach)
        $formA = (array) ($a->form_schema ?? []);
        $formB = (array) ($b->form_schema ?? []);
        $formChanges = [];
        $formKeys = array_unique(array_merge(array_keys($formA), array_keys($formB)));
        foreach ($formKeys as $k) {
            $av = $formA[$k] ?? null;
            $bv = $formB[$k] ?? null;
            if (json_encode($av) !== json_encode($bv)) {
                $formChanges[] = ['key' => $k, 'before' => $av, 'after' => $bv];
            }
        }

        return ['nodes' => $out, 'counts' => $counts, 'form_changes' => $formChanges];
    }
}
