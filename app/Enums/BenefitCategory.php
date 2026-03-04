<?php

namespace App\Enums;

enum BenefitCategory: string
{
    case HealthWelfare = 'health_welfare';
    case SocialSecurity = 'social_security';
    case ProvidentFund = 'provident_fund';
    case SavingFund = 'saving_fund';

    /**
     * Get human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::HealthWelfare => 'Health & Welfare',
            self::SocialSecurity => 'Social Security',
            self::ProvidentFund => 'Provident Fund',
            self::SavingFund => 'Saving Fund',
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
