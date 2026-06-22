<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            GymSettingSeeder::class,
            UserSeeder::class,
            MembershipPlanSeeder::class,
            MemberSeeder::class,
        ]);
    }
}
