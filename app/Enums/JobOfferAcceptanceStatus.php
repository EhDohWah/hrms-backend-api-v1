<?php

namespace App\Enums;

enum JobOfferAcceptanceStatus: string
{
    case Pending = 'Pending';
    case Accepted = 'Accepted';
    case Declined = 'Declined';
    case Expired = 'Expired';
    case Withdrawn = 'Withdrawn';
    case UnderReview = 'Under Review';

    /**
     * Get human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Accepted => 'Accepted',
            self::Declined => 'Declined',
            self::Expired => 'Expired',
            self::Withdrawn => 'Withdrawn',
            self::UnderReview => 'Under Review',
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
