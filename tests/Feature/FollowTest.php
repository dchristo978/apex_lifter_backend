<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FollowTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_lifter_can_follow_another(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($me)
            ->postJson("/api/users/{$other->id}/follow")
            ->assertOk()
            ->assertJson([
                'is_following' => true,
                'followers_count' => 1,
            ]);

        $this->assertDatabaseHas('follows', [
            'follower_id' => $me->id,
            'followee_id' => $other->id,
        ]);
    }

    public function test_following_is_idempotent(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($me)->postJson("/api/users/{$other->id}/follow")->assertOk();
        $this->actingAs($me)->postJson("/api/users/{$other->id}/follow")
            ->assertOk()
            ->assertJson(['followers_count' => 1]);

        $this->assertSame(1, $other->followers()->count());
    }

    public function test_a_lifter_cannot_follow_themselves(): void
    {
        $me = User::factory()->create();

        $this->actingAs($me)
            ->postJson("/api/users/{$me->id}/follow")
            ->assertStatus(422);

        $this->assertSame(0, $me->followers()->count());
    }

    public function test_a_lifter_can_unfollow(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        $me->following()->attach($other->id);

        $this->actingAs($me)
            ->deleteJson("/api/users/{$other->id}/follow")
            ->assertOk()
            ->assertJson([
                'is_following' => false,
                'followers_count' => 0,
            ]);

        $this->assertDatabaseMissing('follows', [
            'follower_id' => $me->id,
            'followee_id' => $other->id,
        ]);
    }

    public function test_followers_and_following_lists(): void
    {
        $me = User::factory()->create();
        $a = User::factory()->create();
        $b = User::factory()->create();

        $me->following()->attach([$a->id, $b->id]);
        $a->following()->attach($me->id);

        $this->actingAs($me)
            ->getJson("/api/users/{$me->id}/following")
            ->assertOk()
            ->assertJsonCount(2, 'users');

        $this->actingAs($me)
            ->getJson("/api/users/{$me->id}/followers")
            ->assertOk()
            ->assertJsonCount(1, 'users')
            ->assertJsonPath('users.0.id', $a->id);
    }

    public function test_profile_exposes_follow_state(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        $me->following()->attach($other->id);

        $this->actingAs($me)
            ->getJson("/api/users/{$other->id}")
            ->assertOk()
            ->assertJsonPath('user.is_following', true)
            ->assertJsonPath('user.is_self', false)
            ->assertJsonPath('user.followers_count', 1);
    }

    public function test_follow_requires_authentication(): void
    {
        $other = User::factory()->create();

        $this->postJson("/api/users/{$other->id}/follow")->assertUnauthorized();
    }

    public function test_a_new_follow_notifies_the_followee_once(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($me)->postJson("/api/users/{$other->id}/follow")->assertOk();

        $this->assertDatabaseHas('rank_notifications', [
            'user_id' => $other->id,
            'type' => 'new_follower',
            'overtaken_by_user_id' => $me->id,
            'machine_id' => null,
        ]);

        // A repeat follow (already following) must not create a second one.
        $this->actingAs($me)->postJson("/api/users/{$other->id}/follow")->assertOk();

        $this->assertSame(
            1,
            $other->rankNotifications()->where('type', 'new_follower')->count(),
        );
    }

    public function test_suggestions_exclude_self_and_already_followed(): void
    {
        $me = User::factory()->create();
        $followed = User::factory()->create();
        $candidate = User::factory()->create();
        $me->following()->attach($followed->id);

        $response = $this->actingAs($me)->getJson('/api/follow-suggestions')->assertOk();

        $ids = collect($response->json('users'))->pluck('id')->all();
        $this->assertContains($candidate->id, $ids);
        $this->assertNotContains($me->id, $ids);
        $this->assertNotContains($followed->id, $ids);
    }
}
