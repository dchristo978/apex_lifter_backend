<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $gender
 * @property Carbon|null $birth_date
 * @property float|null $body_weight_kg
 * @property Carbon|null $body_weight_updated_at
 * @property string|null $fcm_token
 * @property string|null $avatar_path
 * @property array<int, int>|null $featured_machine_ids
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'gender', 'birth_date', 'body_weight_kg', 'body_weight_updated_at', 'fcm_token', 'avatar_path', 'featured_machine_ids'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token', 'fcm_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'birth_date' => 'date',
            'body_weight_kg' => 'float',
            'body_weight_updated_at' => 'datetime',
            'featured_machine_ids' => 'array',
        ];
    }

    /**
     * The machine ids the lifter pins to the top of their public record list,
     * in display order. At most 3 (enforced on write); always a list of ints.
     *
     * @return array<int, int>
     */
    public function featuredMachineIds(): array
    {
        return array_values(array_map('intval', $this->featured_machine_ids ?? []));
    }

    public function workoutSets(): HasMany
    {
        return $this->hasMany(WorkoutSet::class);
    }

    public function checkins(): HasMany
    {
        return $this->hasMany(Checkin::class);
    }

    public function rankNotifications(): HasMany
    {
        return $this->hasMany(RankNotification::class);
    }

    public function challengesMade(): HasMany
    {
        return $this->hasMany(Challenge::class, 'challenger_id');
    }

    public function challengesReceived(): HasMany
    {
        return $this->hasMany(Challenge::class, 'opponent_id');
    }

    /**
     * Number of medals earned — one per challenge won. Shown under the avatar
     * on the public profile.
     */
    public function medalsCount(): int
    {
        return Challenge::query()
            ->where('winner_id', $this->id)
            ->where('status', Challenge::STATUS_COMPLETED)
            ->count();
    }

    public function age(): ?int
    {
        return $this->birth_date?->age;
    }

    /**
     * Consecutive-week gym streak. A calendar week (Mon–Sun) counts toward the
     * streak when the lifter logged at least one set in it. Counting starts at
     * the current week and walks backwards over uninterrupted weeks. The current
     * week not yet having a session does NOT break the streak — it stays alive
     * until the week ends — so when this week is empty, counting starts from
     * last week instead. Returns 0 once a full week is missed.
     */
    public function weekStreak(): int
    {
        // Distinct week-start (Monday) dates the lifter trained on. A lifter's
        // set history is small enough to fold in PHP, which keeps this portable
        // across the sqlite/mysql drivers rather than relying on DB week funcs.
        $weeks = $this->workoutSets()
            ->orderByDesc('performed_at')
            ->pluck('performed_at')
            ->map(fn (CarbonInterface $at) => $at->startOfWeek()->toDateString())
            ->flip();

        if ($weeks->isEmpty()) {
            return 0;
        }

        // now() is a CarbonImmutable in this app (see AppServiceProvider), so
        // every ->subWeek() must be reassigned — it never mutates in place.
        $cursor = now()->startOfWeek();

        // If nothing this week yet, the streak survives as long as last week
        // had a session; otherwise it's broken.
        if (! $weeks->has($cursor->toDateString())) {
            $cursor = $cursor->subWeek();

            if (! $weeks->has($cursor->toDateString())) {
                return 0;
            }
        }

        $streak = 0;
        while ($weeks->has($cursor->toDateString())) {
            $streak++;
            $cursor = $cursor->subWeek();
        }

        return $streak;
    }

    /**
     * Wide MVP brackets; narrower brackets can be introduced once
     * the user base is large enough to keep leaderboards populated.
     */
    public function ageBracket(): ?string
    {
        $age = $this->age();

        return match (true) {
            $age === null => null,
            $age < 18 => 'u18',
            $age < 30 => '18-29',
            $age < 40 => '30-39',
            default => '40+',
        };
    }

    public function weightClass(): ?string
    {
        $weight = $this->body_weight_kg;

        return match (true) {
            $weight === null => null,
            $weight < 60 => 'u60',
            $weight < 75 => '60-74',
            $weight < 90 => '75-89',
            default => '90+',
        };
    }

    /**
     * A recorded body weight older than 90 days is "stale": records tied to it
     * should be treated as unverified until the lifter weighs in again (MVP 3).
     */
    public const BODY_WEIGHT_MAX_AGE_DAYS = 90;

    public function bodyWeightStale(): bool
    {
        if ($this->body_weight_kg === null) {
            return false;
        }

        return $this->body_weight_updated_at === null
            || $this->body_weight_updated_at->diffInDays(now()) > self::BODY_WEIGHT_MAX_AGE_DAYS;
    }

    public function avatarUrl(): ?string
    {
        return $this->avatar_path === null
            ? null
            : Storage::disk('public')->url($this->avatar_path);
    }

    /**
     * The gym the lifter checks in at most often — used as their "home gym"
     * on the public profile. Falls back to the most recent check-in.
     */
    public function homeGym(): ?Gym
    {
        $gymId = $this->checkins()
            ->selectRaw('gym_id, COUNT(*) as total, MAX(checked_in_at) as last_at')
            ->groupBy('gym_id')
            ->orderByDesc('total')
            ->orderByDesc('last_at')
            ->value('gym_id');

        return $gymId === null ? null : Gym::find($gymId);
    }
}
