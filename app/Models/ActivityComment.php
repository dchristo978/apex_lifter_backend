<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A comment by a lifter on a feed activity.
 *
 * @property int $id
 * @property int $activity_id
 * @property int $user_id
 * @property string $body
 * @property Carbon|null $created_at
 */
class ActivityComment extends Model
{
    protected $fillable = ['activity_id', 'user_id', 'body'];

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
