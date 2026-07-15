<?php

namespace App\Services;

use App\Models\RankNotification;
use App\Models\WorkoutSet;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class LeaderboardService
{
    public function __construct(private readonly PushNotifier $push) {}

    /**
     * Ranked leaderboard for one machine.
     *
     * type   : 'single' (true 1RM, reps == 1, ranked by weight)
     *          'multi'  (reps >= 2, ranked by estimated 1RM via Epley)
     * period : 'weekly' | 'monthly'
     *
     * Optional filters: gender, age_bracket, weight_class — applied on the
     * lifter's current profile attributes.
     *
     * @return Collection<int, array<string, mixed>> one row per user, best set only, rank ascending
     */
    public function rankings(
        int $machineId,
        string $type = 'multi',
        string $period = 'weekly',
        ?string $gender = null,
        ?string $ageBracket = null,
        ?string $weightClass = null,
    ): Collection {
        $query = WorkoutSet::query()
            ->with('user:id,name,gender,birth_date,body_weight_kg')
            ->where('machine_id', $machineId)
            ->where('performed_at', '>=', self::periodStart($period));

        if ($type === 'single') {
            $query->where('reps', 1);
        } else {
            $query->where('reps', '>=', 2);
        }

        if ($gender !== null) {
            $query->whereHas('user', fn ($q) => $q->where('gender', $gender));
        }

        if ($ageBracket !== null) {
            [$from, $to] = self::birthDateRange($ageBracket);
            $query->whereHas('user', function ($q) use ($from, $to) {
                if ($from !== null) {
                    $q->where('birth_date', '>', $from);
                }
                if ($to !== null) {
                    $q->where('birth_date', '<=', $to);
                }
            });
        }

        if ($weightClass !== null) {
            [$min, $max] = self::weightRange($weightClass);
            $query->whereHas('user', function ($q) use ($min, $max) {
                if ($min !== null) {
                    $q->where('body_weight_kg', '>=', $min);
                }
                if ($max !== null) {
                    $q->where('body_weight_kg', '<', $max);
                }
            });
        }

        $metric = $type === 'single' ? 'weight_kg' : 'estimated_1rm';

        return $query->get()
            ->groupBy('user_id')
            ->map(fn (Collection $sets) => $sets->sortByDesc($metric)->first())
            ->sortByDesc($metric)
            ->values()
            ->map(fn (WorkoutSet $set, int $index) => [
                'rank' => $index + 1,
                'user_id' => $set->user_id,
                'user_name' => $set->user->name,
                'value' => $type === 'single' ? $set->weight_kg : $set->estimated_1rm,
                'weight_kg' => $set->weight_kg,
                'reps' => $set->reps,
                'performed_at' => $set->performed_at->toIso8601String(),
            ]);
    }

    /**
     * Notify users in the same default category (machine + gender + set type,
     * weekly period) whose best was just surpassed by this set.
     *
     * Rate-limited to one notification per user per machine per day.
     */
    public function notifyOvertaken(WorkoutSet $set): void
    {
        $lifter = $set->user;
        $type = $set->reps === 1 ? 'single' : 'multi';
        $metric = $type === 'single' ? 'weight_kg' : 'estimated_1rm';
        $newValue = $set->{$metric};

        // The lifter's previous best in this period (excluding this set).
        $previousBest = (float) WorkoutSet::query()
            ->where('user_id', $lifter->id)
            ->where('machine_id', $set->machine_id)
            ->where('id', '!=', $set->id)
            ->where('performed_at', '>=', self::periodStart('weekly'))
            ->where('reps', $type === 'single' ? '=' : '>=', $type === 'single' ? 1 : 2)
            ->max($metric);

        if ($newValue <= $previousBest) {
            return; // Not a new personal best for the period; nobody's rank dropped.
        }

        $rankings = $this->rankings($set->machine_id, $type, 'weekly', $lifter->gender);

        $overtaken = $rankings
            ->filter(fn (array $row) => $row['user_id'] !== $lifter->id
                && $row['value'] < $newValue
                && $row['value'] >= $previousBest);

        foreach ($overtaken as $row) {
            $alreadyNotifiedToday = RankNotification::query()
                ->where('user_id', $row['user_id'])
                ->where('machine_id', $set->machine_id)
                ->where('created_at', '>=', now()->startOfDay())
                ->exists();

            if ($alreadyNotifiedToday) {
                continue;
            }

            $notification = RankNotification::create([
                'user_id' => $row['user_id'],
                'machine_id' => $set->machine_id,
                'overtaken_by_user_id' => $lifter->id,
                'title' => 'Rekormu baru saja terkalahkan!',
                'body' => sprintf(
                    '%s baru saja mengangkat %.1f kg x %d reps di %s dan melewati posisimu.',
                    $lifter->name,
                    $set->weight_kg,
                    $set->reps,
                    $set->machine->name,
                ),
            ]);

            $this->sendPush($notification);
        }
    }

    private function sendPush(RankNotification $notification): void
    {
        $this->push->notify($notification);
    }

    public static function periodStart(string $period): CarbonInterface
    {
        return $period === 'monthly' ? now()->startOfMonth() : now()->startOfWeek();
    }

    /**
     * @return array{0: ?CarbonInterface, 1: ?CarbonInterface} [exclusive lower bound, inclusive upper bound] on birth_date
     */
    private static function birthDateRange(string $bracket): array
    {
        // Older person => earlier birth date. "age < 30" => birth_date > (today - 30y).
        return match ($bracket) {
            'u18' => [now()->subYears(18), null],
            '18-29' => [now()->subYears(30), now()->subYears(18)],
            '30-39' => [now()->subYears(40), now()->subYears(30)],
            '40+' => [null, now()->subYears(40)],
            default => [null, null],
        };
    }

    /**
     * @return array{0: ?float, 1: ?float} [inclusive min, exclusive max] body weight kg
     */
    private static function weightRange(string $class): array
    {
        return match ($class) {
            'u60' => [null, 60.0],
            '60-74' => [60.0, 75.0],
            '75-89' => [75.0, 90.0],
            '90+' => [90.0, null],
            default => [null, null],
        };
    }
}
