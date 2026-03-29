<?php

namespace Modules\Patient\Enums;

enum DocumentType: string
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

    public function label(): string
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
}
