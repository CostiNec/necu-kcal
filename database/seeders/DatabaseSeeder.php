<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(FoodSeeder::class);

        $user = User::factory()->create([
            'name' => 'Test User',
            'username' => 'testuser',
            'email' => 'test@example.com',
        ]);

        $user->profile()->create([
            'unit_system' => 'metric',
            'timezone' => 'Europe/Bucharest',
            'onboarding_completed_at' => now(),
        ]);
        $user->nutritionTarget()->create([
            'calories' => 2100,
            'protein' => 140,
            'carbohydrates' => 230,
            'fat' => 70,
            'fibre' => 30,
        ]);
    }
}
