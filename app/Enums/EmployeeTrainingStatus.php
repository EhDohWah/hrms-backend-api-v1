<?php

namespace App\Enums;

enum EmployeeTrainingStatus: string
{
    case Completed = 'Completed';
    case InProgress = 'In Progress';
    case Pending = 'Pending';

    /**
     * Get human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Completed => 'Completed',
            self::InProgress => 'In Progress',
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
