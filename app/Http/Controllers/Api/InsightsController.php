<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorkoutSet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Long-term retention insights (MVP 4): a GitHub-style training-frequency
 * heatmap, a muscle-balance / activation summary over a trailing window, and
 * strength standards that place the lifter's big lifts against population norms.
 */
class InsightsController extends Controller
{
    /**
     * The major muscle groups tracked for balance. Coarser buckets like
     * "Full Body" / "Other" and small assist groups are intentionally left out
     * so the radar reads cleanly and "neglected" means a real training gap.
     *
     * @var list<string>
     */
    private const BALANCE_GROUPS = [
        'Chest', 'Upper Back', 'Lats', 'Lower Back', 'Traps', 'Shoulders',
        'Biceps', 'Triceps', 'Forearms', 'Quadriceps', 'Hamstrings',
        'Glutes', 'Calves', 'Abdominals',
    ];

    /**
     * Strength-standard thresholds as a multiple of body weight, per lift and
     * sex, in ascending level order: Beginner, Novice, Intermediate, Advanced,
     * Elite. Below the first threshold the lifter is "Untrained". Ratios follow
     * widely used barbell strength-standard tables and are a reasonable MVP.
     *
     * @var array<string, array{male: list<float>, female: list<float>}>
     */
    private const STANDARDS = [
        'Bench Press (Barbell)' => [
            'male' => [0.5, 0.75, 1.0, 1.5, 2.0],
            'female' => [0.25, 0.5, 0.75, 1.0, 1.5],
        ],
        'Squat (Barbell)' => [
            'male' => [0.75, 1.25, 1.5, 2.25, 2.75],
            'female' => [0.5, 0.75, 1.25, 1.75, 2.25],
        ],
        'Deadlift (Barbell)' => [
            'male' => [1.0, 1.5, 2.0, 2.5, 3.0],
            'female' => [0.5, 1.0, 1.25, 2.0, 2.5],
        ],
        'Overhead Press (Barbell)' => [
            'male' => [0.35, 0.55, 0.8, 1.1, 1.4],
            'female' => [0.2, 0.35, 0.5, 0.75, 1.0],
        ],
    ];

    private const LEVEL_NAMES = ['Beginner', 'Novice', 'Intermediate', 'Advanced', 'Elite'];

    /**
     * One count per calendar day the lifter logged a set, over the trailing
     * ~53 weeks, for a GitHub-contributions-style heatmap. Only non-empty days
     * are sent; the client fills the rest of the grid with zeros.
     */
    public function heatmap(Request $request): JsonResponse
    {
        $start = now()->subDays(370)->startOfDay();

        $days = $request->user()
            ->workoutSets()
            ->where('performed_at', '>=', $start)
            ->get(['performed_at'])
            ->groupBy(fn (WorkoutSet $set) => $set->performed_at->toDateString())
            ->map(fn ($sets, $day) => ['date' => $day, 'count' => $sets->count()])
            ->values();

        return response()->json([
            'start' => $start->toDateString(),
            'end' => now()->toDateString(),
            'days' => $days,
        ]);
    }

    /**
     * Per-muscle-group training volume over a trailing window (default 7 days,
     * capped at 90). Powers both the profile's 3D muscle model (7-day window)
     * and the insights radar (30-day window). "trained" lights up the model;
     * "neglected" is the balance gap — e.g. skipped leg day for three weeks.
     */
    public function muscleActivation(Request $request): JsonResponse
    {
        $days = (int) $request->integer('days', 7);
        $days = max(1, min($days, 90));
        $since = now()->subDays($days)->startOfDay();

        $sets = $request->user()
            ->workoutSets()
            ->with('machine:id,muscle_group')
            ->where('performed_at', '>=', $since)
            ->get();

        $groups = $sets
            ->filter(fn (WorkoutSet $set) => $set->machine?->muscle_group !== null)
            ->groupBy(fn (WorkoutSet $set) => $set->machine->muscle_group)
            ->map(function ($groupSets, $group) {
                $last = $groupSets->max('performed_at');

                return [
                    'group' => $group,
                    'sets' => $groupSets->count(),
                    'volume_kg' => round($groupSets->sum(fn (WorkoutSet $s) => $s->weight_kg * $s->reps), 1),
                    'last_trained' => $last->toDateString(),
                    'days_since' => (int) $last->startOfDay()->diffInDays(now()->startOfDay()),
                ];
            })
            ->values();

        $trained = $groups->pluck('group')->all();
        $neglected = array_values(array_diff(self::BALANCE_GROUPS, $trained));

        return response()->json([
            'days' => $days,
            'since' => $since->toDateString(),
            'balance_groups' => self::BALANCE_GROUPS,
            'groups' => $groups,
            'trained' => $trained,
            'neglected' => $neglected,
        ]);
    }

    /**
     * The lifter's best estimated 1RM on the four barbell lifts we hold
     * standards for, placed against body-weight-relative population norms.
     * Needs body weight and sex; returns needs_profile when either is missing.
     */
    public function strengthStandards(Request $request): JsonResponse
    {
        $user = $request->user();
        $bodyWeight = $user->body_weight_kg;
        $sex = $user->gender;

        if ($bodyWeight === null || $bodyWeight <= 0 || ! in_array($sex, ['male', 'female'], true)) {
            return response()->json(['needs_profile' => true, 'lifts' => []]);
        }

        // Best estimated 1RM per machine among the standard lifts, in one query.
        $bests = $user->workoutSets()
            ->with('machine:id,name')
            ->get()
            ->filter(fn (WorkoutSet $set) => isset(self::STANDARDS[$set->machine?->name]))
            ->groupBy(fn (WorkoutSet $set) => $set->machine->name)
            ->map(fn ($sets) => $sets->sortByDesc('estimated_1rm')->first());

        $lifts = [];
        foreach (self::STANDARDS as $name => $bySex) {
            $best = $bests->get($name);
            if ($best === null) {
                continue;
            }

            $thresholds = $bySex[$sex];
            $oneRm = $best->estimated_1rm;
            $ratio = $oneRm / $bodyWeight;

            // Level index = how many thresholds the ratio meets (0 = Untrained).
            $reached = 0;
            foreach ($thresholds as $mult) {
                if ($ratio >= $mult) {
                    $reached++;
                }
            }

            $levelName = $reached === 0 ? 'Untrained' : self::LEVEL_NAMES[$reached - 1];
            $nextName = $reached < count(self::LEVEL_NAMES) ? self::LEVEL_NAMES[$reached] : null;
            $nextTarget = $nextName === null ? null : round($thresholds[$reached] * $bodyWeight, 1);

            $lifts[] = [
                'machine_id' => $best->machine->id,
                'name' => $name,
                'best_1rm' => round($oneRm, 1),
                'ratio' => round($ratio, 2),
                'level' => $levelName,
                'level_index' => $reached,
                'next_level' => $nextName,
                'next_target_kg' => $nextTarget,
                'thresholds_kg' => array_map(fn (float $m) => round($m * $bodyWeight, 1), $thresholds),
                'level_names' => self::LEVEL_NAMES,
            ];
        }

        return response()->json([
            'needs_profile' => false,
            'body_weight_kg' => $bodyWeight,
            'gender' => $sex,
            'lifts' => $lifts,
        ]);
    }
}
