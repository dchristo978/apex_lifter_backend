<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WorkoutSet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    /**
     * Public profile (MVP 3 #3): identity, home gym, and headline stats.
     * Champion badges arrive with the MVP 2 leaderboard archives; until then
     * the collection is empty.
     */
    public function show(User $user): JsonResponse
    {
        $sets = $user->workoutSets();

        $homeGym = $user->homeGym();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'avatar_url' => $user->avatarUrl(),
                'gender' => $user->gender,
                'age_bracket' => $user->ageBracket(),
                'weight_class' => $user->weightClass(),
                'body_weight_stale' => $user->bodyWeightStale(),
                'home_gym' => $homeGym === null ? null : [
                    'id' => $homeGym->id,
                    'name' => $homeGym->name,
                ],
                'stats' => [
                    'total_sets' => (clone $sets)->count(),
                    'total_volume_kg' => round((float) (clone $sets)->sum(DB::raw('weight_kg * reps')), 1),
                    'machines_trained' => (clone $sets)->distinct('machine_id')->count('machine_id'),
                    'best_estimated_1rm' => round((float) (clone $sets)->max('estimated_1rm'), 1),
                ],
                'records' => $this->heaviestRecords($user),
                'badges' => [],
            ],
        ]);
    }

    /**
     * The lifter's heaviest lift on each machine they've trained. The single
     * heaviest set per machine (by weight, then estimated 1RM, then recency)
     * becomes that machine's record. Machines the lifter has pinned as
     * "featured" are moved to the front in their chosen order; the rest follow,
     * heaviest first.
     *
     * @return array<int, array<string, mixed>>
     */
    private function heaviestRecords(User $user): array
    {
        $records = WorkoutSet::query()
            ->with('machine:id,name,brand')
            ->where('user_id', $user->id)
            ->orderByDesc('weight_kg')
            ->orderByDesc('estimated_1rm')
            ->orderByDesc('performed_at')
            ->get()
            // Ordering above means the first set seen for a machine is its record.
            ->unique('machine_id')
            ->filter(fn (WorkoutSet $set) => $set->machine !== null)
            ->map(fn (WorkoutSet $set) => [
                'machine_id' => $set->machine_id,
                'machine_name' => $set->machine->name,
                'machine_brand' => $set->machine->brand,
                'weight_kg' => $set->weight_kg,
                'reps' => $set->reps,
                'estimated_1rm' => $set->estimated_1rm,
                'performed_at' => $set->performed_at->toIso8601String(),
            ]);

        $featured = $user->featuredMachineIds();

        return $records
            ->sortBy(function (array $record) use ($featured) {
                $position = array_search($record['machine_id'], $featured, true);

                // Featured machines keep their chosen order (0, 1, 2, ...);
                // everyone else sorts after them while preserving weight order.
                return $position === false ? PHP_INT_MAX : $position;
            })
            ->values()
            ->all();
    }

    /**
     * Paginated gym-session history for lazy loading. A "session" is one
     * calendar day of sets, newest first.
     */
    public function sessions(Request $request, User $user): JsonResponse
    {
        $perPage = 10;
        $page = max(1, (int) $request->query('page', 1));

        $sessions = WorkoutSet::query()
            ->with(['machine:id,name', 'gym:id,name'])
            ->where('user_id', $user->id)
            ->orderByDesc('performed_at')
            ->get()
            ->groupBy(fn (WorkoutSet $set) => $set->performed_at->toDateString())
            ->map(function ($sets, $day) {
                $top = $sets->sortByDesc('estimated_1rm')->first();

                return [
                    'date' => $day,
                    'gym_name' => $sets->pluck('gym')->filter()->first()?->name,
                    'set_count' => $sets->count(),
                    'total_volume_kg' => round($sets->sum(fn (WorkoutSet $s) => $s->weight_kg * $s->reps), 1),
                    'top_machine' => $top->machine?->name,
                    'top_estimated_1rm' => $top->estimated_1rm,
                ];
            })
            ->values();

        $total = $sessions->count();

        return response()->json([
            'data' => $sessions->forPage($page, $perPage)->values(),
            'current_page' => $page,
            'last_page' => (int) max(1, ceil($total / $perPage)),
            'total' => $total,
            'has_more' => $page * $perPage < $total,
        ]);
    }
}
