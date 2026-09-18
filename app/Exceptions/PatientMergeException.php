<?php

namespace Modules\Patient\Exceptions;

use RuntimeException;

class PatientMergeException extends RuntimeException
{
    public static function sameRecord(): self
    {
        return new self(__('A patient profile cannot be merged into itself.'));
    }

    public static function sourceAlreadyMerged(): self
    {
        return new self(__('This profile has already been merged into another patient.'));
    }

    public static function targetAlreadyMerged(): self
    {
        return new self(__('The selected surviving patient was itself merged into another profile. Choose the surviving record instead.'));
    }

    public static function targetTrashed(): self
    {
        return new self(__('The selected surviving patient has been deleted.'));
    }

    public static function crossBranchNotAllowed(): self
    {
        return new self(__('Only a super administrator can merge patients that belong to different branches.'));
    }

    public static function wouldCreateCycle(): self
    {
        return new self(__('The selected surviving patient was previously merged into this profile.'));
    }
}
