<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        return view('notifications.index', [
            'notifications' => $request->user()->appNotifications()->paginate(50),
        ]);
    }

    public function dropdown(Request $request): JsonResponse
    {
        $user = $request->user();
        $unreadCount = $user->appNotifications()->whereNull('read_at')->count();
        $items = $user->appNotifications()->limit(10)->get()->map(fn ($n) => [
            'id' => $n->id,
            'title' => $n->title,
            'body' => $n->body,
            'url' => $n->url,
            'unread' => $n->read_at === null,
            'created_at' => $n->created_at->diffForHumans(),
        ]);
        return response()->json(['unread' => $unreadCount, 'items' => $items]);
    }

    public function markRead(Request $request, AppNotification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);
        if (! $notification->read_at) {
            $notification->update(['read_at' => now()]);
        }
        if ($notification->url) {
            return redirect($notification->url);
        }
        return redirect()->route('notifications.index');
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->appNotifications()->whereNull('read_at')->update(['read_at' => now()]);
        return back()->with('status', 'Alle als gelesen markiert.');
    }
}
