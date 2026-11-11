<?php

namespace App\Enums;

enum Role: string
{
    case Staff = 'staff';
    case Manager = 'manager';
    case Hr = 'hr';
    case Finance = 'finance';

    public function label(): string
    {
        return match ($this) {
            self::Staff => 'Staff',
            self::Manager => 'Manager',
            self::Hr => 'HR',
            self::Finance => 'Finance',
        };
    }
}
