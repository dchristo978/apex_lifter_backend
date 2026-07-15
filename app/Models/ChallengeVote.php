<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $challenge_id
 * @property int $voter_id
 * @property string $choice
 * @property array<string, bool>|null $criteria
 * @property string|null $reason_code
 * @property string|null $reason_text
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['challenge_id', 'voter_id', 'choice', 'criteria', 'reason_code', 'reason_text'])]
class ChallengeVote extends Model
{
    use HasFactory;

    public const CHOICE_CHALLENGER = 'challenger';
    public const CHOICE_OPPONENT = 'opponent';
    public const CHOICE_INVALID = 'invalid';

    /** Suggested rejection reasons surfaced as a dropdown in the app. */
    public const REASON_CODES = [
        'load_too_light',   // weight looked lighter than claimed
        'incomplete_reps',  // rep/set count not met
        'wrong_machine',    // used the wrong machine
        'bad_form',         // invalid form / cheating
        'partial_range',    // partial range of motion
        'video_unclear',    // proof video unclear / unconvincing
        'other',            // free-text
    ];

    protected function casts(): array
    {
        return [
            'criteria' => 'array',
        ];
    }

    public function challenge(): BelongsTo
    {
        return $this->belongsTo(Challenge::class);
    }

    public function voter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voter_id');
    }
}
