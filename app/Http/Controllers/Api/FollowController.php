<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Checkin;
use App\Models\RankNotification;
use App\Models\User;
use App\Services\PushNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FollowController extends Controller
{
    public function __construct(private readonly PushNotifier $push) {}

    /**
     * Follow another lifter. Idempotent: following someone already followed
     * is a no-op that still returns the current state.
     */
    public function store(Request $request, User $user): JsonResponse
    {
        $me = $request->user();

        if ($me->id === $user->id) {
            abort(422, 'Kamu tidak bisa mengikuti dirimu sendiri.');
        }

        $alreadyFollowing = $me->isFollowing($user);

        // syncWithoutDetaching keeps the call idempotent under the unique index.
        $me->following()->syncWithoutDetaching([$user->id]);

        // Notify the followee only on a genuinely new follow, so a double-tap
        // doesn't spam them.
        if (! $alreadyFollowing) {
            $this->notifyNewFollower($me, $user);
        }

        return $this->state($me, $user);
    }

    /**
     * Drop an in-app "new follower" notification (best-effort push on top),
     * deep-linking to the follower's profile via overtaken_by_user_id.
     */
    private function notifyNewFollower(User $follower, User $followee): void
    {
        $notification = RankNotification::create([
            'user_id' => $followee->id,
            'type' => 'new_follower',
            'machine_id' => null,
            'overtaken_by_user_id' => $follower->id,
            'title' => 'New follower 👥',
            'body' => "{$follower->name} started following you.",
        ]);

        $this->push->notify($notification);
    }

    /**
     * Unfollow a lifter. Idempotent.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        $me = $request->user();
        $me->following()->detach($user->id);

        return $this->state($me, $user);
    }

    /**
     * Lifters who follow $user, newest follow first.
     */
    public function followers(User $user): JsonResponse
    {
        return response()->json([
            'users' => $user->followers()
                ->orderByPivot('created_at', 'desc')
                ->get()
                ->map(fn (User $u) => $this->card($u))
                ->all(),
        ]);
    }

    /**
     * Lifters $user follows, newest follow first.
     */
    public function following(User $user): JsonResponse
    {
        return response()->json([
            'users' => $user->following()
                ->orderByPivot('created_at', 'desc')
                ->get()
                ->map(fn (User $u) => $this->card($u))
                ->all(),
        ]);
    }

    /**
     * "Who to follow" suggestions for the viewer: lifters they don't already
     * follow (and aren't themselves), ranked with anyone at their home gym
     * first, then by popularity (follower count). Each card carries a short
     * reason so the UI can explain the suggestion.
     */
    public function suggestions(Request $request): JsonResponse
    {
        $me = $request->user();

        $excluded = $me->following()->pluck('users.id')->push($me->id);
        $homeGymId = $me->homeGym()?->id;

        // Lifters currently checked-in at the viewer's home gym recently share a
        // room — the strongest signal we have without a full social graph.
        $gymMateIds = collect();
        if ($homeGymId !== null) {
            $gymMateIds = Checkin::query()
                ->where('gym_id', $homeGymId)
                ->whereNotIn('user_id', $excluded)
                ->distinct()
                ->pluck('user_id');
        }

        $suggestions = User::query()
            ->whereNotIn('id', $excluded)
            ->withCount('followers')
            ->orderByDesc('followers_count')
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            // Gym-mates float to the top while keeping the popularity order
            // within each group.
            ->sortByDesc(fn (User $u) => $gymMateIds->contains($u->id) ? 1 : 0)
            ->take(10)
            ->map(fn (User $u) => [
                ...$this->card($u),
                'followers_count' => (int) $u->followers_count,
                'reason' => $gymMateIds->contains($u->id) ? 'gym' : 'popular',
            ])
            ->values()
            ->all();

        return response()->json(['users' => $suggestions]);
    }

    private function state(User $me, User $target): JsonResponse
    {
        return response()->json([
            'is_following' => $me->isFollowing($target),
            'followers_count' => $target->followers()->count(),
            'following_count' => $target->following()->count(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function card(User $u): array
    {
        return [
            'id' => $u->id,
            'name' => $u->name,
            'avatar_url' => $u->avatarUrl(),
        ];
    }
}
