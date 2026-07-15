<?php

namespace Tests\Feature;

use App\Models\Challenge;
use App\Models\Checkin;
use App\Models\Gym;
use App\Models\Machine;
use App\Models\User;
use App\Models\WorkoutSet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_delete_account_removes_the_user_and_their_data(): void
    {
        $user = User::factory()->create();
        $gym = Gym::create(['name' => 'Iron House', 'address' => '1 Main St', 'latitude' => 0, 'longitude' => 0]);
        $machine = Machine::create(['name' => 'Leg Press', 'brand' => 'Acme', 'category' => 'legs']);

        WorkoutSet::create([
            'user_id' => $user->id,
            'machine_id' => $machine->id,
            'gym_id' => $gym->id,
            'weight_kg' => 100,
            'reps' => 5,
            'estimated_1rm' => 115,
            'performed_at' => now(),
        ]);
        Checkin::create([
            'user_id' => $user->id,
            'gym_id' => $gym->id,
            'checked_in_at' => now(),
        ]);

        $this->actingAs($user)
            ->deleteJson('/api/auth/account', ['password' => 'password'])
            ->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('workout_sets', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('checkins', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => User::class,
        ]);
    }

    public function test_delete_account_requires_correct_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->deleteJson('/api/auth/account', ['password' => 'wrong-password'])
            ->assertStatus(422);

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_delete_account_cascades_to_challenges(): void
    {
        $user = User::factory()->create();
        $opponent = User::factory()->create();
        $machine = Machine::create(['name' => 'Bench', 'brand' => 'Acme', 'category' => 'chest']);

        $challenge = Challenge::create([
            'challenger_id' => $user->id,
            'opponent_id' => $opponent->id,
            'machine_id' => $machine->id,
            'target_weight_kg' => 80,
            'target_reps' => 8,
            'target_sets' => 3,
            'status' => Challenge::STATUS_PENDING,
        ]);

        $this->actingAs($user)
            ->deleteJson('/api/auth/account', ['password' => 'password'])
            ->assertOk();

        $this->assertDatabaseMissing('challenges', ['id' => $challenge->id]);
    }

    public function test_delete_account_requires_authentication(): void
    {
        $this->deleteJson('/api/auth/account', ['password' => 'password'])
            ->assertUnauthorized();
    }
}
