<?php

namespace Database\Seeders;

use App\Models\Challenge;
use App\Models\ChallengeVote;
use App\Models\Checkin;
use App\Models\Gym;
use App\Models\Machine;
use App\Models\RankNotification;
use App\Models\User;
use App\Models\WorkoutSet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Rich demo data for manual testing: a fixed roster of lifters with stable
 * logins, realistic sets across the popular machines, per-user gym check-ins,
 * and challenges in every state (pending, active-in-arena, completed with
 * medals) so every account has its own challenge history.
 *
 * Run with: php artisan db:seed --class=TestDataSeeder
 * Every account uses the password: password
 */
class TestDataSeeder extends Seeder
{
    /** Shared proof video created in storage/app/public/challenges/sample.mp4. */
    private const SAMPLE_VIDEO = 'challenges/sample.mp4';

    /** Popular machines and a sensible base working weight (kg). */
    private array $baseWeights = [
        'Bench Press (Barbell)' => 60,
        'Squat (Barbell)' => 90,
        'Deadlift (Barbell)' => 110,
        'Leg Press (Machine)' => 160,
        'Lat Pulldown (Cable)' => 55,
        'Chest Press (Machine)' => 55,
        'Seated Row (Machine)' => 55,
        'Shoulder Press (Dumbbell)' => 24,
        'Bicep Curl (Dumbbell)' => 14,
        'Triceps Pushdown' => 30,
        'Leg Extension (Machine)' => 65,
        'Seated Leg Curl (Machine)' => 50,
        'Lateral Raise (Dumbbell)' => 10,
        'Romanian Deadlift (Barbell)' => 80,
        'Hip Thrust (Barbell)' => 100,
        'Incline Bench Press (Dumbbell)' => 26,
        'Overhead Press (Barbell)' => 40,
        'Calf Extension (Machine)' => 70,
    ];

