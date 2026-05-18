<?php

namespace App\Http\Controllers\Workflow;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\FormSubmission;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FormSubmissionController extends Controller
{
    public function index(Form $form, Request $request): View
    {
        $submissions = $form->submissions()
            ->with('submittedBy', 'instance')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('forms.submissions.index', compact('form', 'submissions'));
    }

    public function show(Form $form, FormSubmission $submission): View
    {
        abort_unless($submission->form_id === $form->id, 404);
        $submission->load('submittedBy', 'instance.workflow');
        return view('forms.submissions.show', compact('form', 'submission'));
    }

    public function export(Form $form): StreamedResponse
    {
        $columns = array_map(fn ($f) => $f['key'], $form->schema ?? []);
        $labels = array_map(fn ($f) => $f['label'] ?? $f['key'], $form->schema ?? []);

        $filename = 'submissions-'.$form->slug.'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($form, $columns, $labels) {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM fuer Excel-Kompatibilitaet
            fwrite($out, "\xEF\xBB\xBF");

            $header = ['id', 'eingegangen', 'eingereicht_von', 'workflow_instanz'];
            foreach ($labels as $l) $header[] = $l;
            fputcsv($out, $header, ';');

            $form->submissions()->with('submittedBy', 'instance')->orderBy('id')->chunk(500, function ($chunk) use ($out, $columns) {
                foreach ($chunk as $s) {
                    $row = [
                        $s->id,
                        $s->created_at?->format('Y-m-d H:i:s'),
                        $s->submittedBy?->email ?? 'oeffentlich',
                        $s->workflow_instance_id ?? '',
                    ];
                    foreach ($columns as $col) {
                        $v = $s->data[$col] ?? '';
                        if (is_bool($v)) $v = $v ? 'ja' : 'nein';
                        if (is_array($v)) $v = implode(', ', $v);
                        $row[] = (string) $v;
                    }
                    fputcsv($out, $row, ';');
                }
            });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
