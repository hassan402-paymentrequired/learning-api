<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@stepra.com'],
            [
                'name' => 'Admin',
                'password' => bcrypt('admin@stepra'),
                'email_verified_at' => now(),
                'is_admin' => true,
            ]
        );
        

        User::firstOrCreate(
            ['email' => 'hassan@stepra.com'],
            [
                'name' => 'Hassan',
                'password' => bcrypt('hassan@stepra'),
                'email_verified_at' => now(),
                'is_admin' => false,
            ]
        );
    }
}
