<?php

namespace App\Support\Enums;

enum OrderType: string
{
    case DINE_IN = 'dine_in';
    case TAKEOUT = 'takeout';

    public function label(): string
    {
        return match ($this) {
            self::DINE_IN => 'Dine In',
            self::TAKEOUT => 'Takeout',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DINE_IN => 'info',
            self::TAKEOUT => 'warning',
        };
    }
}