    /**
     * Fixed roster so every account has a stable login (<email> / password).
     * gym is an index into the seeded gyms: 0 is the "busy" gym where most of
     * the roster trains so the arena always has judges.
     *
     * @var array<int, array{name: string, email: string, gender: string, age: int, weight: int, gym: int}>
     */
    private array $roster = [
        ['name' => 'Demo Lifter', 'email' => 'demo@apex.test', 'gender' => 'male', 'age' => 28, 'weight' => 80, 'gym' => 0],
        ['name' => 'Rina Kuat', 'email' => 'rival@apex.test', 'gender' => 'female', 'age' => 26, 'weight' => 63, 'gym' => 0],
        ['name' => 'Coach Bram', 'email' => 'coach@apex.test', 'gender' => 'male', 'age' => 34, 'weight' => 92, 'gym' => 0],
        ['name' => 'Andre Wijaya', 'email' => 'andre@apex.test', 'gender' => 'male', 'age' => 24, 'weight' => 74, 'gym' => 0],
        ['name' => 'Bagas Pratama', 'email' => 'bagas@apex.test', 'gender' => 'male', 'age' => 30, 'weight' => 82, 'gym' => 0],
        ['name' => 'Citra Lestari', 'email' => 'citra@apex.test', 'gender' => 'female', 'age' => 27, 'weight' => 58, 'gym' => 0],
        ['name' => 'Dewi Anggraini', 'email' => 'dewi@apex.test', 'gender' => 'female', 'age' => 22, 'weight' => 55, 'gym' => 0],
        ['name' => 'Eko Saputra', 'email' => 'eko@apex.test', 'gender' => 'male', 'age' => 35, 'weight' => 88, 'gym' => 0],
        ['name' => 'Farhan Hidayat', 'email' => 'farhan@apex.test', 'gender' => 'male', 'age' => 21, 'weight' => 70, 'gym' => 0],
        ['name' => 'Gita Permata', 'email' => 'gita@apex.test', 'gender' => 'female', 'age' => 29, 'weight' => 61, 'gym' => 0],
        ['name' => 'Hendra Gunawan', 'email' => 'hendra@apex.test', 'gender' => 'male', 'age' => 41, 'weight' => 95, 'gym' => 0],
        ['name' => 'Intan Maharani', 'email' => 'intan@apex.test', 'gender' => 'female', 'age' => 25, 'weight' => 57, 'gym' => 0],
        ['name' => 'Joko Santoso', 'email' => 'joko@apex.test', 'gender' => 'male', 'age' => 38, 'weight' => 90, 'gym' => 0],
        ['name' => 'Kevin Tanuwijaya', 'email' => 'kevin@apex.test', 'gender' => 'male', 'age' => 23, 'weight' => 68, 'gym' => 0],
        ['name' => 'Laras Widya', 'email' => 'laras@apex.test', 'gender' => 'female', 'age' => 31, 'weight' => 64, 'gym' => 0],
        ['name' => 'Maya Puspita', 'email' => 'maya@apex.test', 'gender' => 'female', 'age' => 28, 'weight' => 60, 'gym' => 1],
        ['name' => 'Nanda Prakoso', 'email' => 'nanda@apex.test', 'gender' => 'male', 'age' => 26, 'weight' => 77, 'gym' => 1],
        ['name' => 'Oscar Simanjuntak', 'email' => 'oscar@apex.test', 'gender' => 'male', 'age' => 33, 'weight' => 85, 'gym' => 1],
        ['name' => 'Putri Ayu', 'email' => 'putri@apex.test', 'gender' => 'female', 'age' => 20, 'weight' => 52, 'gym' => 2],
        ['name' => 'Reza Firmansyah', 'email' => 'reza@apex.test', 'gender' => 'male', 'age' => 27, 'weight' => 79, 'gym' => 2],
        ['name' => 'Sinta Dewanti', 'email' => 'sinta@apex.test', 'gender' => 'female', 'age' => 24, 'weight' => 56, 'gym' => 3],
        ['name' => 'Tono Hartono', 'email' => 'tono@apex.test', 'gender' => 'male', 'age' => 45, 'weight' => 93, 'gym' => 3],
        ['name' => 'Umar Bakri', 'email' => 'umar@apex.test', 'gender' => 'male', 'age' => 32, 'weight' => 84, 'gym' => 4],
        ['name' => 'Vina Oktaviani', 'email' => 'vina@apex.test', 'gender' => 'female', 'age' => 23, 'weight' => 54, 'gym' => 4],
        ['name' => 'Wawan Kurniawan', 'email' => 'wawan@apex.test', 'gender' => 'male', 'age' => 36, 'weight' => 87, 'gym' => 5],
    ];

