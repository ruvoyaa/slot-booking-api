<?php

namespace Database\Seeders;

use App\Models\Slot;
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
        User::query()->updateOrCreate(
            ['email' => 'demo@example.com'],
            [
                'name' => 'Demo User',
                'password' => 'password',
            ],
        );

        Slot::query()->updateOrCreate(
            ['start_at' => now()->addHour()->startOfHour(), 'end_at' => now()->addHours(2)->startOfHour()],
            [
                'capacity' => 5,
                'held_count' => 0,
                'confirmed_count' => 0,
            ],
        );

        Slot::query()->updateOrCreate(
            ['start_at' => now()->addHours(3)->startOfHour(), 'end_at' => now()->addHours(4)->startOfHour()],
            [
                'capacity' => 10,
                'held_count' => 0,
                'confirmed_count' => 0,
            ],
        );
    }
}
