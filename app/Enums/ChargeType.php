<?php

namespace App\Enums;

enum ChargeType: string
{
    case Ac = 'ac';
    case Dc = 'dc';
    case Supercharger = 'supercharger';

    public function label(): string
    {
        return match ($this) {
            self::Ac => 'AC',
            self::Dc => 'DC',
            self::Supercharger => 'Supercharger',
        };
    }

    public function isDcLike(): bool
    {
        return match ($this) {
            self::Dc, self::Supercharger => true,
            default => false,
        };
    }
}
