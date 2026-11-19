<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SqlDialect
{
    public static function driver(?string $connection = null): string
    {
        return DB::connection($connection)->getDriverName();
    }

    public static function castType(?string $driver = null): string
    {
        $driver ??= self::driver();

        return in_array($driver, ['sqlite', 'pgsql'], true) ? 'INTEGER' : 'UNSIGNED';
    }

    public static function periodExpression(string $column, string $interval = 'day', ?string $driver = null): string
    {
        $driver ??= self::driver();
        $column = self::manilaTimestamp($column, $driver);
        $interval = strtolower($interval);

        if ($driver === 'sqlite') {
            // An ISO week's year and number are determined by its Thursday.
            $thursday = "date({$column}, '-3 days', 'weekday 4')";

            return match ($interval) {
                'week' => "printf('%s-W%02d', strftime('%Y', {$thursday}), 1 + (CAST(strftime('%j', {$thursday}) AS INTEGER) - 1) / 7)",
                'month' => "strftime('%Y-%m', {$column})",
                'year' => "strftime('%Y', {$column})",
                default => "strftime('%Y-%m-%d', {$column})",
            };
        }

        if ($driver === 'pgsql') {
            return match ($interval) {
                'week' => "TO_CHAR({$column}, 'IYYY-\"W\"IW')",
                'month' => "TO_CHAR({$column}, 'YYYY-MM')",
                'year' => "TO_CHAR({$column}, 'YYYY')",
                default => "TO_CHAR({$column}, 'YYYY-MM-DD')",
            };
        }

        return match ($interval) {
            'week' => "DATE_FORMAT({$column}, '%x-W%v')",
            'month' => "DATE_FORMAT({$column}, '%Y-%m')",
            'year' => "DATE_FORMAT({$column}, '%Y')",
            default => "DATE({$column})",
        };
    }

    public static function hourExpression(string $column, ?string $driver = null): string
    {
        $driver ??= self::driver();
        $column = self::manilaTimestamp($column, $driver);

        if ($driver === 'sqlite') {
            return "CAST(strftime('%H', {$column}) AS INTEGER)";
        }

        if ($driver === 'pgsql') {
            return "EXTRACT(HOUR FROM {$column})::INTEGER";
        }

        return "HOUR({$column})";
    }

    // POS records are UTC. Philippine business time is UTC+08:00 year-round.
    private static function manilaTimestamp(string $column, string $driver): string
    {
        $column = self::validateColumn($column);

        return match ($driver) {
            'sqlite' => "datetime({$column}, '+8 hours')",
            'pgsql' => "({$column} + INTERVAL '8 hours')",
            default => "DATE_ADD({$column}, INTERVAL 8 HOUR)",
        };
    }

    private static function validateColumn(string $column): string
    {
        if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z_][a-zA-Z0-9_]*)?\z/', $column)) {
            throw new InvalidArgumentException("Invalid SQL column identifier: {$column}");
        }

        return $column;
    }
}
