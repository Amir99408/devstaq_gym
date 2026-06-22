<?php

namespace Database\Seeders;

use App\Enums\MemberStatus;
use App\Enums\PaymentStatus;
use App\Models\Member;
use App\Models\MemberPlan;
use App\Models\MembershipPlan;
use App\Models\MonthlyPayment;
use App\Models\RegistrationPayment;
use App\Models\User;
use Illuminate\Database\Seeder;

class MemberSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::where('email', 'owner@gym.com')->first();
        $plans = MembershipPlan::all()->keyBy('name');

        $members = [
            ['name' => 'Ahmad Faris', 'phone' => '+60 11-1111 1111', 'email' => 'ahmad@example.com', 'plan' => 'Premium', 'status' => MemberStatus::Active, 'paid' => true],
            ['name' => 'Siti Nurul', 'phone' => '+60 11-2222 2222', 'email' => 'siti@example.com', 'plan' => 'Basic', 'status' => MemberStatus::Active, 'paid' => false],
            ['name' => 'Raj Kumar', 'phone' => '+60 11-3333 3333', 'email' => 'raj@example.com', 'plan' => 'Student', 'status' => MemberStatus::Active, 'paid' => false],
            ['name' => 'Lee Mei Ling', 'phone' => '+60 11-4444 4444', 'email' => 'mei@example.com', 'plan' => 'Premium', 'status' => MemberStatus::Active, 'paid' => true],
            ['name' => 'Hassan Ali', 'phone' => '+60 11-5555 5555', 'email' => 'hassan@example.com', 'plan' => 'Basic', 'status' => MemberStatus::PendingPayment, 'paid' => false],
            ['name' => 'Priya Sharma', 'phone' => '+60 11-6666 6666', 'email' => 'priya@example.com', 'plan' => 'Basic', 'status' => MemberStatus::Active, 'paid' => true],
            ['name' => 'Tan Wei Jie', 'phone' => '+60 11-7777 7777', 'email' => 'weijie@example.com', 'plan' => 'Student', 'status' => MemberStatus::Active, 'paid' => false],
            ['name' => 'Nurul Ain', 'phone' => '+60 11-8888 8888', 'email' => 'ain@example.com', 'plan' => 'Premium', 'status' => MemberStatus::Inquiry, 'paid' => false],
        ];

        foreach ($members as $i => $data) {
            $plan = $plans->get($data['plan']);

            $member = Member::firstOrCreate(
                ['phone' => $data['phone']],
                [
                    'full_name' => $data['name'],
                    'email' => $data['email'],
                    'date_of_birth' => now()->subYears(rand(20, 40))->subDays(rand(0, 365))->toDateString(),
                    'address' => 'Kuala Lumpur, Malaysia',
                    'status' => $data['status'],
                    'created_by' => $owner->id,
                ]
            );

            if (! $plan || in_array($data['status'], [MemberStatus::Inquiry])) {
                continue;
            }

            $memberPlan = MemberPlan::firstOrCreate(
                ['member_id' => $member->id, 'membership_plan_id' => $plan->id],
                [
                    'registration_fee_amount' => $plan->registration_fee,
                    'monthly_fee_amount' => $plan->monthly_fee,
                    'start_date' => now()->subMonths(rand(1, 6))->toDateString(),
                    'created_by' => $owner->id,
                ]
            );

            RegistrationPayment::firstOrCreate(
                ['member_id' => $member->id],
                [
                    'member_plan_id' => $memberPlan->id,
                    'amount' => $plan->registration_fee,
                    'status' => $data['status'] === MemberStatus::PendingPayment
                        ? PaymentStatus::Pending
                        : PaymentStatus::Verified,
                    'verified_at' => $data['status'] === MemberStatus::Active ? now()->subMonths(rand(1, 3)) : null,
                    'verified_by' => $data['status'] === MemberStatus::Active ? $owner->id : null,
                ]
            );

            if ($data['status'] === MemberStatus::Active) {
                MonthlyPayment::firstOrCreate(
                    ['member_id' => $member->id, 'billing_month' => now()->startOfMonth()->toDateString()],
                    [
                        'amount_due' => $plan->monthly_fee,
                        'status' => $data['paid'] ? PaymentStatus::Verified : PaymentStatus::Pending,
                        'due_date' => now()->startOfMonth()->addDays(7)->toDateString(),
                        'verified_at' => $data['paid'] ? now()->subDays(rand(1, 5)) : null,
                        'verified_by' => $data['paid'] ? $owner->id : null,
                    ]
                );
            }
        }
    }
}
