<?php

namespace Tests\Feature;

use App\Models\Machine;
use App\Models\User;
use App\Models\WorkoutSet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class WeekStreakTest extends TestCase
{
    use RefreshDatabase;

    private function machine(): Machine
    {
        return Machine::create(['name' => 'Test', 'brand' => 'Acme', 'category' => 'chest']);
    }

    private function logSet(User $user, Machine $machine, int $daysAgo): void
    {
        WorkoutSet::create([
            'user_id' => $user->id,
            'machine_id' => $machine->id,
            'weight_kg' => 50,
            'reps' => 5,
            'estimated_1rm' => 58,
            'performed_at' => Carbon::now()->subDays($daysAgo),
        ]);
    }

    public function test_no_sessions_means_no_streak(): void
    {
        $this->assertSame(0, User::factory()->create()->weekStreak());
    }

    public function test_counts_consecutive_weeks_up_to_a_gap(): void
    {
        // Freeze near mid-week so subDays stays within the same calendar weeks.
        Carbon::setTestNow(Carbon::parse('2026-07-15 12:00:00')); // Wednesday
        $user = User::factory()->create();
        $machine = $this->machine();

        // This week, last week, two weeks ago — then a gap, then four weeks ago.
        foreach ([0, 7, 14, 28] as $daysAgo) {
            $this->logSet($user, $machine, $daysAgo);
        }

        $this->assertSame(3, $user->weekStreak());
        Carbon::setTestNow();
    }

    public function test_current_week_empty_keeps_streak_alive_from_last_week(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-15 12:00:00'));
        $user = User::factory()->create();
        $machine = $this->machine();

        // Nothing this week, but last week and the week before have a session.
        $this->logSet($user, $machine, 8);
        $this->logSet($user, $machine, 15);

        $this->assertSame(2, $user->weekStreak());
        Carbon::setTestNow();
    }

    public function test_a_fully_missed_week_breaks_the_streak(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-15 12:00:00'));
        $user = User::factory()->create();
        $machine = $this->machine();

        // Only a session ~three weeks ago: neither this week nor last week.
        $this->logSet($user, $machine, 20);

        $this->assertSame(0, $user->weekStreak());
        Carbon::setTestNow();
    }
}
