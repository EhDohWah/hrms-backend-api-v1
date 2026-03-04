<?php

namespace App\Enums;

enum BenefitSettingType: string
{
    case Percentage = 'percentage';
    case Boolean = 'boolean';
    case Numeric = 'numeric';

    /**
     * Get human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Percentage => 'Percentage',
            self::Boolean => 'Boolean',
            self::Numeric => 'Numeric',
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
