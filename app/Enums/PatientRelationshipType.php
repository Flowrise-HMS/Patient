<?php

namespace Modules\Patient\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum PatientRelationshipType: string implements HasColor, HasDescription, HasLabel
{
    case MOTHER = 'mother';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::MOTHER => 'Mother',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::MOTHER => 'primary',
        };
    }

    public function getDescription(): string|Htmlable|null
    {
        return match ($this) {
            self::MOTHER => 'Biological or legal mother of the subject patient.',
        };
    }
}
