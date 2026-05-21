<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Models\Permission;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin-seitige Token-Verwaltung fuer beliebige (insbesondere
 * Service-Account-) User. Sinnvoll fuer Integrationen, bei denen
 * man kein Login als der Service-User braucht.
 *
 * Permission: users.manage (Voll-Admin oder dedizierte User-Manager-Rolle).
 */
class UserApiTokenController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request, User $user): View
    {
        return view('admin.users.tokens', [
            'managedUser' => $user,
            'tokens' => $user->apiTokens()->orderByDesc('id')->get(),
            // Nur Permissions, die der Ziel-User auch selbst hat —
            // Token kann nie mehr als der User.
            'permissions' => Permission::orderBy('name')->get()
                ->filter(fn ($p) => $user->hasRole('admin') || $user->hasPermission($p->slug))
                ->values(),
            'plain' => $request->session()->pull('api_token.plain'),
        ]);
    }

    public function store(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'abilities' => ['nullable', 'array'],
            'abilities.*' => ['string', 'max:64'],
            'expires_in_days' => ['nullable', 'integer', 'between:1,3650'],
        ]);

        // Sicherheits-Check: Token-Abilities duerfen nie ueber die
        // Permissions des Ziel-Users hinausgehen (auch der erstellende
        // Admin darf das nicht escalieren).
        $abilities = $data['abilities'] ?? null;
        if (! empty($abilities) && ! $user->hasRole('admin')) {
            $invalid = collect($abilities)->reject(fn ($a) => $a === '*' || $user->hasPermission($a))->all();
            if (! empty($invalid)) {
                return back()->withErrors([
                    'abilities' => 'Der User hat folgende Rechte nicht: '.implode(', ', $invalid),
                ]);
            }
        }

        $expiresAt = ! empty($data['expires_in_days'])
            ? now()->addDays((int) $data['expires_in_days'])
            : null;

        $result = ApiToken::generate($user, $data['name'], $abilities, $expiresAt);

        $this->audit->log('api_token.created_for_user', $result['token'], null, [
            'name' => $result['token']->name,
            'prefix' => $result['token']->prefix,
            'abilities' => $result['token']->abilities,
            'expires_at' => $result['token']->expires_at?->toIso8601String(),
            'target_user_id' => $user->id,
            'target_user_email' => $user->email,
        ], "Admin-API-Token fuer {$user->email} erstellt: {$result['token']->name}",
            $request->user()->id);

        $request->session()->put('api_token.plain', $result['plain']);
        return redirect()->route('admin.users.tokens.index', $user);
    }

    public function destroy(Request $request, User $user, ApiToken $token): RedirectResponse
    {
        abort_unless($token->user_id === $user->id, 404);
        $token->update(['revoked_at' => now()]);
        $this->audit->log('api_token.revoked_by_admin', $token, null,
            ['name' => $token->name, 'target_user_id' => $user->id],
            "Admin-API-Token widerrufen: {$token->name} (User: {$user->email})",
            $request->user()->id);
        return back()->with('status', 'Token widerrufen.');
    }
}
