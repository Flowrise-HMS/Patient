<?php

namespace Modules\Patient\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum EducationLevel: string implements HasColor, HasLabel
{
    case NONE = 'none';
    case PRIMARY = 'primary';
    case JUNIOR_SECONDARY = 'junior_secondary';
    case SENIOR_SECONDARY = 'senior_secondary';
    case VOCATIONAL = 'vocational';
    case TERTIARY = 'tertiary';
    case POSTGRADUATE = 'postgraduate';
    case UNKNOWN = 'unknown';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::NONE => 'No Formal Education',
            self::PRIMARY => 'Primary School',
            self::JUNIOR_SECONDARY => 'Junior Secondary (JHS)',
            self::SENIOR_SECONDARY => 'Senior Secondary (SHS)',
            self::VOCATIONAL => 'Vocational/Technical',
            self::TERTIARY => 'Tertiary (University/College)',
            self::POSTGRADUATE => 'Postgraduate',
            self::UNKNOWN => 'Unknown',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::NONE => 'gray',
            self::PRIMARY => 'info',
            self::JUNIOR_SECONDARY => 'primary',
            self::SENIOR_SECONDARY => 'primary',
            self::VOCATIONAL => 'warning',
            self::TERTIARY => 'success',
            self::POSTGRADUATE => 'success',
            self::UNKNOWN => 'gray',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function isFormal(): bool
    {
        return $this !== self::NONE && $this !== self::UNKNOWN;
    }

    public function isHigher(): bool
    {
        return in_array($this, [
            self::TERTIARY,
            self::POSTGRADUATE,
        ]);
    }
}
