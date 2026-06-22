<?php

namespace Database\Seeders;

use App\Models\MembershipPlan;
use Illuminate\Database\Seeder;

class MembershipPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Basic',
                'description' => 'Perfect for beginners. Access to all gym equipment during standard hours.',
                'registration_fee' => 100.00,
                'monthly_fee' => 80.00,
                'features' => ['Full gym access', 'Locker room', 'Free WiFi', 'Basic fitness assessment'],
                'sort_order' => 1,
            ],
            [
                'name' => 'Premium',
                'description' => 'Our most popular plan. Unlimited access plus group classes.',
                'registration_fee' => 150.00,
                'monthly_fee' => 120.00,
                'features' => ['24/7 gym access', 'Unlimited group classes', 'Locker room', 'Free WiFi', 'Monthly fitness assessment', 'Nutrition guide'],
                'sort_order' => 2,
            ],
            [
                'name' => 'Student',
                'description' => 'Special rate for full-time students with valid student ID.',
                'registration_fee' => 50.00,
                'monthly_fee' => 50.00,
                'features' => ['Gym access (6am–6pm)', 'Locker room', 'Free WiFi'],
                'sort_order' => 3,
            ],
        ];

        foreach ($plans as $plan) {
            MembershipPlan::firstOrCreate(['name' => $plan['name']], array_merge($plan, ['is_active' => true]));
        }
    }
}