    public function run(): void
    {
        $gyms = Gym::orderBy('id')->get()->values();
        if ($gyms->isEmpty()) {
            $this->command->warn('No gyms found — run GymSeeder first.');

            return;
        }
        $busyGym = $gyms->first();

        // Resolve the popular machines that actually exist.
        $machines = Machine::whereIn('name', array_keys($this->baseWeights))
            ->get()->keyBy('name');

        // --- Users (stable logins, each with their own gym check-ins) --------
        $everyone = [];
        foreach ($this->roster as $entry) {
            $gym = $gyms[$entry['gym'] % $gyms->count()];
            $everyone[$entry['email']] = $this->makeUser(
                $entry['name'], $entry['email'], $entry['gender'],
                $entry['age'], $entry['weight'], $gym,
            );
        }

        $demo = $everyone['demo@apex.test'];
        $rina = $everyone['rival@apex.test'];
        $bram = $everyone['coach@apex.test'];

        // --- Workout sets ----------------------------------------------------
        $strength = [];
        foreach ($everyone as $u) {
            $strength[$u->id] = $u->gender === 'female'
                ? fake()->randomFloat(2, 0.55, 0.95)
                : fake()->randomFloat(2, 0.80, 1.40);
            $this->seedSets($u, $machines, $strength[$u->id]);
        }
        // Make the demo account strong & well-rounded.
        $strength[$demo->id] = 1.25;
        $this->seedSets($demo, $machines, 1.25, dense: true);

        // Feature a few machines on every profile.
        foreach ($everyone as $u) {
            $ids = WorkoutSet::where('user_id', $u->id)
                ->distinct()->pluck('machine_id')->shuffle()->take(3)->values()->all();
            $u->update(['featured_machine_ids' => $ids]);
        }

        // Judges per gym, so votes always come from lifters who train there.
        $judgesByGym = collect($everyone)
            ->groupBy(fn (User $u) => $u->homeGym()?->id)
            ->map(fn ($group) => $group->values());
        $busyJudges = $judgesByGym[$busyGym->id] ?? collect();

        $bench = $machines['Bench Press (Barbell)'] ?? $machines->first();
        $squat = $machines['Squat (Barbell)'] ?? $machines->first();
        $dead = $machines['Deadlift (Barbell)'] ?? $machines->first();
        $press = $machines['Overhead Press (Barbell)'] ?? $machines->first();

        $others = collect($everyone)
            ->reject(fn (User $u) => in_array($u->id, [$demo->id, $rina->id, $bram->id]))
            ->values();

        // --- Scripted challenges around the demo account ----------------------
        // 1) Completed — demo WON (medal + notification).
        $c1 = $this->makeChallenge($demo, $bram, $bench, $busyGym, 'completed',
            weight: 120, reps: 3, sets: 3, winner: $demo);
        $this->addVotes($c1, $busyJudges, ['challenger' => 4, 'opponent' => 1]);
        $this->notifyResult($c1);

        // 2) Completed — demo LOST (Rina has a medal).
        $c2 = $this->makeChallenge($rina, $demo, $squat, $busyGym, 'completed',
            weight: 140, reps: 2, sets: 3, winner: $rina);
        $this->addVotes($c2, $busyJudges, ['challenger' => 5, 'opponent' => 2]);
        $this->notifyResult($c2);

        // 3) Active in the arena between two others — demo CAN judge it.
        $c3 = $this->makeChallenge($rina, $bram, $dead, $busyGym, 'active',
            weight: 150, reps: 3, sets: 2);
        $this->addVotes($c3, $busyJudges->reject(fn ($u) => $u->id === $demo->id),
            ['challenger' => 2, 'opponent' => 2, 'invalid' => 1]);

        // 4) Active — demo is a participant (shows in "Mine", awaits result).
        $this->makeChallenge($demo, $others[0], $press, $busyGym, 'active',
            weight: 60, reps: 5, sets: 3);

        // 5) Pending — someone challenged demo (demo can accept/decline).
        $c5 = $this->makeChallenge($others[1], $demo, $bench, $busyGym, 'pending',
            weight: 100, reps: 5, sets: 3);
        $this->notify($demo, $c5, 'challenge_received', 'You\'ve been challenged! 💪',
            $others[1]->name.' challenged you on Bench Press (Barbell). Record your proof to accept!');

        // 6) Pending — demo challenged someone (awaiting their proof).
        $this->makeChallenge($demo, $others[2], $squat, $busyGym, 'pending',
            weight: 110, reps: 4, sets: 3, challengerSubmitted: true);

        // --- Challenge history for EVERY account -------------------------------
        // Two rounds of completed head-to-heads so each lifter has their own
        // wins/losses, medals, and notifications.
        $pool = collect($everyone)->values();
        for ($round = 0; $round < 2; $round++) {
            $shuffled = $pool->shuffle()->values();
            for ($i = 0; $i + 1 < $shuffled->count(); $i += 2) {
                $a = $shuffled[$i];
                $b = $shuffled[$i + 1];
                $machineName = fake()->randomElement(array_keys($this->baseWeights));
                $machine = $machines[$machineName] ?? $machines->first();
                $gym = $a->homeGym() ?? $busyGym;

                $target = $this->round25(max(10,
                    ($this->baseWeights[$machineName] ?? 40) * ($strength[$a->id] ?? 1.0)));
                $winner = fake()->boolean() ? $a : $b;

                $ch = $this->makeChallenge($a, $b, $machine, $gym, 'completed',
                    weight: (int) $target,
                    reps: fake()->numberBetween(2, 8),
                    sets: fake()->numberBetween(2, 4),
                    winner: $winner,
                    daysAgo: fake()->numberBetween(2, 40));
                $this->addVotes($ch, $judgesByGym[$gym->id] ?? $busyJudges, [
                    'challenger' => $winner->id === $a->id ? fake()->numberBetween(3, 5) : fake()->numberBetween(0, 2),
                    'opponent' => $winner->id === $b->id ? fake()->numberBetween(3, 5) : fake()->numberBetween(0, 2),
                ]);
                $this->notifyResult($ch);
            }
        }

        // A few extra live arena challenges among others for variety.
        for ($k = 3; $k <= 7; $k++) {
            $ch = $this->makeChallenge($others[$k], $others[$k + 3],
                $machines->random(), $busyGym, 'active',
                weight: fake()->numberBetween(40, 130), reps: fake()->numberBetween(2, 8), sets: 3);
            $this->addVotes($ch, $busyJudges->reject(fn ($u) => $u->id === $demo->id),
                ['challenger' => fake()->numberBetween(1, 3), 'opponent' => fake()->numberBetween(0, 2)]);
        }

        $this->command->info('Seeded '.count($everyone).' lifters, '
            .WorkoutSet::count().' sets, '.Checkin::count().' check-ins, '
            .Challenge::count().' challenges.');
        $this->command->info('All accounts use the password: password');
    }

