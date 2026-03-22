<?php

namespace Modules\Patient\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum SchoolType: string implements HasColor, HasLabel
{
    case NURSERY = 'nursery';
    case CRECHE = 'creche';
    case KINDERGARTEN = 'kindergarten';
    case PRIMARY = 'primary';
    case JUNIOR_HIGH = 'junior_high';
    case SENIOR_HIGH = 'senior_high';
    case VOCATIONAL = 'vocational';
    case TERTIARY = 'tertiary';
    case UNIVERSITY = 'university';
    case PROFESSIONAL = 'professional';
    case OTHER = 'other';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::NURSERY => 'Nursery School',
            self::CRECHE => 'Creche/Daycare',
            self::KINDERGARTEN => 'Kindergarten',
            self::PRIMARY => 'Primary School',
            self::JUNIOR_HIGH => 'Junior High School (JHS)',
            self::SENIOR_HIGH => 'Senior High School (SHS)',
            self::VOCATIONAL => 'Vocational/Technical School',
            self::TERTIARY => 'Tertiary College',
            self::UNIVERSITY => 'University',
            self::PROFESSIONAL => 'Professional Institute',
            self::OTHER => 'Other',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::NURSERY, self::CRECHE, self::KINDERGARTEN => 'warning',
            self::PRIMARY => 'info',
            self::JUNIOR_HIGH, self::SENIOR_HIGH => 'primary',
            self::VOCATIONAL => 'secondary',
            self::TERTIARY, self::UNIVERSITY => 'success',
            self::PROFESSIONAL => 'danger',
            self::OTHER => 'gray',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return [
            self::NURSERY->value => 'Nursery School',
            self::CRECHE->value => 'Creche/Daycare',
            self::KINDERGARTEN->value => 'Kindergarten',
            self::PRIMARY->value => 'Primary School',
            self::JUNIOR_HIGH->value => 'Junior High School (JHS)',
            self::SENIOR_HIGH->value => 'Senior High School (SHS)',
            self::VOCATIONAL->value => 'Vocational/Technical School',
            self::TERTIARY->value => 'Tertiary College',
            self::UNIVERSITY->value => 'University',
            self::PROFESSIONAL->value => 'Professional Institute',
            self::OTHER->value => 'Other',
        ];
    }

    public function getClassLevels(): array
    {
        return match ($this) {
            self::NURSERY => ['Nursery 1', 'Nursery 2'],
            self::CRECHE => ['Creche'],
            self::KINDERGARTEN => ['KGa 1', 'KGa 2'],
            self::PRIMARY => ['Primary 1', 'Primary 2', 'Primary 3', 'Primary 4', 'Primary 5', 'Primary 6'],
            self::JUNIOR_HIGH => ['JHS 1', 'JHS 2', 'JHS 3'],
            self::SENIOR_HIGH => ['SHS 1', 'SHS 2', 'SHS 3'],
            self::VOCATIONAL => ['Level 1', 'Level 2', 'Level 3', 'Level 4'],
            self::TERTIARY => ['Year 1', 'Year 2', 'Year 3', 'Year 4', 'Year 5'],
            self::UNIVERSITY => ['Year 1', 'Year 2', 'Year 3', 'Year 4', 'Year 5', 'Year 6'],
            self::PROFESSIONAL => ['Certificate', 'Diploma', 'Degree', 'Masters', 'PhD'],
            self::OTHER => [],
        };
    }

    public function requiresHostel(): bool
    {
        return in_array($this, [
            self::SENIOR_HIGH,
            self::TERTIARY,
            self::UNIVERSITY,
            self::VOCATIONAL,
        ]);
    }

    public function requiresCourse(): bool
    {
        return in_array($this, [
            self::TERTIARY,
            self::UNIVERSITY,
            self::PROFESSIONAL,
        ]);
    }

    public function isBasic(): bool
    {
        return in_array($this, [
            self::NURSERY,
            self::CRECHE,
            self::KINDERGARTEN,
            self::PRIMARY,
            self::JUNIOR_HIGH,
        ]);
    }

    public function isSecondary(): bool
    {
        return in_array($this, [
            self::SENIOR_HIGH,
            self::VOCATIONAL,
        ]);
    }

    public function isHigher(): bool
    {
        return in_array($this, [
            self::TERTIARY,
            self::UNIVERSITY,
            self::PROFESSIONAL,
        ]);
    }
}
