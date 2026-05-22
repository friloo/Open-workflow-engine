<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\LoginAnomalies;
use Illuminate\View\View;

class LoginAnomaliesController extends Controller
{
    public function __construct(private readonly LoginAnomalies $service) {}

    public function index(): View
    {
        return view('admin.login_anomalies.index', [
            'data' => $this->service->snapshot(24),
        ]);
    }
}
