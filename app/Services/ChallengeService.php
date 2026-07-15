<?php

namespace App\Services;

use App\Models\Challenge;
use App\Models\ChallengeVote;
use App\Models\RankNotification;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ChallengeService
{
    public function __construct(private readonly PushNotifier $push) {}

    /**
     * Vote tally for a challenge.
     *
     * @return array{challenger:int, opponent:int, invalid:int, approvers:int, rejecters:int, total:int}
     */
    public function tally(Challenge $challenge): array
    {
        $counts = $challenge->votes()
            ->select('choice', DB::raw('COUNT(*) as total'))
            ->groupBy('choice')
            ->pluck('total', 'choice');

        $challenger = (int) ($counts[ChallengeVote::CHOICE_CHALLENGER] ?? 0);
        $opponent = (int) ($counts[ChallengeVote::CHOICE_OPPONENT] ?? 0);
        $invalid = (int) ($counts[ChallengeVote::CHOICE_INVALID] ?? 0);

        return [
            'challenger' => $challenger,
            'opponent' => $opponent,
            'invalid' => $invalid,
            'approvers' => $challenger + $opponent,
            'rejecters' => $invalid,
            'total' => $challenger + $opponent + $invalid,
        ];
    }

    /**
     * Resolve every active challenge whose 48h voting window has closed.
     *
     * A challenge completes only when approvers outnumber rejecters AND one
     * lifter has strictly more votes than the other. On a tie (or when
     * rejecters win) the window rolls forward another 48h — the challenge
     * "continues" until a clear winner emerges.
     *
     * @return int number of challenges completed this run
     */
    public function resolveDueChallenges(): int
    {
        $due = Challenge::query()
            ->where('status', Challenge::STATUS_ACTIVE)
            ->whereNotNull('voting_ends_at')
            ->where('voting_ends_at', '<=', now())
            ->get();

        $completed = 0;

        foreach ($due as $challenge) {
            $t = $this->tally($challenge);

            $hasMajority = $t['approvers'] > $t['rejecters'];
            $notTied = $t['challenger'] !== $t['opponent'];

            if ($hasMajority && $notTied) {
                $winnerId = $t['challenger'] > $t['opponent']
                    ? $challenge->challenger_id
                    : $challenge->opponent_id;

                $challenge->update([
                    'status' => Challenge::STATUS_COMPLETED,
                    'winner_id' => $winnerId,
                    'resolved_at' => now(),
                ]);

                $this->notifyResult($challenge->fresh());
                $completed++;
            } else {
                // Still contested — keep the arena open for another window.
                $challenge->update([
                    'voting_ends_at' => now()->addHours(Challenge::VOTING_HOURS),
                ]);
            }
        }

        return $completed;
    }

    private function notifyResult(Challenge $challenge): void
    {
        $winnerId = $challenge->winner_id;
        $loserId = $winnerId === $challenge->challenger_id
            ? $challenge->opponent_id
            : $challenge->challenger_id;

        $winner = User::find($winnerId);
        $loser = User::find($loserId);
        $machineName = $challenge->machine?->name ?? 'the machine';

        if ($winner !== null) {
            $this->notify(
                $winner,
                $challenge,
                'challenge_won',
                'You won a challenge! 🏅',
                "The arena crowned you winner on {$machineName}. A medal has been added to your profile.",
            );
        }

        if ($loser !== null) {
            $this->notify(
                $loser,
                $challenge,
                'challenge_lost',
                'Challenge decided',
                "The arena decided your {$machineName} challenge. Better luck next time — call for a rematch!",
            );
        }
    }

    /**
     * Create an in-app notification tied to a challenge and push it to the
     * recipient's device (best-effort; the feed entry is the source of truth).
     */
    public function notify(User $recipient, Challenge $challenge, string $type, string $title, string $body): void
    {
        $notification = RankNotification::create([
            'user_id' => $recipient->id,
            'type' => $type,
            'machine_id' => $challenge->machine_id,
            'challenge_id' => $challenge->id,
            'title' => $title,
            'body' => $body,
        ]);

        $this->push->notify($notification);
    }
}
