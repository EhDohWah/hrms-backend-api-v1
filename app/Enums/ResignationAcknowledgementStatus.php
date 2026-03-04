<?php

namespace App\Enums;

enum ResignationAcknowledgementStatus: string
{
    case Pending = 'Pending';
    case Acknowledged = 'Acknowledged';
    case Rejected = 'Rejected';

    /**
     * Get human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Acknowledged => 'Acknowledged',
            self::Rejected => 'Rejected',
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
