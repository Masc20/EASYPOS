<?php

namespace App\Support\Enums;

enum KitchenTicketItemStatus: string
{
    case PENDING = 'pending';
    case COOKING = 'cooking';
    case DONE = 'done';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::COOKING => 'Cooking',
            self::DONE => 'Done',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
