<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\DatevExporter;
use App\Support\DocumentTypes;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DatevController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly DatevExporter $exporter,
    ) {}

    public function index(): View
    {
        return view('admin.datev.index', [
            'config' => Settings::group('datev'),
            'documentTypes' => DocumentTypes::all(),
            'defaultMap' => DatevExporter::DEFAULT_FIELD_MAP,
        ]);
    }

    public function updateConfig(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'config' => ['array'],
            'config.konto_lieferant' => ['nullable', 'string', 'max:10'],
            'config.gegenkonto_aufwand' => ['nullable', 'string', 'max:10'],
            'config.bu_schluessel' => ['nullable', 'string', 'max:4'],
            'field_map' => ['array'],
            'field_map.*' => ['nullable', 'string', 'max:64'],
        ]);

        Settings::set('datev.config', array_filter($data['config'] ?? [], fn ($v) => $v !== ''), $request->user()->id);
        Settings::set('datev.field_map', array_filter($data['field_map'] ?? [], fn ($v) => $v !== ''), $request->user()->id);

        $this->audit->log('settings.datev.updated', null, null, [
            'config' => $data['config'] ?? [],
        ], 'DATEV-Konfiguration aktualisiert', $request->user()->id);

        return back()->with('status', 'DATEV-Konfiguration gespeichert.');
    }

    public function export(Request $request): BinaryFileResponse|RedirectResponse
    {
        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'document_type' => ['nullable', 'string', 'max:64'],
        ]);

        try {
            $result = $this->exporter->export(
                Carbon::parse($data['from']),
                Carbon::parse($data['to']),
                $data['document_type'] ?? 'Rechnung',
            );
        } catch (\Throwable $e) {
            return back()->withErrors(['export' => $e->getMessage()]);
        }

        $this->audit->log('datev.exported', null, null, [
            'from' => $data['from'], 'to' => $data['to'],
            'document_type' => $data['document_type'] ?? 'Rechnung',
            'count' => $result['count'],
        ], "DATEV-Export: {$result['count']} Belege", $request->user()->id);

        return response()->download($result['path'], $result['filename'])->deleteFileAfterSend();
    }
}
