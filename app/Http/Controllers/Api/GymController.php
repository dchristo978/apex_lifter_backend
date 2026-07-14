<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Checkin;
use App\Models\Gym;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GymController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['gyms' => Gym::orderBy('name')->get()]);
    }

    /**
     * GPS check-in: finds the nearest gym within its check-in radius.
     */
    public function checkin(Request $request): JsonResponse
    {
        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $nearest = Gym::all()
            ->map(fn (Gym $gym) => [
                'gym' => $gym,
                'distance_m' => $gym->distanceFromM($data['latitude'], $data['longitude']),
            ])
            ->sortBy('distance_m')
            ->first();

        if ($nearest === null || $nearest['distance_m'] > $nearest['gym']->checkin_radius_m) {
            return response()->json([
                'message' => 'Tidak ada gym terdaftar di sekitar lokasimu.',
            ], 422);
        }

        $checkin = Checkin::create([
            'user_id' => $request->user()->id,
            'gym_id' => $nearest['gym']->id,
            'checked_in_at' => now(),
        ]);

        return response()->json([
            'checkin' => $checkin,
            'gym' => $nearest['gym'],
            'distance_m' => round($nearest['distance_m']),
        ], 201);
    }

    public function latestCheckin(Request $request): JsonResponse
    {
        $checkin = $request->user()
            ->checkins()
            ->with('gym')
            ->latest('checked_in_at')
            ->first();

        return response()->json(['checkin' => $checkin]);
    }

    /**
     * Lifters currently present at a gym: one row per user whose most recent
     * check-in here was within the last few hours. Powers the "who's training
     * with me" list.
     */
    public const ACTIVE_WINDOW_HOURS = 3;

    public function activeCheckins(Request $request, Gym $gym): JsonResponse
    {
        $since = now()->subHours(self::ACTIVE_WINDOW_HOURS);

        $people = $gym->checkins()
            ->where('checked_in_at', '>=', $since)
            ->with('user:id,name,avatar_path')
            ->latest('checked_in_at')
            ->get()
            ->unique('user_id')
            ->values()
            ->map(fn (Checkin $checkin) => [
                'user_id' => $checkin->user_id,
                'name' => $checkin->user->name,
                'avatar_url' => $checkin->user->avatarUrl(),
                'checked_in_at' => $checkin->checked_in_at->toIso8601String(),
                'is_me' => $checkin->user_id === $request->user()->id,
            ]);

        return response()->json([
            'gym' => ['id' => $gym->id, 'name' => $gym->name],
            'people' => $people,
        ]);
    }
}
