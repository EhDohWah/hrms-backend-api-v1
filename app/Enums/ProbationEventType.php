<?php

namespace App\Enums;

enum ProbationEventType: string
{
    case Initial = 'initial';
    case Extension = 'extension';
    case Passed = 'passed';
    case Failed = 'failed';

    /**
     * Get human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Initial => 'Initial',
            self::Extension => 'Extension',
            self::Passed => 'Passed',
            self::Failed => 'Failed',
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
