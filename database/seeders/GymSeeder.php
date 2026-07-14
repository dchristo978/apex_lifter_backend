<?php

namespace Database\Seeders;

use App\Models\Gym;
use Illuminate\Database\Seeder;

class GymSeeder extends Seeder
{
    /**
     * FTL branch coordinates are approximate placeholders —
     * verify each pinpoint on the ground before launch.
     */
    public function run(): void
    {
        $branches = [
            ['name' => 'FTL Puri Indah', 'address' => 'Puri Indah Mall, Jakarta Barat', 'latitude' => -6.1876, 'longitude' => 106.7382],
            ['name' => 'FTL Kelapa Gading', 'address' => 'Kelapa Gading, Jakarta Utara', 'latitude' => -6.1577, 'longitude' => 106.9089],
            ['name' => 'FTL Pantai Indah Kapuk', 'address' => 'PIK, Jakarta Utara', 'latitude' => -6.1090, 'longitude' => 106.7410],
            ['name' => 'FTL Kemang', 'address' => 'Kemang, Jakarta Selatan', 'latitude' => -6.2601, 'longitude' => 106.8135],
            ['name' => 'FTL Bintaro', 'address' => 'Bintaro, Tangerang Selatan', 'latitude' => -6.2762, 'longitude' => 106.7186],
            ['name' => 'FTL Tebet', 'address' => 'Tebet, Jakarta Selatan', 'latitude' => -6.2262, 'longitude' => 106.8582],
        ];

        foreach ($branches as $branch) {
            Gym::updateOrCreate(['name' => $branch['name']], $branch);
        }
    }
}
