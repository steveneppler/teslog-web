<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DatabaseHelper
{
    /**
     * Build a driver-appropriate SQL expression that formats a timestamp column.
     *
     * SQLite's strftime() has no equivalent in PostgreSQL or MySQL, so each
     * supported format is expressed per driver. $column is interpolated into
     * raw SQL — only pass trusted, hard-coded column names.
     *
     * @param  string  $format  One of: hour, year-month, datetime
     */
    public static function formatDateTime(string $column, string $format): string
    {
        $driver = DB::connection()->getDriverName();

        return match ($format) {
            'hour' => match ($driver) {
                'pgsql' => "date_trunc('hour', {$column})",
                'sqlite' => "strftime('%Y-%m-%d %H:00:00', {$column})",
                default => "DATE_FORMAT({$column}, '%Y-%m-%d %H:00:00')",
            },
            'year-month' => match ($driver) {
                'pgsql' => "to_char({$column}, 'YYYY-MM')",
                'sqlite' => "strftime('%Y-%m', {$column})",
                default => "DATE_FORMAT({$column}, '%Y-%m')",
            },
            'datetime' => match ($driver) {
                'pgsql' => "to_char({$column}, 'YYYY-MM-DD HH24:MI:SS')",
                'sqlite' => "strftime('%Y-%m-%d %H:%M:%S', {$column})",
                default => "DATE_FORMAT({$column}, '%Y-%m-%d %H:%i:%s')",
            },
            default => throw new InvalidArgumentException("Unsupported date format [{$format}]."),
        };
    }

    /**
     * Build a driver-appropriate SQL expression for the absolute distance, in
     * seconds, between a timestamp column and a bound timestamp parameter.
     *
     * SQLite's JULIANDAY() exists on no other driver. Useful for ordering rows
     * by proximity to a given moment. As above, $column must be trusted.
     */
    public static function absTimeDiffSeconds(string $column, string $placeholder = '?'): string
    {
        return match (DB::connection()->getDriverName()) {
            'pgsql' => "ABS(EXTRACT(EPOCH FROM ({$column} - CAST({$placeholder} AS timestamp))))",
            'sqlite' => "ABS(JULIANDAY({$column}) - JULIANDAY({$placeholder})) * 86400",
            default => "ABS(TIMESTAMPDIFF(SECOND, {$column}, {$placeholder}))",
        };
    }
}
