<?php

namespace Modules\Patient\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum IdentifierType: string implements HasColor, HasLabel
{
    case MRN = 'mrn';
    case NHIS = 'nhis';
    case NATIONAL_ID = 'national_id';
    case PASSPORT = 'passport';
    case DRIVER_LICENSE = 'driver_license';
    case BIRTH_CERTIFICATE = 'birth_certificate';
    case SSNIT = 'ssnit';
    case VOTER_ID = 'voter_id';
    case ALIEN_ID = 'alien_id';
    case OTHER = 'other';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::MRN => 'Medical Record Number (MRN)',
            self::NHIS => 'NHIS Card Number',
            self::NATIONAL_ID => 'National ID (Ghana Card)',
            self::PASSPORT => 'Passport Number',
            self::DRIVER_LICENSE => "Driver's License",
            self::BIRTH_CERTIFICATE => 'Birth Certificate Number',
            self::SSNIT => 'SSNIT Number',
            self::VOTER_ID => "Voter's ID",
            self::ALIEN_ID => 'Alien ID',
            self::OTHER => 'Other',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::MRN => 'primary',
            self::NHIS => 'success',
            self::NATIONAL_ID => 'info',
            self::PASSPORT => 'warning',
            self::DRIVER_LICENSE => 'gray',
            self::BIRTH_CERTIFICATE => 'secondary',
            self::SSNIT => 'danger',
            self::VOTER_ID => 'gray',
            self::ALIEN_ID => 'warning',
            self::OTHER => 'gray',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function isGovernmentIssued(): bool
    {
        return in_array($this, [
            self::NATIONAL_ID,
            self::PASSPORT,
            self::DRIVER_LICENSE,
            self::BIRTH_CERTIFICATE,
            self::SSNIT,
            self::VOTER_ID,
        ]);
    }

    public function isInsuranceRelated(): bool
    {
        return in_array($this, [
            self::NHIS,
            self::SSNIT,
        ]);
    }

    public function requiresExpiryDate(): bool
    {
        return in_array($this, [
            self::PASSPORT,
            self::DRIVER_LICENSE,
            self::NHIS,
        ]);
    }
}
