<?php

namespace App\Enums;

enum MemberStatus: string
{
    case Inquiry = 'inquiry';
    case PendingPayment = 'pending_payment';
    case Active = 'active';
    case Inactive = 'inactive';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Inquiry => 'Inquiry',
            self::PendingPayment => 'Pending Payment',
            self::Active => 'Active',
            self::Inactive => 'Inactive',
            self::Suspended => 'Suspended',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Inquiry => 'yellow',
            self::PendingPayment => 'orange',
            self::Active => 'green',
            self::Inactive => 'zinc',
            self::Suspended => 'red',
        };
    }

    public function isActive(): bool
    {
        return $this === self::Active;
    }
}
