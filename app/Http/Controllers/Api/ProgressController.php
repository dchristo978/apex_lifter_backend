<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Machine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProgressController extends Controller
{
    /**
     * Personal estimated-1RM progression on one machine over time (MVP 2 #3).
     *
     * Returns one point per day: the lifter's best estimated 1RM that day, so
     * the mobile chart plots a clean strength-over-time curve.
     */
    public function show(Request $request, Machine $machine): JsonResponse
    {
        $points = $request->user()
            ->workoutSets()
            ->where('machine_id', $machine->id)
            ->orderBy('performed_at')
            ->get()
            ->groupBy(fn ($set) => $set->performed_at->toDateString())
            ->map(function ($sets, $day) {
                $best = $sets->sortByDesc('estimated_1rm')->first();

                return [
                    'date' => $day,
                    'estimated_1rm' => $best->estimated_1rm,
                    'weight_kg' => $best->weight_kg,
                    'reps' => $best->reps,
                ];
            })
            ->values();

        return response()->json([
            'machine' => ['id' => $machine->id, 'name' => $machine->name],
            'points' => $points,
        ]);
    }
}
