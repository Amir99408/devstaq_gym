<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class MembershipPlanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement(['Basic', 'Premium', 'Student', 'VIP', 'Family']),
            'description' => $this->faker->sentence(),
            'registration_fee' => $this->faker->randomElement([0, 50, 100, 150]),
            'monthly_fee' => $this->faker->randomElement([50, 80, 100, 150, 200]),
            'features' => ['Gym access', 'Locker room', 'Free WiFi'],
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
