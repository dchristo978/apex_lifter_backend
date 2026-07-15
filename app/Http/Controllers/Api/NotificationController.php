<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RankNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()
            ->rankNotifications()
            ->with(['machine:id,name', 'overtakenBy:id,name'])
            ->latest()
            ->limit(50)
            ->get()
            ->map(function (RankNotification $n) {
                // For a 'new_follower' notification the "other user" is the new
                // follower; surface actor_id/name so the app can deep-link to
                // their profile.
                $data = $n->toArray();
                $data['actor_id'] = $n->overtaken_by_user_id;
                $data['actor_name'] = $n->overtakenBy?->name;

                return $data;
            });

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $request->user()->rankNotifications()->whereNull('read_at')->count(),
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()
            ->rankNotifications()
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'All notifications marked as read.']);
    }
}