    private function makeUser(string $name, string $email, string $gender, int $age, int $weightKg, Gym $gym): User
    {
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'gender' => $gender,
                'birth_date' => now()->subYears($age)->subDays(fake()->numberBetween(0, 300)),
                'body_weight_kg' => $weightKg,
                'body_weight_updated_at' => now()->subDays(fake()->numberBetween(0, 45)),
            ],
        );

        // A month of gym sessions per user, the latest one recent so the
        // "who's here" screen is populated.
        Checkin::where('user_id', $user->id)->delete();
        for ($i = 1; $i <= fake()->numberBetween(8, 16); $i++) {
            Checkin::create([
                'user_id' => $user->id,
                'gym_id' => $gym->id,
                'checked_in_at' => now()->subDays($i * 2)->subHours(fake()->numberBetween(0, 6)),
            ]);
        }
        // Recent check-in (within ~2h) so the gym-presence screen is populated.
        Checkin::create([
            'user_id' => $user->id,
            'gym_id' => $gym->id,
            'checked_in_at' => now()->subMinutes(fake()->numberBetween(5, 130)),
        ]);

        return $user;
    }

    /**
     * @param  Collection<string, Machine>  $machines
     */
    private function seedSets(User $user, $machines, float $strength, bool $dense = false): void
    {
        $rows = [];
        $picked = $machines->shuffle()->take($dense ? $machines->count() : fake()->numberBetween(9, 16));
        $gymId = $user->homeGym()?->id;

        foreach ($picked as $name => $machine) {
            $base = $this->baseWeights[$name] ?? 40;
            $days = fake()->numberBetween(3, 8);
            for ($d = 0; $d < $days; $d++) {
                // First session recent (populates weekly board), rest older.
                $offset = $d === 0
                    ? fake()->numberBetween(0, 6)
                    : fake()->numberBetween(7, 60);
                $when = now()->subDays($offset)
                    ->setTime(fake()->numberBetween(6, 21), fake()->numberBetween(0, 59));

                $sets = fake()->numberBetween(2, 4);
                for ($s = 0; $s < $sets; $s++) {
                    // Occasional heavy single for the pure-1RM board.
                    $single = fake()->boolean(12);
                    $reps = $single ? 1 : fake()->numberBetween(3, 10);
                    $factor = $single ? 1.05 : fake()->randomFloat(2, 0.82, 1.0);
                    $weight = $this->round25(max(5, $base * $strength * $factor));
                    $rows[] = [
                        'user_id' => $user->id,
                        'machine_id' => $machine->id,
                        'gym_id' => $gymId,
                        'weight_kg' => $weight,
                        'reps' => $reps,
                        'estimated_1rm' => WorkoutSet::epley($weight, $reps),
                        'performed_at' => $when->copy()->addMinutes($s * 3)->format('Y-m-d H:i:s'),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
        }

        foreach (array_chunk($rows, 400) as $chunk) {
            WorkoutSet::insert($chunk);
        }
    }

    private function makeChallenge(
        User $challenger,
        User $opponent,
        Machine $machine,
        Gym $gym,
        string $status,
        int $weight,
        int $reps,
        int $sets,
        ?User $winner = null,
        bool $challengerSubmitted = false,
        int $daysAgo = 3,
    ): Challenge {
        $attrs = [
            'challenger_id' => $challenger->id,
            'opponent_id' => $opponent->id,
            'machine_id' => $machine->id,
            'gym_id' => $gym->id,
            'target_weight_kg' => $weight,
            'target_reps' => $reps,
            'target_sets' => $sets,
            'status' => $status,
        ];

        if ($status === 'pending') {
            if ($challengerSubmitted) {
                $attrs['challenger_video_path'] = self::SAMPLE_VIDEO;
                $attrs['challenger_submitted_at'] = now()->subHours(2);
            }
        } elseif ($status === 'active') {
            $attrs += [
                'challenger_video_path' => self::SAMPLE_VIDEO,
                'opponent_video_path' => self::SAMPLE_VIDEO,
                'challenger_submitted_at' => now()->subHours(6),
                'opponent_submitted_at' => now()->subHours(5),
                'voting_ends_at' => now()->addHours(fake()->numberBetween(4, 40)),
            ];
        } elseif ($status === 'completed') {
            $attrs += [
                'challenger_video_path' => self::SAMPLE_VIDEO,
                'opponent_video_path' => self::SAMPLE_VIDEO,
                'challenger_submitted_at' => now()->subDays($daysAgo),
                'opponent_submitted_at' => now()->subDays($daysAgo),
                'voting_ends_at' => now()->subDays(max(0, $daysAgo - 2))->subHours(4),
                'winner_id' => $winner?->id,
                'resolved_at' => now()->subDays(max(0, $daysAgo - 2)),
            ];
        }

        return Challenge::create($attrs);
    }

    /** Won/lost notifications for both participants of a completed challenge. */
    private function notifyResult(Challenge $challenge): void
    {
        if ($challenge->status !== 'completed' || ! $challenge->winner_id) {
            return;
        }
        $machineName = Machine::find($challenge->machine_id)?->name ?? 'a machine';
        [$winnerId, $loserId] = $challenge->winner_id === $challenge->challenger_id
            ? [$challenge->challenger_id, $challenge->opponent_id]
            : [$challenge->opponent_id, $challenge->challenger_id];

        $this->notify(User::find($winnerId), $challenge, 'challenge_won',
            'You won a challenge! 🏅',
            'The arena crowned you winner on '.$machineName.'.');
        $this->notify(User::find($loserId), $challenge, 'challenge_lost',
            'Challenge decided',
            'The arena decided your '.Str::before($machineName, ' (').' challenge. Call for a rematch!');
    }

    /**
     * @param  Collection<int, User>  $judges
     * @param  array<string, int>  $plan  choice => count
     */
    private function addVotes(Challenge $challenge, $judges, array $plan): void
    {
        $pool = $judges
            ->reject(fn (User $u) => $challenge->isParticipant($u->id))
            ->shuffle()
            ->values();

        $idx = 0;
        foreach ($plan as $choice => $count) {
            for ($i = 0; $i < $count && $idx < $pool->count(); $i++, $idx++) {
                $voter = $pool[$idx];
                $rejected = $choice === 'invalid';
                ChallengeVote::updateOrCreate(
                    ['challenge_id' => $challenge->id, 'voter_id' => $voter->id],
                    [
                        'choice' => $choice,
                        'criteria' => [
                            'load' => ! $rejected,
                            'form' => ! $rejected,
                            'machine' => true,
                            'reps_sets' => ! $rejected,
                        ],
                        'reason_code' => $rejected ? 'load_too_light' : null,
                        'reason_text' => $rejected ? 'Weight looked lighter than claimed.' : null,
                    ],
                );
            }
        }
    }

    private function notify(?User $user, Challenge $challenge, string $type, string $title, string $body): void
    {
        if (! $user) {
            return;
        }
        RankNotification::create([
            'user_id' => $user->id,
            'type' => $type,
            'machine_id' => $challenge->machine_id,
            'challenge_id' => $challenge->id,
            'title' => $title,
            'body' => $body,
            'created_at' => now()->subHours(fake()->numberBetween(1, 20)),
        ]);
    }

    private function round25(float $w): float
    {
        return round($w / 2.5) * 2.5;
    }
}
