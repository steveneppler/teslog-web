<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;

class DatabaseHelper
{
  public static function formatDateTime(string $column, string $format): string
  {
    $driver = DB::connection()->getDriverName();

    return match ($format) {
      'hour' => match ($driver) {
        'pgsql'  => "date_trunc('hour', {$column})",
        'sqlite' => "strftime('%Y-%m-%d %H:00:00', {$column})",
        default  => "DATE_FORMAT({$column}, '%Y-%m-%d %H:00:00')",
      },
      'year-month' => match ($driver) {
        'pgsql'  => "to_char({$column}, 'YYYY-MM')",
        'sqlite' => "strftime('%Y-%m', {$column})",
        default  => "DATE_FORMAT({$column}, '%Y-%m')",
      },
      'datetime' => match ($driver) {
        'pgsql'  => "to_char({$column}, 'YYYY-MM-DD HH24:MI:SS')",
        'sqlite' => "strftime('%Y-%m-%d %H:%M:%S', {$column})",
        default  => "DATE_FORMAT({$column}, '%Y-%m-%d %H:%i:%s')",
      },
    };
  }
}