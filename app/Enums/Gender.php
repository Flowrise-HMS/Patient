<?php

namespace Modules\Patient\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum Gender: string implements HasColor, HasDescription, HasLabel
{
    case MALE = 'male';
    case FEMALE = 'female';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::MALE => 'Male',
            self::FEMALE => 'Female',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::MALE => 'info',
            self::FEMALE => 'danger',
        };
    }

    public function getDescription(): string|Htmlable|null
    {
        return match ($this) {
            self::MALE => 'Patient recorded as male for demographics and reporting.',
            self::FEMALE => 'Patient recorded as female for demographics and reporting.',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
