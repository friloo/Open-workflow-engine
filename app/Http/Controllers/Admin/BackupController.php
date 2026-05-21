<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\BackupService;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    public function __construct(private readonly BackupService $service) {}

    public function index(): View
    {
        return view('admin.backups.index', [
            'backups' => $this->service->list(),
            'retentionDays' => (int) Settings::get('backups.retention_days', 14),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        try {
            $path = $this->service->create($request->user()->id);
            return back()->with('status', 'Backup erstellt: '.basename($path));
        } catch (\Throwable $e) {
            return back()->withErrors(['backup' => $e->getMessage()]);
        }
    }

    public function download(string $file): BinaryFileResponse
    {
        $path = $this->service->path($file);
        abort_unless($path, 404);
        return response()->download($path);
    }

    public function destroy(Request $request, string $file): RedirectResponse
    {
        if (! $this->service->delete($file, $request->user()->id)) {
            return back()->withErrors(['backup' => 'Datei nicht gefunden.']);
        }
        return back()->with('status', 'Backup gelöscht.');
    }

    public function updateRetention(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'retention_days' => ['required', 'integer', 'between:1,365'],
        ]);
        Settings::set('backups.retention_days', (int) $data['retention_days'], $request->user()->id);
        return back()->with('status', 'Retention gespeichert.');
    }
}
