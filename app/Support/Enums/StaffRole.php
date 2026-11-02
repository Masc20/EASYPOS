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

    public function color(): string
    {
        return match ($this) {
            self::OWNER, self::SUPER_ADMIN => 'danger',
            self::BRANCH_MANAGER => 'warning',
            self::CASHIER => 'primary',
            self::CHEF, self::COOK => 'success',
        };
    }

    public static function colorFor(string $value): string
    {
        return self::tryFrom($value)?->color() ?? 'gray';
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

    /**
     * @return array<int, string>
     */
    public static function floorValues(): array
    {
        return [
            self::CASHIER->value,
            self::COOK->value,
            self::CHEF->value,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function managementValues(): array
    {
        return [
            self::OWNER->value,
            self::SUPER_ADMIN->value,
            self::BRANCH_MANAGER->value,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function executiveValues(): array
    {
        return [
            self::OWNER->value,
            self::SUPER_ADMIN->value,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function floorOptions(): array
    {
        return [
            self::CASHIER->value => 'Cashier (POS Register)',
            self::COOK->value => 'Kitchen Cook (KDS)',
            self::CHEF->value => 'Head Chef (Menu & Kitchen)',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function managementOptions(): array
    {
        return [
            self::BRANCH_MANAGER->value => self::BRANCH_MANAGER->label(),
            self::SUPER_ADMIN->value => self::SUPER_ADMIN->label(),
            self::OWNER->value => self::OWNER->label(),
        ];
    }
}
