<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivitySocialTest extends TestCase
{
    use RefreshDatabase;

    private function activityBy(User $user): Activity
    {
        return Activity::record($user->id, Activity::TYPE_CHECKIN, null, ['gym_name' => 'Iron']);
    }

    public function test_kudos_is_idempotent_and_counted(): void
    {
        $author = User::factory()->create();
        $fan = User::factory()->create();
        $activity = $this->activityBy($author);

        $this->actingAs($fan)->postJson("/api/activities/{$activity->id}/kudos")
            ->assertOk()
            ->assertJson(['kudos_count' => 1, 'viewer_kudoed' => true]);

        // A repeat tap must not double-count.
        $this->actingAs($fan)->postJson("/api/activities/{$activity->id}/kudos")
            ->assertOk()
            ->assertJson(['kudos_count' => 1]);

        $this->assertSame(1, $activity->kudos()->count());
    }

    public function test_kudos_can_be_removed(): void
    {
        $author = User::factory()->create();
        $fan = User::factory()->create();
        $activity = $this->activityBy($author);
        $activity->kudos()->create(['user_id' => $fan->id]);

        $this->actingAs($fan)->deleteJson("/api/activities/{$activity->id}/kudos")
            ->assertOk()
            ->assertJson(['kudos_count' => 0, 'viewer_kudoed' => false]);
    }

    public function test_feed_reports_kudos_and_comment_counts_and_viewer_state(): void
    {
        $me = User::factory()->create();
        $friend = User::factory()->create();
        $me->following()->attach($friend->id);

        $activity = $this->activityBy($friend);
        $activity->kudos()->create(['user_id' => $me->id]);
        $activity->comments()->create(['user_id' => $me->id, 'body' => 'Beast!']);

        $this->actingAs($me)->getJson('/api/feed')
            ->assertOk()
            ->assertJsonPath('data.0.kudos_count', 1)
            ->assertJsonPath('data.0.comment_count', 1)
            ->assertJsonPath('data.0.viewer_kudoed', true);
    }

    public function test_a_lifter_can_comment_and_the_author_can_moderate(): void
    {
        $author = User::factory()->create();
        $commenter = User::factory()->create();
        $activity = $this->activityBy($author);

        $post = $this->actingAs($commenter)
            ->postJson("/api/activities/{$activity->id}/comments", ['body' => 'Nice lift'])
            ->assertCreated()
            ->assertJsonPath('comment.body', 'Nice lift')
            ->assertJsonPath('comment.is_mine', true);

        $commentId = $post->json('comment.id');

        // The activity owner may delete a comment on their own activity.
        $this->actingAs($author)
            ->deleteJson("/api/activities/{$activity->id}/comments/{$commentId}")
            ->assertOk();

        $this->assertDatabaseMissing('activity_comments', ['id' => $commentId]);
    }

    public function test_a_third_party_cannot_delete_a_comment(): void
    {
        $author = User::factory()->create();
        $commenter = User::factory()->create();
        $stranger = User::factory()->create();
        $activity = $this->activityBy($author);
        $comment = $activity->comments()->create(['user_id' => $commenter->id, 'body' => 'Hi']);

        $this->actingAs($stranger)
            ->deleteJson("/api/activities/{$activity->id}/comments/{$comment->id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('activity_comments', ['id' => $comment->id]);
    }

    public function test_comment_requires_a_body(): void
    {
        $author = User::factory()->create();
        $activity = $this->activityBy($author);

        $this->actingAs(User::factory()->create())
            ->postJson("/api/activities/{$activity->id}/comments", ['body' => ''])
            ->assertStatus(422);
    }
}
