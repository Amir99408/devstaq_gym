<?php

namespace App\Console\Commands;

use App\Enums\PaymentStatus;
use App\Models\MonthlyPayment;
use Illuminate\Console\Command;

class MarkOverduePayments extends Command
{
    protected $signature = 'payments:mark-overdue
                            {--dry-run : Preview without updating}';

    protected $description = 'Mark pending monthly payments past their due date as overdue';

    public function handle(): int
    {
        $query = MonthlyPayment::where('status', PaymentStatus::Pending)
            ->where('due_date', '<', today()->toDateString());

        $count = $query->count();

        if ($count === 0) {
            $this->info('No overdue payments found.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn("Would mark {$count} payment(s) as overdue (dry-run).");

            return self::SUCCESS;
        }

        $query->update(['status' => PaymentStatus::Overdue->value]);

        $this->info("Marked {$count} payment(s) as overdue.");

        return self::SUCCESS;
    }
}
