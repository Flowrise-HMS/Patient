<?php

namespace Modules\Patient\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum RelationshipType: string implements HasColor, HasLabel
{
    case SPOUSE = 'spouse';
    case PARENT = 'parent';
    case CHILD = 'child';
    case SIBLING = 'sibling';
    case GRANDPARENT = 'grandparent';
    case GRANDCHILD = 'grandchild';
    case UNCLE = 'uncle';
    case FRIEND = 'friend';
    case NEIGHBOR = 'neighbor';
    case COLLEAGUE = 'colleague';
    case GUARDIAN = 'guardian';
    case CAREGIVER = 'caregiver';
    case OTHER = 'other';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::SPOUSE => 'Spouse',
            self::PARENT => 'Parent',
            self::CHILD => 'Child',
            self::SIBLING => 'Sibling',
            self::GRANDPARENT => 'Grandparent',
            self::GRANDCHILD => 'Grandchild',
            self::FRIEND => 'Friend',
            self::UNCLE => 'Uncle',
            self::NEIGHBOR => 'Neighbor',
            self::COLLEAGUE => 'Colleague',
            self::GUARDIAN => 'Guardian',
            self::CAREGIVER => 'Caregiver',
            self::OTHER => 'Other',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::SPOUSE => 'danger',
            self::PARENT => 'info',
            self::CHILD => 'success',
            self::SIBLING => 'primary',
            self::GRANDPARENT => 'warning',
            self::GRANDCHILD => 'success',
            self::UNCLE => 'secondary',
            self::FRIEND => 'secondary',
            self::NEIGHBOR => 'gray',
            self::COLLEAGUE => 'gray',
            self::GUARDIAN => 'info',
            self::CAREGIVER => 'primary',
            self::OTHER => 'gray',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function canMakeMedicalDecisions(): bool
    {
        return in_array($this, [
            self::SPOUSE,
            self::PARENT,
            self::GUARDIAN,
            self::CAREGIVER,
        ]);
    }

    public function isImmediateFamily(): bool
    {
        return in_array($this, [
            self::SPOUSE,
            self::PARENT,
            self::CHILD,
            self::SIBLING,
        ]);
    }
}
