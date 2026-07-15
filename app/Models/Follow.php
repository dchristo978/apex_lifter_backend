<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A directed follow edge: $follower follows $followee.
 *
 * @property int $id
 * @property int $follower_id
 * @property int $followee_id
 */
class Follow extends Model
{
    protected $fillable = ['follower_id', 'followee_id'];

    public function follower(): BelongsTo
    {
        return $this->belongsTo(User::class, 'follower_id');
    }

    public function followee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'followee_id');
    }
}
