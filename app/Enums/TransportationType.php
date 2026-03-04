<?php

namespace App\Enums;

enum TransportationType: string
{
    case SmruVehicle = 'smru_vehicle';
    case PublicTransportation = 'public_transportation';
    case Air = 'air';
    case Other = 'other';

    /**
     * Get human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::SmruVehicle => 'SMRU vehicle',
            self::PublicTransportation => 'Public transportation',
            self::Air => 'Air',
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
