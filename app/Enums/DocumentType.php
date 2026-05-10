<?php

namespace Modules\Patient\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum DocumentType: string implements HasColor, HasDescription, HasLabel
{
    case NATIONAL_ID = 'national_id';
    case PASSPORT = 'passport';
    case INSURANCE_CARD = 'insurance_card';
    case BIRTH_CERTIFICATE = 'birth_certificate';
    case MEDICAL_RECORD = 'medical_record';
    case LAB_RESULT = 'lab_result';
    case PRESCRIPTION = 'prescription';
    case REFERRAL_LETTER = 'referral_letter';
    case CONSENT_FORM = 'consent_form';
    case OTHER = 'other';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::NATIONAL_ID => 'National ID',
            self::PASSPORT => 'Passport',
            self::INSURANCE_CARD => 'Insurance Card',
            self::BIRTH_CERTIFICATE => 'Birth Certificate',
            self::MEDICAL_RECORD => 'Medical Record',
            self::LAB_RESULT => 'Lab Result',
            self::PRESCRIPTION => 'Prescription',
            self::REFERRAL_LETTER => 'Referral Letter',
            self::CONSENT_FORM => 'Consent Form',
            self::OTHER => 'Other',
        };
    }

    public function getDescription(): string|Htmlable|null
    {
        return match ($this) {
            self::NATIONAL_ID => 'Government-issued national identification document.',
            self::PASSPORT => 'International travel identity document.',
            self::INSURANCE_CARD => 'Scheme membership or coverage proof.',
            self::BIRTH_CERTIFICATE => 'Legal proof of birth and parentage.',
            self::MEDICAL_RECORD => 'Clinical notes, summaries, or external records.',
            self::LAB_RESULT => 'Laboratory report or result slip.',
            self::PRESCRIPTION => 'Medication order or pharmacy document.',
            self::REFERRAL_LETTER => 'Referral to another provider or facility.',
            self::CONSENT_FORM => 'Signed consent for treatment or procedures.',
            self::OTHER => 'Attachment that does not fit a standard category.',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::NATIONAL_ID, self::PASSPORT => 'primary',
            self::INSURANCE_CARD => 'success',
            self::BIRTH_CERTIFICATE => 'info',
            self::MEDICAL_RECORD, self::LAB_RESULT => 'warning',
            self::PRESCRIPTION, self::REFERRAL_LETTER => 'secondary',
            self::CONSENT_FORM => 'danger',
            self::OTHER => 'gray',
        };
    }

    /**
     * @deprecated Use {@see self::getLabel()} for Filament-aware labels.
     */
    public function label(): string
    {
        return (string) $this->getLabel();
    }
}
