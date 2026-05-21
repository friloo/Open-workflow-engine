<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
            'notificationCatalog' => \App\Support\NotificationPreferences::catalog(),
            'notificationChannels' => \App\Support\NotificationPreferences::channels(),
            'notificationMatrix' => \App\Support\NotificationPreferences::matrixFor($request->user()),
        ]);
    }

    /**
     * Schreibt die Notification-Präferenzen aus dem Profile-Form.
     * Form-Format: prefs[event_key][channel] = '1' wenn checked.
     */
    public function updateNotificationPreferences(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'prefs' => ['array'],
            'prefs.*' => ['array'],
        ]);
        $user = $request->user();

        $catalog = array_keys(\App\Support\NotificationPreferences::catalog());
        $channels = array_keys(\App\Support\NotificationPreferences::channels());
        $submitted = $data['prefs'] ?? [];

        foreach ($catalog as $eventKey) {
            foreach ($channels as $channel) {
                $checked = isset($submitted[$eventKey][$channel]) && $submitted[$eventKey][$channel] === '1';
                \App\Support\NotificationPreferences::set($user, $eventKey, $channel, $checked);
            }
        }

        return Redirect::route('profile.edit')->with('status', 'notifications-updated');
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
