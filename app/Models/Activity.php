<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A single feed-worthy event by a lifter. The `meta` array carries the
 * denormalised display payload for its `type` (machine name, weight, gym, etc.)
 * so the feed renders without touching the source tables.
 *
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property int|null $subject_id
 * @property array<string, mixed>|null $meta
 * @property Carbon $created_at
 */
class Activity extends Model
{
    public const TYPE_PR = 'pr';

    public const TYPE_MEDAL = 'medal';

    public const TYPE_CHECKIN = 'checkin';

    protected $fillable = ['user_id', 'type', 'subject_id', 'meta'];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }

    /**
     * Record a feed activity for a lifter. `meta` should already hold every
     * field the feed needs to display this event.
     *
     * @param  array<string, mixed>  $meta
     */
    public static function record(int $userId, string $type, ?int $subjectId, array $meta): self
    {
        return self::create([
            'user_id' => $userId,
            'type' => $type,
            'subject_id' => $subjectId,
            'meta' => $meta,
        ]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function kudos(): HasMany
    {
        return $this->hasMany(ActivityKudos::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ActivityComment::class);
    }
}
