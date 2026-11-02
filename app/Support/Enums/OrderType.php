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
            self::DINE_IN => 'primary',
            self::TAKEOUT => 'warning',
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

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
