<?php

namespace Tests\Feature;

use App\Models\Machine;
use App\Models\User;
use App\Models\WorkoutSet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InsightsTest extends TestCase
{
    use RefreshDatabase;

    private function machine(string $name, string $muscleGroup): Machine
    {
        return Machine::create([
            'name' => $name,
            'brand' => 'Acme',
            'category' => 'legs',
            'muscle_group' => $muscleGroup,
        ]);
    }

    private function logSet(User $user, Machine $machine, array $overrides = []): WorkoutSet
    {
        return WorkoutSet::create([
            'user_id' => $user->id,
            'machine_id' => $machine->id,
            'weight_kg' => 100,
            'reps' => 5,
            'estimated_1rm' => 115,
            'performed_at' => now(),
            ...$overrides,
        ]);
    }

    public function test_heatmap_returns_one_count_per_trained_day(): void
    {
        $user = User::factory()->create();
        $machine = $this->machine('Leg Press (Machine)', 'Quadriceps');

        // Two sets today, one yesterday, one long ago outside the window.
        $this->logSet($user, $machine, ['performed_at' => now()]);
        $this->logSet($user, $machine, ['performed_at' => now()]);
        $this->logSet($user, $machine, ['performed_at' => now()->subDay()]);
        $this->logSet($user, $machine, ['performed_at' => now()->subDays(400)]);

        $res = $this->actingAs($user)->getJson('/api/insights/heatmap')->assertOk();

        $days = collect($res->json('days'));
        $this->assertCount(2, $days); // the 400-day-old set is out of range
        $this->assertSame(2, $days->firstWhere('date', now()->toDateString())['count']);
    }

    public function test_muscle_activation_reports_trained_and_neglected_groups(): void
    {
        $user = User::factory()->create();
        $quads = $this->machine('Leg Press (Machine)', 'Quadriceps');
        $chest = $this->machine('Bench (Machine)', 'Chest');

        $this->logSet($user, $quads, ['performed_at' => now()->subDays(2)]);
        // Chest trained outside the 7-day window — should read as neglected.
        $this->logSet($user, $chest, ['performed_at' => now()->subDays(20)]);

        $res = $this->actingAs($user)
            ->getJson('/api/insights/muscle-activation?days=7')
            ->assertOk();

        $this->assertContains('Quadriceps', $res->json('trained'));
        $this->assertContains('Chest', $res->json('neglected'));
        $this->assertNotContains('Quadriceps', $res->json('neglected'));

        $quadStat = collect($res->json('groups'))->firstWhere('group', 'Quadriceps');
        $this->assertSame(1, $quadStat['sets']);
        $this->assertSame(2, $quadStat['days_since']);
    }

    public function test_strength_standards_need_body_weight_and_gender(): void
    {
        $user = User::factory()->create(['body_weight_kg' => null, 'gender' => null]);

        $this->actingAs($user)
            ->getJson('/api/insights/strength-standards')
            ->assertOk()
            ->assertJson(['needs_profile' => true]);
    }

    public function test_strength_standards_rank_a_lift_against_body_weight(): void
    {
        $user = User::factory()->create(['body_weight_kg' => 100, 'gender' => 'male']);
        $bench = $this->machine('Bench Press (Barbell)', 'Chest');

        // 100kg 1RM at 100kg body weight = 1.0x → Intermediate (3rd threshold).
        $this->logSet($user, $bench, ['estimated_1rm' => 100]);

        $res = $this->actingAs($user)
            ->getJson('/api/insights/strength-standards')
            ->assertOk()
            ->assertJson(['needs_profile' => false]);

        $lift = collect($res->json('lifts'))->firstWhere('name', 'Bench Press (Barbell)');
        $this->assertSame('Intermediate', $lift['level']);
        $this->assertSame('Advanced', $lift['next_level']);
        $this->assertEqualsWithDelta(150.0, $lift['next_target_kg'], 0.01);
    }
}
