<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tracking fuer den Onboarding-Wizard: User klickt "Spaeter" oder
 * "Fertig", wir merken uns das.
 */
class OnboardingController extends Controller
{
    public function dismiss(Request $request): JsonResponse
    {
        $request->user()->forceFill(['onboarding_dismissed_at' => now()])->save();
        return response()->json(['ok' => true]);
    }

    public function complete(Request $request): JsonResponse
    {
        $request->user()->forceFill(['onboarding_completed_at' => now()])->save();
        return response()->json(['ok' => true]);
    }
}
