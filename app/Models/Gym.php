<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $address
 * @property float $latitude
 * @property float $longitude
 * @property int $checkin_radius_m
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'address', 'latitude', 'longitude', 'checkin_radius_m'])]
class Gym extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function checkins(): HasMany
    {
        return $this->hasMany(Checkin::class);
    }

    /**
     * Distance in meters from the given coordinates (haversine).
     */
    public function distanceFromM(float $latitude, float $longitude): float
    {
        $earthRadiusM = 6371000;
        $latFrom = deg2rad($latitude);
        $latTo = deg2rad($this->latitude);
        $latDelta = $latTo - $latFrom;
        $lonDelta = deg2rad($this->longitude - $longitude);

        $a = sin($latDelta / 2) ** 2 + cos($latFrom) * cos($latTo) * sin($lonDelta / 2) ** 2;

        return 2 * $earthRadiusM * atan2(sqrt($a), sqrt(1 - $a));
    }
}
