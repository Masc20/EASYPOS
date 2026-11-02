<?php

namespace App\Support\Enums;

enum PaymentStatus: string
{
    case UNPAID = 'unpaid';
    case PARTIALLY_PAID = 'partially_paid';
    case PAID = 'paid';
    case REFUNDED = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::UNPAID => 'Unpaid',
            self::PARTIALLY_PAID => 'Partially Paid',
            self::PAID => 'Paid',
            self::REFUNDED => 'Refunded',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::UNPAID => 'danger',
            self::PARTIALLY_PAID => 'warning',
            self::PAID => 'success',
            self::REFUNDED => 'gray',
        };
    }

    public static function labelFor(string $value): string
    {
        return self::tryFrom($value)?->label() ?? ucfirst(str_replace('_', ' ', $value));
    }

    public static function colorFor(string $value): string
    {
        return self::tryFrom($value)?->color() ?? 'gray';
    }
}
