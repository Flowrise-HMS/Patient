<?php

namespace Modules\Patient\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum MaritalStatus: string implements HasColor, HasDescription, HasLabel
{
    case SINGLE = 'single';
    case MARRIED = 'married';
    case DIVORCED = 'divorced';
    case WIDOWED = 'widowed';
    case SEPARATED = 'separated';
    case COHABITING = 'cohabiting';
    case UNKNOWN = 'unknown';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::SINGLE => 'Single',
            self::MARRIED => 'Married',
            self::DIVORCED => 'Divorced',
            self::WIDOWED => 'Widowed',
            self::SEPARATED => 'Separated',
            self::COHABITING => 'Cohabiting',
            self::UNKNOWN => 'Unknown',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::SINGLE => 'info',
            self::MARRIED => 'success',
            self::DIVORCED => 'warning',
            self::WIDOWED => 'gray',
            self::SEPARATED => 'warning',
            self::COHABITING => 'primary',
            self::UNKNOWN => 'gray',
        };
    }

    public function getDescription(): string|Htmlable|null
    {
        return match ($this) {
            self::SINGLE => 'Not currently married or in a registered partnership.',
            self::MARRIED => 'Legally married or customary marriage recognized.',
            self::DIVORCED => 'Marriage legally dissolved.',
            self::WIDOWED => 'Spouse deceased.',
            self::SEPARATED => 'Married but living apart without divorce finalized.',
            self::COHABITING => 'Partners living together without formal marriage.',
            self::UNKNOWN => 'Marital status not collected or declined.',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
