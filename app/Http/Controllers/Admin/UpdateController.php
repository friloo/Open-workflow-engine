<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\Update\UpdateChannelFactory;
use App\Services\Update\UpdateManager;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UpdateController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(UpdateManager $manager): View
    {
        return view('admin.update.index', [
            'check' => $manager->check(),
            'channels' => UpdateChannelFactory::all(),
            'progress' => $manager->getProgress(),
            'maintenance' => $manager->isMaintenanceActive(),
        ]);
    }

    public function status(UpdateManager $manager): JsonResponse
    {
        return response()->json([
            'check' => $manager->check(),
            'progress' => $manager->getProgress(),
            'maintenance' => $manager->isMaintenanceActive(),
        ]);
    }

    public function updateChannel(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'channel' => ['required', Rule::in(array_keys(UpdateChannelFactory::all()))],
        ]);
        Settings::set('update.channel', $data['channel'], $request->user()->id);
        $this->audit->log('update.channel.changed', null, null, ['channel' => $data['channel']],
            "Update-Channel auf {$data['channel']}", $request->user()->id);
        return back()->with('status', 'Channel gespeichert.');
    }

    public function run(Request $request, UpdateManager $manager): RedirectResponse
    {
        try {
            $result = $manager->run($request->user()?->id);
            if (($result['status'] ?? null) === 'noop') {
                return back()->with('status', 'Es liegt schon die aktuelle Version vor.');
            }
            return back()->with('status', 'Update abgeschlossen: '.$result['version']);
        } catch (\Throwable $e) {
            return back()->withErrors(['update' => $e->getMessage()]);
        }
    }
}
