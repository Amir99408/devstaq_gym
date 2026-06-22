<?php

namespace Database\Seeders;

use App\Models\GymSetting;
use Illuminate\Database\Seeder;

class GymSettingSeeder extends Seeder
{
    public function run(): void
    {
        GymSetting::firstOrCreate([], [
            'gym_name' => 'PowerFit Gym',
            'tagline' => 'Stronger Every Day',
            'about' => 'PowerFit Gym is a state-of-the-art fitness center dedicated to helping you reach your goals. Whether you are a beginner or a seasoned athlete, our expert trainers and premium equipment are here to support your journey.',
            'address' => '123 Fitness Street, Kuala Lumpur, Malaysia',
            'phone' => '+60 12-345 6789',
            'email' => 'info@powerfitgym.com',
            'whatsapp' => '+60123456789',
            'primary_color' => '#3b82f6',
            'secondary_color' => '#1e40af',
            'meta_title' => 'PowerFit Gym — Stronger Every Day',
            'meta_description' => 'Join PowerFit Gym and transform your fitness journey with expert trainers and premium facilities.',
        ]);
    }
}
