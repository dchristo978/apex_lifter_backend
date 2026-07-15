<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Challenge;
use App\Models\ChallengeVote;
use App\Models\Checkin;
use App\Models\User;
use App\Services\ChallengeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ChallengeController extends Controller
{
    public function __construct(private readonly ChallengeService $service) {}

    /** Challenges the current user is involved in (as challenger or opponent). */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $challenges = Challenge::query()
            ->with(['challenger', 'opponent', 'machine', 'gym', 'winner'])
            ->where(fn ($q) => $q->where('challenger_id', $user->id)
                ->orWhere('opponent_id', $user->id))
            ->latest()
            ->limit(100)
            ->get();

        return response()->json([
            'challenges' => $challenges->map(fn ($c) => $this->serialize($c, $user))->all(),
        ]);
    }

    /** Completed challenges the user won — their medal history. */
    public function history(Request $request): JsonResponse
    {
        $user = $request->user();

        $challenges = Challenge::query()
            ->with(['challenger', 'opponent', 'machine', 'gym', 'winner'])
            ->where('status', Challenge::STATUS_COMPLETED)
            ->where(fn ($q) => $q->where('challenger_id', $user->id)
                ->orWhere('opponent_id', $user->id))
            ->latest('resolved_at')
            ->get();

        return response()->json([
            'medals' => $user->medalsCount(),
            'challenges' => $challenges->map(fn ($c) => $this->serialize($c, $user))->all(),
        ]);
    }

    /** Active challenges open for community judging in the arena. */
    public function arena(Request $request): JsonResponse
    {
        $user = $request->user();

        $challenges = Challenge::query()
            ->with(['challenger', 'opponent', 'machine', 'gym', 'winner'])
            ->where('status', Challenge::STATUS_ACTIVE)
            ->latest('voting_ends_at')
            ->limit(100)
            ->get();

        return response()->json([
            'challenges' => $challenges->map(fn ($c) => $this->serialize($c, $user))->all(),
            'reason_codes' => ChallengeVote::REASON_CODES,
        ]);
    }

    public function show(Request $request, Challenge $challenge): JsonResponse
    {
        $challenge->load(['challenger', 'opponent', 'machine', 'gym', 'winner']);

        return response()->json([
            'challenge' => $this->serialize($challenge, $request->user()),
            'reason_codes' => ChallengeVote::REASON_CODES,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'opponent_id' => ['required', 'integer', Rule::exists('users', 'id'), Rule::notIn([$user->id])],
            'machine_id' => ['required', 'integer', Rule::exists('machines', 'id')],
            'target_weight_kg' => ['required', 'numeric', 'min:1', 'max:1000'],
            'target_reps' => ['required', 'integer', 'min:1', 'max:100'],
            'target_sets' => ['required', 'integer', 'min:1', 'max:20'],
        ]);

        $challenge = Challenge::create([
            'challenger_id' => $user->id,
            'opponent_id' => $data['opponent_id'],
            'machine_id' => $data['machine_id'],
            'gym_id' => $user->homeGym()?->id,
            'target_weight_kg' => $data['target_weight_kg'],
            'target_reps' => $data['target_reps'],
            'target_sets' => $data['target_sets'],
            'status' => Challenge::STATUS_PENDING,
        ]);

        $challenge->load(['challenger', 'opponent', 'machine', 'gym', 'winner']);

        $this->service->notify(
            User::find($data['opponent_id']),
            $challenge,
            'challenge_received',
            'You\'ve been challenged! 💪',
            "{$user->name} challenged you: {$challenge->target_weight_kg} kg × "
                ."{$challenge->target_reps} reps × {$challenge->target_sets} sets on "
                .($challenge->machine?->name ?? 'a machine').'. Record your proof to accept!',
        );

        return response()->json(['challenge' => $this->serialize($challenge, $user)], 201);
    }

    /** Upload the current participant's proof video. */
    public function submitVideo(Request $request, Challenge $challenge): JsonResponse
    {
        $user = $request->user();

        if (! $challenge->isParticipant($user->id)) {
            abort(403, 'Only participants can submit proof.');
        }
        if (! in_array($challenge->status, [Challenge::STATUS_PENDING, Challenge::STATUS_ACTIVE], true)) {
            abort(422, 'This challenge is no longer accepting proof.');
        }

        $request->validate([
            'video' => ['required', 'file', 'mimetypes:video/mp4,video/quicktime,video/3gpp', 'max:102400'],
        ]);

        $isChallenger = $challenge->challenger_id === $user->id;
        $role = $isChallenger ? 'challenger' : 'opponent';
        $path = $request->file('video')->store("challenges/{$challenge->id}", 'public');

        // Remove a previously uploaded video for this role, if any.
        $old = $isChallenger ? $challenge->challenger_video_path : $challenge->opponent_video_path;
        if ($old !== null) {
            Storage::disk('public')->delete($old);
        }

        $challenge->update([
            "{$role}_video_path" => $path,
            "{$role}_submitted_at" => now(),
        ]);

        // Once both proofs are in, open the arena for 48h.
        $challenge->refresh();
        if ($challenge->status === Challenge::STATUS_PENDING
            && $challenge->challenger_video_path !== null
            && $challenge->opponent_video_path !== null) {
            $challenge->update([
                'status' => Challenge::STATUS_ACTIVE,
                'voting_ends_at' => now()->addHours(Challenge::VOTING_HOURS),
            ]);

            foreach ([$challenge->challenger, $challenge->opponent] as $participant) {
                $this->service->notify(
                    $participant,
                    $challenge,
                    'challenge_active',
                    'Challenge is live in the Arena! 🏟️',
                    'Both proofs are in. The gym is now judging your challenge.',
                );
            }
        }

        $challenge->load(['challenger', 'opponent', 'machine', 'gym', 'winner']);

        return response()->json(['challenge' => $this->serialize($challenge, $user)]);
    }

    public function decline(Request $request, Challenge $challenge): JsonResponse
    {
        $user = $request->user();

        if ($challenge->opponent_id !== $user->id) {
            abort(403, 'Only the challenged lifter can decline.');
        }
        if ($challenge->status !== Challenge::STATUS_PENDING) {
            abort(422, 'This challenge can no longer be declined.');
        }

        $challenge->update(['status' => Challenge::STATUS_DECLINED]);

        $this->service->notify(
            $challenge->challenger,
            $challenge,
            'challenge_declined',
            'Challenge declined',
            "{$user->name} declined your challenge.",
        );

        $challenge->load(['challenger', 'opponent', 'machine', 'gym', 'winner']);

        return response()->json(['challenge' => $this->serialize($challenge, $user)]);
    }

    public function cancel(Request $request, Challenge $challenge): JsonResponse
    {
        $user = $request->user();

        if ($challenge->challenger_id !== $user->id) {
            abort(403, 'Only the challenger can cancel.');
        }
        if ($challenge->status !== Challenge::STATUS_PENDING) {
            abort(422, 'This challenge can no longer be cancelled.');
        }

        $challenge->update(['status' => Challenge::STATUS_CANCELLED]);
        $challenge->load(['challenger', 'opponent', 'machine', 'gym', 'winner']);

        return response()->json(['challenge' => $this->serialize($challenge, $user)]);
    }

    /** Cast (or update) an arena judgement. */
    public function vote(Request $request, Challenge $challenge): JsonResponse
    {
        $user = $request->user();

        if ($challenge->status !== Challenge::STATUS_ACTIVE) {
            abort(422, 'This challenge is not open for voting.');
        }
        if ($challenge->isParticipant($user->id)) {
            abort(403, 'Participants cannot vote on their own challenge.');
        }
        if (! $this->canJudgeGym($challenge, $user)) {
            abort(403, 'Only lifters from this gym can judge this challenge.');
        }

        $data = $request->validate([
            'choice' => ['required', Rule::in([
                ChallengeVote::CHOICE_CHALLENGER,
                ChallengeVote::CHOICE_OPPONENT,
                ChallengeVote::CHOICE_INVALID,
            ])],
            'criteria' => ['nullable', 'array'],
            'criteria.load' => ['boolean'],
            'criteria.form' => ['boolean'],
            'criteria.machine' => ['boolean'],
            'criteria.reps_sets' => ['boolean'],
            'reason_code' => ['nullable', Rule::in(ChallengeVote::REASON_CODES)],
            'reason_text' => ['nullable', 'string', 'max:500'],
        ]);

        // A rejection (or any failed criterion) must carry a reason.
        $criteria = $data['criteria'] ?? [];
        $anyRejected = $data['choice'] === ChallengeVote::CHOICE_INVALID
            || in_array(false, array_map('boolval', $criteria), true);

        if ($anyRejected && empty($data['reason_code'])) {
            throw ValidationException::withMessages([
                'reason_code' => 'A reason is required when you reject the lift.',
            ]);
        }
        if (($data['reason_code'] ?? null) === 'other' && empty($data['reason_text'])) {
            throw ValidationException::withMessages([
                'reason_text' => 'Please describe your reason.',
            ]);
        }

        ChallengeVote::updateOrCreate(
            ['challenge_id' => $challenge->id, 'voter_id' => $user->id],
            [
                'choice' => $data['choice'],
                'criteria' => $criteria ?: null,
                'reason_code' => $data['reason_code'] ?? null,
                'reason_text' => $data['reason_text'] ?? null,
            ],
        );

        $challenge->load(['challenger', 'opponent', 'machine', 'gym', 'winner']);

        return response()->json(['challenge' => $this->serialize($challenge, $user)]);
    }

    private function canJudgeGym(Challenge $challenge, User $user): bool
    {
        if ($challenge->gym_id === null) {
            return true;
        }

        return Checkin::query()
            ->where('user_id', $user->id)
            ->where('gym_id', $challenge->gym_id)
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Challenge $challenge, User $viewer): array
    {
        $tally = $this->service->tally($challenge);
        $isParticipant = $challenge->isParticipant($viewer->id);

        $myVote = $isParticipant
            ? null
            : $challenge->votes()->where('voter_id', $viewer->id)->value('choice');

        $role = match (true) {
            $challenge->challenger_id === $viewer->id => 'challenger',
            $challenge->opponent_id === $viewer->id => 'opponent',
            default => 'judge',
        };

        $canVote = $challenge->status === Challenge::STATUS_ACTIVE
            && ! $isParticipant
            && $myVote === null
            && $this->canJudgeGym($challenge, $viewer);

        return [
            'id' => $challenge->id,
            'status' => $challenge->status,
            'machine' => $challenge->machine === null ? null : [
                'id' => $challenge->machine->id,
                'name' => $challenge->machine->name,
                'muscle_group' => $challenge->machine->muscle_group,
            ],
            'gym' => $challenge->gym === null ? null : [
                'id' => $challenge->gym->id,
                'name' => $challenge->gym->name,
            ],
            'target_weight_kg' => $challenge->target_weight_kg,
            'target_reps' => $challenge->target_reps,
            'target_sets' => $challenge->target_sets,
            'challenger' => $this->participant($challenge->challenger),
            'opponent' => $this->participant($challenge->opponent),
            'challenger_submitted' => $challenge->challenger_video_path !== null,
            'opponent_submitted' => $challenge->opponent_video_path !== null,
            // Proof videos are only exposed once judging (or after) begins.
            'challenger_video_url' => $challenge->status === Challenge::STATUS_PENDING
                ? null
                : $challenge->challengerVideoUrl(),
            'opponent_video_url' => $challenge->status === Challenge::STATUS_PENDING
                ? null
                : $challenge->opponentVideoUrl(),
            'voting_ends_at' => $challenge->voting_ends_at?->toIso8601String(),
            'winner_id' => $challenge->winner_id,
            'winner' => $this->participant($challenge->winner),
            'created_at' => $challenge->created_at?->toIso8601String(),
            'my_role' => $role,
            'i_submitted' => $role === 'challenger'
                ? $challenge->challenger_video_path !== null
                : ($role === 'opponent' ? $challenge->opponent_video_path !== null : false),
            'my_vote' => $myVote,
            'can_vote' => $canVote,
            'tally' => $tally,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function participant(?User $user): ?array
    {
        return $user === null ? null : [
            'id' => $user->id,
            'name' => $user->name,
            'avatar_url' => $user->avatarUrl(),
        ];
    }
}
