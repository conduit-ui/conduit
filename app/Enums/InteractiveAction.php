<?php

namespace App\Enums;

enum InteractiveAction: string
{
    case ENABLE = 'enable';
    case DISABLE = 'disable';
    case STATUS = 'status';

    /**
     * Get all valid action values
     */
    public static function values(): array
    {
        return array_map(fn($case) => $case->value, self::cases());
    }

    /**
     * Check if a value is valid
     */
    public static function isValid(string $value): bool
    {
        return self::tryFrom($value) !== null;
    }
}