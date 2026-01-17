<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::firstOrCreate(
            ['email' => 'balogun@initsng.com'],
            [
                'name' => 'Test User',
                'password' => bcrypt('1234567890'),
                'email_verified_at' => now(),
            ]
        );

        // $this->call([
        //     SubscriptionPlanSeeder::class, 
        //     SubjectSeeder::class,
        //     ExamSeeder::class,   
        // ]);
    }
}
