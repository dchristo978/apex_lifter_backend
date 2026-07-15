<?php

namespace Tests\Feature;

use App\Models\Machine;
use App\Models\User;
use App\Models\WorkoutSet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkoutSetDeletionTest extends TestCase
{
    use RefreshDatabase;

    private function makeSet(User $user, array $overrides = []): WorkoutSet
    {
        $machine = Machine::create(['name' => 'Bench', 'brand' => 'Acme', 'category' => 'chest']);

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

    public function test_owner_can_delete_a_recent_set(): void
    {
        $user = User::factory()->create();
        $set = $this->makeSet($user);

        $this->actingAs($user)
            ->deleteJson("/api/workout-sets/{$set->id}")
            ->assertOk()
            ->assertJson(['deleted' => true]);

        $this->assertDatabaseMissing('workout_sets', ['id' => $set->id]);
    }

    public function test_cannot_delete_another_users_set(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $set = $this->makeSet($owner);

        $this->actingAs($intruder)
            ->deleteJson("/api/workout-sets/{$set->id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('workout_sets', ['id' => $set->id]);
    }

    public function test_cannot_delete_a_set_past_the_edit_window(): void
    {
        $user = User::factory()->create();
        $set = $this->makeSet($user, [
            'performed_at' => now()->subMinutes(WorkoutSet::EDIT_WINDOW_MINUTES + 1),
        ]);

        $this->actingAs($user)
            ->deleteJson("/api/workout-sets/{$set->id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('workout_sets', ['id' => $set->id]);
    }

    public function test_deletion_requires_authentication(): void
    {
        $user = User::factory()->create();
        $set = $this->makeSet($user);

        $this->deleteJson("/api/workout-sets/{$set->id}")
            ->assertUnauthorized();
    }
}
