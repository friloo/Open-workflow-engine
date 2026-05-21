<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use App\Services\WebPushSender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PushController extends Controller
{
    public function __construct(private readonly WebPushSender $sender) {}

    /**
     * Endpoint für den Service-Worker: Browser meldet seine
     * Subscription-Daten an, wir speichern sie.
     */
    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:512'],
            'keys.p256dh' => ['required', 'string', 'max:191'],
            'keys.auth' => ['required', 'string', 'max:191'],
        ]);

        PushSubscription::updateOrCreate(
            ['user_id' => $request->user()->id, 'endpoint' => $data['endpoint']],
            [
                'public_key' => $data['keys']['p256dh'],
                'auth_token' => $data['keys']['auth'],
                'user_agent' => substr((string) $request->userAgent(), 0, 191),
            ],
        );

        return response()->json(['ok' => true]);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $endpoint = (string) $request->input('endpoint');
        if ($endpoint !== '') {
            PushSubscription::where('user_id', $request->user()->id)
                ->where('endpoint', $endpoint)
                ->delete();
        }
        return response()->json(['ok' => true]);
    }

    /**
     * Test-Push für User: schickt eine Test-Nachricht an alle aktiven
     * Subscriptions. Nützlich um zu testen ob der Browser-Subscribe
     * wirklich durchgeht.
     */
    public function test(Request $request): RedirectResponse
    {
        if (! $this->sender->isEnabled()) {
            return back()->withErrors(['push' => 'Push ist serverseitig nicht aktiv (VAPID-Keys fehlen).']);
        }
        $count = $this->sender->sendToUser(
            $request->user(),
            'Test-Push aus ' . config('app.name'),
            'Wenn du das siehst, funktionieren Push-Benachrichtigungen einwandfrei.',
            url('/dashboard'),
        );
        return back()->with('status', "{$count} Subscription(s) angesprochen.");
    }
}
