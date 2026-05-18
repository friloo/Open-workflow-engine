<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\HealthChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class HealthController extends Controller
{
    public function index(HealthChecker $checker): View
    {
        return view('admin.health.index', ['checks' => $checker->all()]);
    }

    public function json(HealthChecker $checker): JsonResponse
    {
        $checks = $checker->all();
        $overall = collect($checks)->reduce(function ($carry, $c) {
            if ($carry === 'fail' || $c['status'] === 'fail') return 'fail';
            if ($carry === 'warn' || $c['status'] === 'warn') return 'warn';
            return 'ok';
        }, 'ok');
        return response()->json(['status' => $overall, 'checks' => $checks]);
    }
}
