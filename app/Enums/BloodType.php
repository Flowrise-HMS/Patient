<?php

namespace Modules\Patient\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum BloodType: string implements HasColor, HasDescription, HasLabel
{
    case A_POSITIVE = 'A+';
    case A_NEGATIVE = 'A-';
    case B_POSITIVE = 'B+';
    case B_NEGATIVE = 'B-';
    case AB_POSITIVE = 'AB+';
    case AB_NEGATIVE = 'AB-';
    case O_POSITIVE = 'O+';
    case O_NEGATIVE = 'O-';
    case UNKNOWN = 'unknown';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::A_POSITIVE => 'A+',
            self::A_NEGATIVE => 'A-',
            self::B_POSITIVE => 'B+',
            self::B_NEGATIVE => 'B-',
            self::AB_POSITIVE => 'AB+',
            self::AB_NEGATIVE => 'AB-',
            self::O_POSITIVE => 'O+',
            self::O_NEGATIVE => 'O-',
            self::UNKNOWN => 'Unknown',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::A_POSITIVE, self::A_NEGATIVE => 'danger',
            self::B_POSITIVE, self::B_NEGATIVE => 'info',
            self::AB_POSITIVE, self::AB_NEGATIVE => 'primary',
            self::O_POSITIVE, self::O_NEGATIVE => 'success',
            self::UNKNOWN => 'gray',
        };
    }

    public function getDescription(): string|Htmlable|null
    {
        return match ($this) {
            self::A_POSITIVE => 'Blood group A, Rh positive.',
            self::A_NEGATIVE => 'Blood group A, Rh negative.',
            self::B_POSITIVE => 'Blood group B, Rh positive.',
            self::B_NEGATIVE => 'Blood group B, Rh negative.',
            self::AB_POSITIVE => 'Blood group AB, Rh positive.',
            self::AB_NEGATIVE => 'Blood group AB, Rh negative.',
            self::O_POSITIVE => 'Blood group O, Rh positive.',
            self::O_NEGATIVE => 'Blood group O, Rh negative (universal red cell donor).',
            self::UNKNOWN => 'ABO/Rh not known or not recorded.',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function isRhesusPositive(): bool
    {
        return in_array($this, [
            self::A_POSITIVE,
            self::B_POSITIVE,
            self::AB_POSITIVE,
            self::O_POSITIVE,
        ]);
    }

    public function canDonateTo(self $recipient): bool
    {
        $compatibility = [
            self::A_POSITIVE->value => ['A+', 'A-', 'AB+', 'AB-'],
            self::A_NEGATIVE->value => ['A-', 'AB-'],
            self::B_POSITIVE->value => ['B+', 'B-', 'AB+', 'AB-'],
            self::B_NEGATIVE->value => ['B-', 'AB-'],
            self::AB_POSITIVE->value => ['AB+', 'AB-'],
            self::AB_NEGATIVE->value => ['AB-'],
            self::O_POSITIVE->value => ['A+', 'B+', 'O+', 'AB+'],
            self::O_NEGATIVE->value => ['O-', 'O+', 'A-', 'B-', 'AB-', 'AB+'],
        ];

        return in_array($recipient->value, $compatibility[$this->value] ?? []);
    }
}
