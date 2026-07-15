<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $challenger_id
 * @property int $opponent_id
 * @property int $machine_id
 * @property int|null $gym_id
 * @property float $target_weight_kg
 * @property int $target_reps
 * @property int $target_sets
 * @property string $status
 * @property string|null $challenger_video_path
 * @property string|null $opponent_video_path
 * @property Carbon|null $challenger_submitted_at
 * @property Carbon|null $opponent_submitted_at
 * @property Carbon|null $voting_ends_at
 * @property int|null $winner_id
 * @property Carbon|null $resolved_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'challenger_id', 'opponent_id', 'machine_id', 'gym_id',
    'target_weight_kg', 'target_reps', 'target_sets', 'status',
    'challenger_video_path', 'opponent_video_path',
    'challenger_submitted_at', 'opponent_submitted_at',
    'voting_ends_at', 'winner_id', 'resolved_at',
])]
class Challenge extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_DECLINED = 'declined';
    public const STATUS_CANCELLED = 'cancelled';

    /** Arena voting window once both proofs are in. */
    public const VOTING_HOURS = 48;

    protected function casts(): array
    {
        return [
            'target_weight_kg' => 'float',
            'target_reps' => 'integer',
            'target_sets' => 'integer',
            'challenger_submitted_at' => 'datetime',
            'opponent_submitted_at' => 'datetime',
            'voting_ends_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function challenger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'challenger_id');
    }

    public function opponent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opponent_id');
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_id');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(ChallengeVote::class);
    }

    public function isParticipant(int $userId): bool
    {
        return $this->challenger_id === $userId || $this->opponent_id === $userId;
    }

    public function challengerVideoUrl(): ?string
    {
        return $this->challenger_video_path === null
            ? null
            : Storage::disk('public')->url($this->challenger_video_path);
    }

    public function opponentVideoUrl(): ?string
    {
        return $this->opponent_video_path === null
            ? null
            : Storage::disk('public')->url($this->opponent_video_path);
    }
}
