<?php

namespace App\Http\Controllers;

use App\Models\ApiToken;
use App\Models\Permission;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApiTokenController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): View
    {
        return view('profile.tokens', [
            'tokens' => $request->user()->apiTokens()->orderByDesc('id')->get(),
            'permissions' => Permission::orderBy('name')->get(),
            'plain' => $request->session()->pull('api_token.plain'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'abilities' => ['nullable', 'array'],
            'abilities.*' => ['string', 'max:64'],
            'expires_in_days' => ['nullable', 'integer', 'between:1,3650'],
        ]);

        $expiresAt = ! empty($data['expires_in_days'])
            ? now()->addDays((int) $data['expires_in_days'])
            : null;

        $result = ApiToken::generate(
            $request->user(),
            $data['name'],
            $data['abilities'] ?? null,
            $expiresAt,
        );

        $this->audit->log('api_token.created', $result['token'], null, [
            'name' => $result['token']->name,
            'prefix' => $result['token']->prefix,
            'abilities' => $result['token']->abilities,
            'expires_at' => $result['token']->expires_at?->toIso8601String(),
        ], "API-Token erstellt: {$result['token']->name}");

        $request->session()->put('api_token.plain', $result['plain']);
        return redirect()->route('tokens.index');
    }

    public function destroy(Request $request, ApiToken $token): RedirectResponse
    {
        abort_unless($token->user_id === $request->user()->id, 403);
        $token->update(['revoked_at' => now()]);
        $this->audit->log('api_token.revoked', $token, null, ['name' => $token->name],
            "API-Token widerrufen: {$token->name}");
        return back()->with('status', 'Token widerrufen.');
    }
}
