<?php

namespace App\Enums;

enum AccommodationType: string
{
    case SmruArrangement = 'smru_arrangement';
    case SelfArrangement = 'self_arrangement';
    case Other = 'other';

    /**
     * Get human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::SmruArrangement => 'SMRU arrangement',
            self::SelfArrangement => 'Self arrangement',
            self::Other => 'Other please specify',
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
