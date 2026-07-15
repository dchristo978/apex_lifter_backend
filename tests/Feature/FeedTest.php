<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Machine;
use App\Models\User;
use App\Models\WorkoutSet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_feed_shows_followed_lifters_and_self_but_not_strangers(): void
    {
        $me = User::factory()->create();
        $friend = User::factory()->create();
        $stranger = User::factory()->create();

        $me->following()->attach($friend->id);

        Activity::record($me->id, Activity::TYPE_CHECKIN, null, ['gym_name' => 'Mine']);
        Activity::record($friend->id, Activity::TYPE_MEDAL, null, ['machine_name' => 'Bench']);
        Activity::record($stranger->id, Activity::TYPE_PR, null, ['machine_name' => 'Squat']);

        $response = $this->actingAs($me)->getJson('/api/feed')->assertOk();

        $response->assertJsonCount(2, 'data');
        $ids = collect($response->json('data'))->pluck('actor.id')->all();
        $this->assertContains($me->id, $ids);
        $this->assertContains($friend->id, $ids);
        $this->assertNotContains($stranger->id, $ids);
    }

    public function test_logging_a_new_best_records_a_pr_activity(): void
    {
        $user = User::factory()->create();
        $machine = Machine::create(['name' => 'Bench', 'brand' => 'Acme', 'category' => 'chest']);

        $this->actingAs($user)
            ->postJson('/api/workout-sets', [
                'machine_id' => $machine->id,
                'weight_kg' => 100,
                'reps' => 5,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('activities', [
            'user_id' => $user->id,
            'type' => Activity::TYPE_PR,
        ]);
    }

    public function test_a_lighter_set_does_not_record_a_pr(): void
    {
        $user = User::factory()->create();
        $machine = Machine::create(['name' => 'Bench', 'brand' => 'Acme', 'category' => 'chest']);

        WorkoutSet::create([
            'user_id' => $user->id,
            'machine_id' => $machine->id,
            'weight_kg' => 120,
            'reps' => 3,
            'estimated_1rm' => WorkoutSet::epley(120, 3),
            'performed_at' => now()->subDay(),
        ]);

        $this->actingAs($user)
            ->postJson('/api/workout-sets', [
                'machine_id' => $machine->id,
                'weight_kg' => 100,
                'reps' => 5,
            ])
            ->assertCreated();

        $this->assertSame(0, Activity::where('type', Activity::TYPE_PR)->count());
    }

    public function test_feed_requires_authentication(): void
    {
        $this->getJson('/api/feed')->assertUnauthorized();
    }
}
