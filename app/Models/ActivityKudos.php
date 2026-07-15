<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A kudos (like) by a lifter on a feed activity.
 *
 * @property int $id
 * @property int $activity_id
 * @property int $user_id
 */
class ActivityKudos extends Model
{
    protected $table = 'activity_kudos';

    protected $fillable = ['activity_id', 'user_id'];

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
