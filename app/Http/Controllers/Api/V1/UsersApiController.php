<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API: Benutzer (read-only). Token-Ability: users.view
 */
class UsersApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = User::query()->with('roles:id,slug,name');
        if ($s = trim((string) $request->get('q', ''))) {
            $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")
                ->orWhere('email', 'like', "%{$s}%"));
        }
        if ($request->boolean('active_only')) $q->where('is_active', true);
        if ($role = $request->get('role')) {
            $q->whereHas('roles', fn ($r) => $r->where('slug', $role));
        }

        $perPage = min(200, max(10, (int) $request->get('per_page', 50)));
        $page = $q->orderBy('name')->paginate($perPage);

        return response()->json([
            'data' => collect($page->items())->map(fn (User $u) => self::serialize($u))->all(),
            'meta' => [
                'page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json(self::serialize($user->load('roles:id,slug,name'), full: true));
    }

    public static function serialize(User $u, bool $full = false): array
    {
        $base = [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'is_active' => (bool) $u->is_active,
            'roles' => $u->roles->map(fn ($r) => ['slug' => $r->slug, 'name' => $r->name])->all(),
        ];
        if ($full) {
            $base['department'] = $u->department;
            $base['job_title'] = $u->job_title;
            $base['phone'] = $u->phone;
            $base['supervisor_id'] = $u->supervisor_id;
            $base['last_login_at'] = $u->last_login_at?->toIso8601String();
        }
        return $base;
    }
}
