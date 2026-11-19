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
        $column = self::validateColumn($column);
        $driver ??= self::driver();
        $interval = strtolower($interval);

        if ($driver === 'sqlite') {
            return match ($interval) {
                'week' => "strftime('%Y-W%W', {$column})",
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
        $column = self::validateColumn($column);
        $driver ??= self::driver();

        if ($driver === 'sqlite') {
            return "CAST(strftime('%H', {$column}) AS INTEGER)";
        }

        if ($driver === 'pgsql') {
            return "EXTRACT(HOUR FROM {$column})::INTEGER";
        }

        return "HOUR({$column})";
    }

    private static function validateColumn(string $column): string
    {
        if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z_][a-zA-Z0-9_]*)?\z/', $column)) {
            throw new InvalidArgumentException("Invalid SQL column identifier: {$column}");
        }

        return $column;
    }
}
