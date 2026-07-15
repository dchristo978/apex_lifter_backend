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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * Rich demo data for manual testing: many lifters, realistic sets across the
 * popular machines, gym check-ins, and challenges in every state (pending,
 * active-in-arena, completed with medals) plus matching notifications.
 *
 * Run with: php artisan db:seed --class=TestDataSeeder
 * Log in as: demo@apex.test / password
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

    public function run(): void
    {
        $gyms = Gym::all();
        if ($gyms->isEmpty()) {
            $this->command->warn('No gyms found — run GymSeeder first.');

            return;
        }
        $busyGym = $gyms->first();

        // Resolve the popular machines that actually exist.
        $machines = Machine::whereIn('name', array_keys($this->baseWeights))
            ->get()->keyBy('name');

        // --- Known accounts (stable logins) ---------------------------------
        $demo = $this->makeUser('Demo Lifter', 'demo@apex.test', 'male', 28, 80, $busyGym);
        $rina = $this->makeUser('Rina Kuat', 'rival@apex.test', 'female', 26, 63, $busyGym);
        $bram = $this->makeUser('Coach Bram', 'coach@apex.test', 'male', 34, 92, $busyGym);

        // --- Random lifters --------------------------------------------------
        $others = [];
        for ($i = 0; $i < 18; $i++) {
            $gender = fake()->boolean(60) ? 'male' : 'female';
            $age = fake()->numberBetween(17, 47);
            $weight = $gender === 'female'
                ? fake()->numberBetween(48, 86)
                : fake()->numberBetween(60, 106);
            // Cluster ~60% of lifters at the busy gym so the arena has judges.
            $gym = fake()->boolean(60) ? $busyGym : $gyms->random();
            $others[] = $this->makeUser(
                fake()->name($gender === 'male' ? 'male' : 'female'),
                'lifter'.$i.'_'.uniqid().'@apex.test',
                $gender,
                $age,
                $weight,
                $gym,
            );
        }

        $everyone = array_merge([$demo, $rina, $bram], $others);

        // --- Workout sets ----------------------------------------------------
        $strength = [];
        foreach ($everyone as $u) {
            $strength[$u->id] = $u->gender === 'female'
                ? fake()->randomFloat(2, 0.55, 0.95)
                : fake()->randomFloat(2, 0.80, 1.40);
            $this->seedSets($u, $machines, $strength[$u->id]);
        }
        // Make the demo account strong & well-rounded.
        $this->seedSets($demo, $machines, 1.25, dense: true);

        // Feature a few machines on some profiles.
        foreach (array_merge([$demo, $rina, $bram], array_slice($others, 0, 6)) as $u) {
            $ids = WorkoutSet::where('user_id', $u->id)
                ->distinct()->pluck('machine_id')->shuffle()->take(3)->values()->all();
            $u->update(['featured_machine_ids' => $ids]);
        }

        // --- Challenges ------------------------------------------------------
        $busyJudges = collect($everyone)
            ->filter(fn ($u) => $u->homeGym()?->id === $busyGym->id)
            ->values();

        $bench = $machines['Bench Press (Barbell)'] ?? $machines->first();
        $squat = $machines['Squat (Barbell)'] ?? $machines->first();
        $dead = $machines['Deadlift (Barbell)'] ?? $machines->first();
        $press = $machines['Overhead Press (Barbell)'] ?? $machines->first();

        // 1) Completed — demo WON (medal + notification).
        $c1 = $this->makeChallenge($demo, $bram, $bench, $busyGym, 'completed',
            weight: 120, reps: 3, sets: 3, winner: $demo);
        $this->addVotes($c1, $busyJudges, ['challenger' => 4, 'opponent' => 1]);
        $this->notify($demo, $c1, 'challenge_won', 'You won a challenge! 🏅',
            'The arena crowned you winner on Bench Press (Barbell).');
        $this->notify($bram, $c1, 'challenge_lost', 'Challenge decided',
            'The arena decided your Bench Press challenge.');

        // 2) Completed — demo LOST (Rina has a medal).
        $c2 = $this->makeChallenge($rina, $demo, $squat, $busyGym, 'completed',
            weight: 140, reps: 2, sets: 3, winner: $rina);
        $this->addVotes($c2, $busyJudges, ['challenger' => 5, 'opponent' => 2]);
        $this->notify($demo, $c2, 'challenge_lost', 'Challenge decided',
            'The arena decided your Squat challenge. Call for a rematch!');

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

        // A couple of extra arena challenges among others for variety.
        for ($k = 3; $k <= 5; $k++) {
            $ch = $this->makeChallenge($others[$k], $others[$k + 3],
                $machines->random(), $busyGym, 'active',
                weight: fake()->numberBetween(40, 130), reps: fake()->numberBetween(2, 8), sets: 3);
            $this->addVotes($ch, $busyJudges->reject(fn ($u) => $u->id === $demo->id),
                ['challenger' => fake()->numberBetween(1, 3), 'opponent' => fake()->numberBetween(0, 2)]);
        }

        $this->command->info('Seeded '.count($everyone).' lifters, '
            .WorkoutSet::count().' sets, '.Challenge::count().' challenges.');
        $this->command->info('Log in as: demo@apex.test / password');
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

        // A handful of check-ins, the latest one recent so "who's here" shows.
        Checkin::where('user_id', $user->id)->delete();
        for ($i = 0; $i < fake()->numberBetween(3, 8); $i++) {
            Checkin::create([
                'user_id' => $user->id,
                'gym_id' => $gym->id,
                'checked_in_at' => now()->subDays($i)->subHours(fake()->numberBetween(0, 6)),
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
     * @param  \Illuminate\Support\Collection<string, Machine>  $machines
     */
    private function seedSets(User $user, $machines, float $strength, bool $dense = false): void
    {
        $rows = [];
        $picked = $machines->shuffle()->take($dense ? $machines->count() : fake()->numberBetween(7, 14));
        $gymId = $user->homeGym()?->id;

        foreach ($picked as $name => $machine) {
            $base = $this->baseWeights[$name] ?? 40;
            $days = fake()->numberBetween(2, 6);
            for ($d = 0; $d < $days; $d++) {
                // First session recent (populates weekly board), rest older.
                $offset = $d === 0
                    ? fake()->numberBetween(0, 6)
                    : fake()->numberBetween(7, 44);
                $when = now()->subDays($offset)
                    ->setTime(fake()->numberBetween(6, 21), fake()->numberBetween(0, 59));

                $sets = fake()->numberBetween(1, 3);
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
                'challenger_submitted_at' => now()->subDays(3),
                'opponent_submitted_at' => now()->subDays(3),
                'voting_ends_at' => now()->subDay(),
                'winner_id' => $winner?->id,
                'resolved_at' => now()->subHours(6),
            ];
        }

        return Challenge::create($attrs);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, User>  $judges
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

    private function notify(User $user, Challenge $challenge, string $type, string $title, string $body): void
    {
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
