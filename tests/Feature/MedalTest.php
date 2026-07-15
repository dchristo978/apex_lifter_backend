<?php

namespace Tests\Feature;

use App\Models\Challenge;
use App\Models\Machine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MedalTest extends TestCase
{
    use RefreshDatabase;

    private function completedWin(User $winner, User $loser): Challenge
    {
        $machine = Machine::create(['name' => 'Chest Press', 'brand' => 'Acme', 'category' => 'chest']);

        return Challenge::create([
            'challenger_id' => $winner->id,
            'opponent_id' => $loser->id,
            'machine_id' => $machine->id,
            'target_weight_kg' => 80,
            'target_reps' => 8,
            'target_sets' => 3,
            'status' => Challenge::STATUS_COMPLETED,
            'winner_id' => $winner->id,
            'resolved_at' => now(),
        ]);
    }

    public function test_medal_case_lists_wins_and_marks_ownership(): void
    {
        $winner = User::factory()->create();
        $loser = User::factory()->create();
        $this->completedWin($winner, $loser);

        $this->actingAs($winner)
            ->getJson("/api/users/{$winner->id}/medals")
            ->assertOk()
            ->assertJsonPath('is_owner', true)
            ->assertJsonPath('medals.0.machine_name', 'Chest Press')
            ->assertJsonPath('medals.0.defeated.id', $loser->id)
            ->assertJsonPath('medals.0.note', null);

        $this->actingAs($loser)
            ->getJson("/api/users/{$winner->id}/medals")
            ->assertOk()
            ->assertJsonPath('is_owner', false)
            ->assertJsonCount(1, 'medals');

        // The loser's own case is empty.
        $this->actingAs($loser)
            ->getJson("/api/users/{$loser->id}/medals")
            ->assertOk()
            ->assertJsonCount(0, 'medals');
    }

    public function test_winner_can_write_and_clear_a_medal_story(): void
    {
        $winner = User::factory()->create();
        $loser = User::factory()->create();
        $challenge = $this->completedWin($winner, $loser);

        $this->actingAs($winner)
            ->patchJson("/api/challenges/{$challenge->id}/medal-note", ['note' => 'Best comeback of my life.'])
            ->assertOk()
            ->assertJsonPath('medal_note', 'Best comeback of my life.');

        $this->actingAs($winner)
            ->patchJson("/api/challenges/{$challenge->id}/medal-note", ['note' => '  '])
            ->assertOk()
            ->assertJsonPath('medal_note', null);
    }

    public function test_story_is_rejected_over_100_words(): void
    {
        $winner = User::factory()->create();
        $loser = User::factory()->create();
        $challenge = $this->completedWin($winner, $loser);

        $this->actingAs($winner)
            ->patchJson("/api/challenges/{$challenge->id}/medal-note", [
                'note' => implode(' ', array_fill(0, 101, 'gym')),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('note');
    }

    public function test_only_the_winner_can_write_the_story(): void
    {
        $winner = User::factory()->create();
        $loser = User::factory()->create();
        $challenge = $this->completedWin($winner, $loser);

        $this->actingAs($loser)
            ->patchJson("/api/challenges/{$challenge->id}/medal-note", ['note' => 'I actually won.'])
            ->assertForbidden();
    }
}
