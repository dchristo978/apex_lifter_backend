<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeedController extends Controller
{
    private const PER_PAGE = 20;

    /**
     * The home activity feed: recent PRs, medals, and check-ins from every
     * lifter the viewer follows, plus their own, newest first. Paginated by
     * page number for infinite scroll on mobile.
     */
    public function index(Request $request): JsonResponse
    {
        $me = $request->user();
        $page = max(1, (int) $request->query('page', 1));

        // Followed lifters plus self — so a lifter's own actions show up too.
        $authorIds = $me->following()->pluck('users.id')->push($me->id)->unique();

        $paginator = Activity::query()
            ->with('user:id,name,avatar_path')
            ->withCount(['kudos', 'comments'])
            // Whether the viewer has already given kudos, resolved in one query.
            ->withExists(['kudos as viewer_kudoed' => fn ($q) => $q->where('user_id', $me->id)])
            ->whereIn('user_id', $authorIds)
            ->latest('created_at')
            ->latest('id')
            ->paginate(self::PER_PAGE, ['*'], 'page', $page);

        return response()->json([
            'data' => collect($paginator->items())
                ->map(fn (Activity $a) => $this->present($a))
                ->all(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
            'has_more' => $paginator->hasMorePages(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Activity $a): array
    {
        $user = $a->user;

        return [
            'id' => $a->id,
            'type' => $a->type,
            'created_at' => $a->created_at->toIso8601String(),
            'actor' => $user === null ? null : [
                'id' => $user->id,
                'name' => $user->name,
                'avatar_url' => $user->avatarUrl(),
            ],
            'meta' => $a->meta ?? [],
            'kudos_count' => (int) ($a->kudos_count ?? 0),
            'comment_count' => (int) ($a->comments_count ?? 0),
            'viewer_kudoed' => (bool) ($a->viewer_kudoed ?? false),
        ];
    }
}
