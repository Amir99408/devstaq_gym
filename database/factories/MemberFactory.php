<?php

namespace Database\Factories;

use App\Enums\MemberStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MemberFactory extends Factory
{
    public function definition(): array
    {
        return [
            'full_name' => $this->faker->name(),
            'phone' => $this->faker->phoneNumber(),
            'email' => $this->faker->safeEmail(),
            'date_of_birth' => $this->faker->dateTimeBetween('-50 years', '-18 years')->format('Y-m-d'),
            'address' => $this->faker->address(),
            'emergency_contact_name' => $this->faker->name(),
            'emergency_contact_phone' => $this->faker->phoneNumber(),
            'status' => MemberStatus::Active,
            'created_by' => User::factory(),
        ];
    }

    public function inquiry(): static
    {
        return $this->state(['status' => MemberStatus::Inquiry]);
    }

    public function pendingPayment(): static
    {
        return $this->state(['status' => MemberStatus::PendingPayment]);
    }

    public function inactive(): static
    {
        return $this->state(['status' => MemberStatus::Inactive]);
    }
}
