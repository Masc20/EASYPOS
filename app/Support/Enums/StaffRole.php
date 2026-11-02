<?php

namespace App\Support\Enums;

enum StaffRole: string
{
    case OWNER = 'owner';
    case SUPER_ADMIN = 'super-admin';
    case BRANCH_MANAGER = 'branch-manager';
    case CASHIER = 'cashier';
    case COOK = 'cook';
    case CHEF = 'chef';

    public function label(): string
    {
        return match ($this) {
            self::OWNER => 'System Owner',
            self::SUPER_ADMIN => 'Super Administrator',
            self::BRANCH_MANAGER => 'Branch Manager',
            self::CASHIER => 'Cashier',
            self::COOK => 'Kitchen Cook',
            self::CHEF => 'Head Chef',
        };
    }

    public function isFloorStaff(): bool
    {
        return in_array($this, [self::CASHIER, self::COOK, self::CHEF]);
    }

    public function codePrefix(): string
    {
        return match ($this) {
            self::CASHIER => 'CSH',
            self::COOK => 'COK',
            self::CHEF => 'CHF',
            self::BRANCH_MANAGER => 'MGR',
            self::SUPER_ADMIN, self::OWNER => 'ADM',
        };
    }
}
