<?php

namespace App\Enums;

/**
 * Whose courier it is: the restaurant's own staff or the platform's fleet. Dispatch by type comes in phase 4.
 */
enum CourierType: string
{
    case OWN = 'own';
    case FLEET = 'fleet';

    public function labelUz(): string
    {
        return match ($this) {
            self::OWN => "O'z kuryeri",
            self::FLEET => 'Rizqim kuryeri',
        };
    }

    /**
     * @return array<string, string> value => Uzbek label
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $type) => [$type->value => $type->labelUz()])->all();
    }
}
