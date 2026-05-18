<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DelegationController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'delegate_user_id' => ['nullable', 'integer', 'different:'.$user->id, 'exists:users,id'],
            'delegate_from' => ['nullable', 'required_with:delegate_user_id', 'date'],
            'delegate_to' => ['nullable', 'required_with:delegate_user_id', 'date', 'after_or_equal:delegate_from'],
            'delegate_reason' => ['nullable', 'string', 'max:255'],
        ]);

        // Wenn der Vertreter geleert wird, raeumen wir die Felder mit.
        if (empty($data['delegate_user_id'])) {
            $data = ['delegate_user_id' => null, 'delegate_from' => null, 'delegate_to' => null, 'delegate_reason' => null];
        }

        $original = $user->only(['delegate_user_id', 'delegate_from', 'delegate_to', 'delegate_reason']);
        $user->fill($data)->save();

        $target = $user->delegate_user_id ? User::find($user->delegate_user_id) : null;
        $this->audit->log('user.delegate.updated', $user, $original, $user->only(array_keys($original)),
            $target
                ? "Vertretung gesetzt: {$user->email} -> {$target->email} ({$user->delegate_from?->format('d.m.Y')} bis {$user->delegate_to?->format('d.m.Y')})"
                : "Vertretung entfernt: {$user->email}",
            $user->id);

        return back()->with('status', 'Vertretung gespeichert.');
    }

    public function clear(Request $request): RedirectResponse
    {
        $user = $request->user();
        $user->fill([
            'delegate_user_id' => null,
            'delegate_from' => null,
            'delegate_to' => null,
            'delegate_reason' => null,
        ])->save();
        $this->audit->log('user.delegate.cleared', $user, null, null, "Vertretung entfernt: {$user->email}", $user->id);
        return back()->with('status', 'Vertretung beendet.');
    }
}
