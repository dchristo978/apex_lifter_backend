<?php

namespace Database\Seeders;

use App\Models\Machine;
use Illuminate\Database\Seeder;

class MachineSeeder extends Seeder
{
    public function run(): void
    {
        $machines = [
            // Chest
            ['name' => 'Chest Press', 'category' => 'chest'],
            ['name' => 'Incline Chest Press', 'category' => 'chest'],
            ['name' => 'Pec Fly / Rear Delt', 'category' => 'chest'],
            ['name' => 'Smith Machine (Bench Press)', 'category' => 'chest'],
            // Back
            ['name' => 'Lat Pulldown', 'category' => 'back'],
            ['name' => 'Seated Row', 'category' => 'back'],
            ['name' => 'High Row', 'category' => 'back'],
            ['name' => 'Low Row', 'category' => 'back'],
            ['name' => 'Assisted Pull-Up', 'category' => 'back'],
            // Shoulders
            ['name' => 'Shoulder Press', 'category' => 'shoulders'],
            ['name' => 'Lateral Raise Machine', 'category' => 'shoulders'],
            // Arms
            ['name' => 'Biceps Curl Machine', 'category' => 'arms'],
            ['name' => 'Triceps Extension Machine', 'category' => 'arms'],
            ['name' => 'Cable Crossover', 'category' => 'arms'],
            // Legs
            ['name' => 'Leg Press', 'category' => 'legs'],
            ['name' => 'Hack Squat', 'category' => 'legs'],
            ['name' => 'Leg Extension', 'category' => 'legs'],
            ['name' => 'Seated Leg Curl', 'category' => 'legs'],
            ['name' => 'Standing Calf Raise', 'category' => 'legs'],
            ['name' => 'Hip Abductor', 'category' => 'legs'],
            ['name' => 'Hip Adductor', 'category' => 'legs'],
            ['name' => 'Glute Kickback Machine', 'category' => 'legs'],
            ['name' => 'Smith Machine (Squat)', 'category' => 'legs'],
            // Core
            ['name' => 'Abdominal Crunch Machine', 'category' => 'core'],
            ['name' => 'Rotary Torso', 'category' => 'core'],
            ['name' => 'Back Extension', 'category' => 'core'],
        ];

        foreach ($machines as $machine) {
            Machine::updateOrCreate(
                ['name' => $machine['name']],
                [...$machine, 'brand' => 'Shua Fitness'],
            );
        }
    }
}
