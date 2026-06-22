<?php

namespace App\Enums;

enum UserRole: string
{
    case Owner = 'owner';
    case Staff = 'staff';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::Owner  => 'Owner',
            self::Staff  => 'Staff',
            self::Member => 'Member',
        };
    }

    public function canManageSettings(): bool
    {
        return $this === self::Owner;
    }
}
