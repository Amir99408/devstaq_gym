<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'owner@gym.com'],
            [
                'name' => 'Gym Owner',
                'password' => Hash::make('password'),
                'role' => UserRole::Owner,
                'email_verified_at' => now(),
            ]
        );

        User::firstOrCreate(
            ['email' => 'staff@gym.com'],
            [
                'name' => 'Gym Staff',
                'password' => Hash::make('password'),
                'role' => UserRole::Staff,
                'email_verified_at' => now(),
            ]
        );
    }
}
