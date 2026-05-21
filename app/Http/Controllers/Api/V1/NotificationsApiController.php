<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API: Eigene In-App-Notifications lesen + als gelesen markieren.
 * Kein spezielles Recht — User sieht immer nur SEINE eigenen.
 */
class NotificationsApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = AppNotification::query()->where('user_id', $request->user()->id)
            ->orderByDesc('id');
        if ($request->boolean('unread_only')) $q->whereNull('read_at');
        $perPage = min(200, max(10, (int) $request->get('per_page', 50)));
        $page = $q->paginate($perPage);

        return response()->json([
            'data' => collect($page->items())->map(fn (AppNotification $n) => [
                'id' => $n->id,
                'type' => $n->type,
                'title' => $n->title,
                'body' => $n->body,
                'url' => $n->url,
                'read_at' => $n->read_at?->toIso8601String(),
                'created_at' => $n->created_at?->toIso8601String(),
            ])->all(),
            'meta' => [
                'page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function markRead(Request $request, AppNotification $notification): JsonResponse
    {
        if ($notification->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Nicht deine Notification.'], 403);
        }
        $notification->update(['read_at' => now()]);
        return response()->json(['ok' => true]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        AppNotification::where('user_id', $request->user()->id)
            ->whereNull('read_at')->update(['read_at' => now()]);
        return response()->json(['ok' => true]);
    }
}
