<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Services\NotificationScanner;
use App\Services\NotificationService;
use App\Support\DrfPagination;
use Illuminate\Http\Request;

/** A user's own notifications, plus a manual scan trigger for managers/admins. */
class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $query = Notification::where('user_id', $request->user()->id)
            ->orderByDesc('id');

        if ($request->boolean('unread')) {
            $query->whereNull('read_at');
        }
        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }

        return response()->json(
            DrfPagination::paginate($query, $request, fn (Notification $n) => $n->toApi())
        );
    }

    public function unreadCount(Request $request)
    {
        return response()->json(['count' => NotificationService::unreadCount($request->user())]);
    }

    public function markRead(Request $request, Notification $notification)
    {
        // You can only touch your own notifications.
        if ($notification->user_id !== $request->user()->id) {
            return response()->json(['detail' => 'Not found.'], 404);
        }

        return response()->json(NotificationService::markRead($notification)->toApi());
    }

    public function markAllRead(Request $request)
    {
        return response()->json(['updated' => NotificationService::markAllRead($request->user())]);
    }

    /**
     * Run the detectors now. Useful without a scheduler running — the bell
     * refreshes on the next poll. Manager/admin only (see routes).
     */
    public function scan()
    {
        return response()->json(['created' => NotificationScanner::scan()]);
    }
}
