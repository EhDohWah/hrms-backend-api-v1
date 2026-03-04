<?php

namespace App\Enums;

enum HiredStatus: string
{
    case Hired = 'Hired';
    case NotHired = 'Not Hired';
    case Pending = 'Pending';

    /**
     * Get human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Hired => 'Hired',
            self::NotHired => 'Not Hired',
            self::Pending => 'Pending',
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
