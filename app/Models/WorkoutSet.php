<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $machine_id
 * @property int|null $gym_id
 * @property float $weight_kg
 * @property int $reps
 * @property float $estimated_1rm
 * @property Carbon $performed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'machine_id', 'gym_id', 'weight_kg', 'reps', 'estimated_1rm', 'performed_at'])]
class WorkoutSet extends Model
{
    use HasFactory;

    /**
     * How long after logging a set the lifter may still delete it. Past this
     * window the set is locked so leaderboards and 1RMs stay trustworthy.
     */
    public const EDIT_WINDOW_MINUTES = 30;

    protected function casts(): array
    {
        return [
            'weight_kg' => 'float',
            'estimated_1rm' => 'float',
            'performed_at' => 'datetime',
        ];
    }

    /**
     * Epley formula. A true single counts as its own 1RM.
     */
    public static function epley(float $weightKg, int $reps): float
    {
        if ($reps <= 1) {
            return round($weightKg, 2);
        }

        return round($weightKg * (1 + $reps / 30), 2);
    }

    /**
     * True while the set is still within its editable window.
     */
    public function isEditable(): bool
    {
        return $this->performed_at->gt(now()->subMinutes(self::EDIT_WINDOW_MINUTES));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }
}
