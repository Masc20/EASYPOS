<?php

namespace App\Support\Enums;

enum OrderStatus: string
{
    case PENDING = 'pending';
    case IN_KITCHEN = 'in_kitchen';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::IN_KITCHEN => 'In Kitchen',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::IN_KITCHEN => 'info',
            self::COMPLETED => 'success',
            self::CANCELLED => 'danger',
        };
    }
}
