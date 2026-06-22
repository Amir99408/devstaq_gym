<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Submitted = 'submitted';
    case Verified = 'verified';
    case Rejected = 'rejected';
    case Overdue = 'overdue';
    case Waived = 'waived';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Submitted => 'Awaiting Verification',
            self::Verified => 'Verified',
            self::Rejected => 'Rejected',
            self::Overdue => 'Overdue',
            self::Waived => 'Waived',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'zinc',
            self::Submitted => 'yellow',
            self::Verified => 'green',
            self::Rejected => 'red',
            self::Overdue => 'red',
            self::Waived => 'blue',
        };
    }

    public function isPaid(): bool
    {
        return in_array($this, [self::Verified, self::Waived]);
    }

    public function needsAction(): bool
    {
        return in_array($this, [self::Pending, self::Submitted, self::Overdue]);
    }
}
