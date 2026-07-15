<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\WorkoutSet;
use App\Services\LeaderboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkoutSetController extends Controller
{
    public function __construct(private readonly LeaderboardService $leaderboard) {}

    public function index(Request $request): JsonResponse
    {
        $sets = $request->user()
            ->workoutSets()
            ->with(['machine:id,name,category', 'gym:id,name'])
            ->when($request->query('machine_id'), fn ($q, $id) => $q->where('machine_id', $id))
            ->latest('performed_at')
            ->limit(100)
            ->get();

        return response()->json(['workout_sets' => $sets]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'machine_id' => ['required', 'exists:machines,id'],
            'gym_id' => ['nullable', 'exists:gyms,id'],
            'weight_kg' => ['required', 'numeric', 'min:0.5', 'max:1500'],
            'reps' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $userId = $request->user()->id;

        // A set beats the lifter's previous best weight on this machine → PR.
        // Read the prior max before inserting so the new set can't count itself.
        $previousBest = WorkoutSet::query()
            ->where('user_id', $userId)
            ->where('machine_id', $data['machine_id'])
            ->max('weight_kg');

        $set = WorkoutSet::create([
            ...$data,
            'user_id' => $userId,
            'estimated_1rm' => WorkoutSet::epley((float) $data['weight_kg'], (int) $data['reps']),
            'performed_at' => now(),
        ]);

        $this->leaderboard->notifyOvertaken($set);

        if ($previousBest === null || (float) $data['weight_kg'] > (float) $previousBest) {
            $set->loadMissing('machine:id,name');
            Activity::record($userId, Activity::TYPE_PR, $set->id, [
                'machine_id' => $set->machine_id,
                'machine_name' => $set->machine?->name,
                'weight_kg' => (float) $set->weight_kg,
                'reps' => (int) $set->reps,
                'estimated_1rm' => (float) $set->estimated_1rm,
            ]);
        }

        return response()->json([
            'workout_set' => $set->unsetRelation('user')->load('machine:id,name,category'),
        ], 201);
    }

    public function destroy(Request $request, WorkoutSet $workoutSet): JsonResponse
    {
        if ($workoutSet->user_id !== $request->user()->id) {
            abort(403, 'Kamu hanya bisa menghapus set milikmu sendiri.');
        }

        if (! $workoutSet->isEditable()) {
            abort(403, sprintf(
                'Set hanya bisa dihapus dalam %d menit setelah dicatat.',
                WorkoutSet::EDIT_WINDOW_MINUTES,
            ));
        }

        $workoutSet->delete();

        return response()->json(['deleted' => true]);
    }
}
