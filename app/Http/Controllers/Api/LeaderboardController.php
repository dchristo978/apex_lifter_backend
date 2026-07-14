<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LeaderboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeaderboardController extends Controller
{
    public function __construct(private readonly LeaderboardService $leaderboard) {}

    public function show(Request $request): JsonResponse
    {
        $data = $request->validate([
            'machine_id' => ['required', 'exists:machines,id'],
            'type' => ['sometimes', Rule::in(['single', 'multi'])],
            'period' => ['sometimes', Rule::in(['weekly', 'monthly'])],
            'gender' => ['sometimes', 'nullable', Rule::in(['male', 'female'])],
            'age_bracket' => ['sometimes', 'nullable', Rule::in(['u18', '18-29', '30-39', '40+'])],
            'weight_class' => ['sometimes', 'nullable', Rule::in(['u60', '60-74', '75-89', '90+'])],
        ]);

        $rankings = $this->leaderboard->rankings(
            machineId: (int) $data['machine_id'],
            type: $data['type'] ?? 'multi',
            period: $data['period'] ?? 'weekly',
            gender: $data['gender'] ?? null,
            ageBracket: $data['age_bracket'] ?? null,
            weightClass: $data['weight_class'] ?? null,
        );

        $myRank = $rankings->firstWhere('user_id', $request->user()->id)['rank'] ?? null;

        return response()->json([
            'entries' => $rankings,
            'my_rank' => $myRank,
        ]);
    }
}
