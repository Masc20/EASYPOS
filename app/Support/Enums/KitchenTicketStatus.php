<?php

namespace App\Support\Enums;

enum KitchenTicketStatus: string
{
    case NEW = 'new';
    case IN_PREP = 'in_prep';
    case READY = 'ready';
    case SERVED = 'served';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::NEW => 'New Order',
            self::IN_PREP => 'In Prep',
            self::READY => 'Ready',
            self::SERVED => 'Served',
            self::CANCELLED => 'Cancelled',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return array<int, string>
     */
    public static function activeValues(): array
    {
        return [
            self::NEW->value,
            self::IN_PREP->value,
            self::READY->value,
        ];
    }
}
