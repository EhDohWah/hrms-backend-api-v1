<?php

namespace App\Enums;

enum IdentificationType: string
{
    case TenYearsID = '10YearsID';
    case BurmeseID = 'BurmeseID';
    case CI = 'CI';
    case Borderpass = 'Borderpass';
    case ThaiID = 'ThaiID';
    case Passport = 'Passport';
    case Other = 'Other';

    public function label(): string
    {
        return match ($this) {
            self::TenYearsID => '10 Years ID',
            self::BurmeseID => 'Burmese ID',
            self::CI => 'CI',
            self::Borderpass => 'Borderpass',
            self::ThaiID => 'Thai ID',
            self::Passport => 'Passport',
            self::Other => 'Other',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
