<?php

namespace App\Enums;

enum EmployeeStatus: string
{
    case LocalId = 'Local ID Staff';
    case LocalNonId = 'Local non ID Staff';
    case Expats = 'Expats (Local)';

    /**
     * Get human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::LocalId => 'Local ID Staff',
            self::LocalNonId => 'Local non ID Staff',
            self::Expats => 'Expats (Local)',
        };
    }

    /**
     * Get all values as an array.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
