<?php

namespace Updater;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Admin-Endpoints fuer den Updater. Bewusst nicht im App\-Namespace,
 * damit der Rueckbau (Loeschen von updater/ + Entfernen des require in
 * routes/web.php) den App-Code nicht beruehrt.
 */
final class UpdateController
{
    private function manager(): UpdateManager
    {
        $audit = null;
        try {
            $audit = app(\App\Services\AuditLogger::class);
        } catch (\Throwable) {
            $audit = null;
        }
        return UpdaterFactory::create(DB::connection(), $audit);
    }

    public function index(Request $request): View
    {
        // Blade-Namespace 'updater' lazy registrieren — kein
        // ServiceProvider noetig, weiter isoliert.
        \View::addNamespace('updater', dirname(__DIR__).'/ui');

        $manager = $this->manager();
        return view('updater::index', [
            'currentSha' => $manager->getCurrentVersion(),
            'channel' => $manager->channel(),
            'channels' => array_keys(UpdateManager::CHANNELS),
            'inMaintenance' => $manager->isInMaintenance(),
        ]);
    }

    public function check(Request $request): JsonResponse
    {
        try {
            $r = $this->manager()->checkForUpdates();
            return response()->json(['ok' => true, 'data' => $r]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 502);
        }
    }

    public function install(Request $request): JsonResponse
    {
        try {
            $r = $this->manager()->installUpdate($request->user()?->id);
            return response()->json(['ok' => true, 'data' => $r]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function progress(Request $request): JsonResponse
    {
        $p = $this->manager()->getProgress();
        return response()->json(['ok' => true, 'data' => $p]);
    }

    public function setChannel(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'channel' => ['required', 'in:stable,development'],
        ]);
        $root = dirname(__DIR__, 2);
        $settings = UpdaterFactory::loadSettings($root);
        $settings['channel'] = $data['channel'];
        UpdaterFactory::saveSettings($root, $settings);
        return back()->with('status', "Update-Channel auf '{$data['channel']}' gesetzt.");
    }

    public function migrationStatus(): JsonResponse
    {
        $runner = new MigrationsRunner(DB::connection(), dirname(__DIR__).'/migrations');
        return response()->json(['ok' => true, 'data' => $runner->status()]);
    }
}
