<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\IcalFeedGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class IcalController extends Controller
{
    public function __construct(private readonly IcalFeedGenerator $generator) {}

    /**
     * Token-basierter Feed-Endpoint. Liefert text/calendar.
     * Kein Login noetig — der Token ist die Auth.
     */
    public function feed(string $token): Response
    {
        $user = User::where('ical_token', $token)->first();
        if (! $user || ! $user->is_active) {
            abort(404);
        }
        return response($this->generator->generate($user), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Cache-Control' => 'no-store, max-age=0',
            'Content-Disposition' => 'inline; filename="owe-inbox.ics"',
        ]);
    }

    /**
     * Token erzeugen / rotieren. Im Profil per Button erreichbar.
     */
    public function rotate(Request $request): RedirectResponse
    {
        $user = $request->user();
        $user->forceFill(['ical_token' => Str::random(48)])->save();
        return back()->with('status', 'Neuer iCal-Token erzeugt. Alter Token ist sofort ungueltig.');
    }

    /**
     * Token wieder loeschen — Feed deaktivieren.
     */
    public function revoke(Request $request): RedirectResponse
    {
        $request->user()->forceFill(['ical_token' => null])->save();
        return back()->with('status', 'iCal-Feed deaktiviert.');
    }
}
