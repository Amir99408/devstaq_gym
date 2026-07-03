<?php

namespace App\Console\Commands;

use App\Enums\MemberStatus;
use App\Enums\PaymentStatus;
use App\Models\Member;
use App\Models\MonthlyPayment;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class GenerateMonthlyPayments extends Command
{
    protected $signature = 'payments:generate-monthly
                            {--month= : Billing month in YYYY-MM format (defaults to current month)}
                            {--dry-run : Preview without inserting}';

    protected $description = 'Generate monthly payment records for all active members';

    public function handle(): int
    {
        $month = $this->option('month')
            ? Carbon::createFromFormat('Y-m', $this->option('month'))->startOfMonth()
            : now()->startOfMonth();

        $billingMonth = $month->toDateString();

        $this->info("Generating payments for: {$month->format('F Y')}");

        $members = Member::active()
            ->with('currentPlan')
            ->get();

        $created = 0;
        $skipped = 0;

        foreach ($members as $member) {
            if (! $member->currentPlan) {
                $skipped++;
                continue;
            }

            $exists = MonthlyPayment::where('member_id', $member->id)
                ->where('billing_month', $billingMonth)
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            // Use the member's personal billing day; clamp to last day of month
            // to handle months shorter than the billing day (e.g. day 31 in Feb)
            $billingDay = $member->currentPlan->billing_day ?? 1;
            $dueDate    = $month->copy()->setDay(min($billingDay, $month->daysInMonth))->toDateString();

            if (! $this->option('dry-run')) {
                MonthlyPayment::create([
                    'member_id'     => $member->id,
                    'billing_month' => $billingMonth,
                    'amount_due'    => $member->currentPlan->monthly_fee_amount,
                    'due_date'      => $dueDate,
                    'status'        => PaymentStatus::Pending->value,
                ]);
            }

            $created++;
            $this->line("  Created: {$member->full_name} — RM {$member->currentPlan->monthly_fee_amount} — due {$dueDate}");
        }

        $this->info("Done. Created: {$created}, Skipped: {$skipped}");

        return self::SUCCESS;
    }
}
