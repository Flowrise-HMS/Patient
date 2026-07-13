<?php

namespace Modules\Patient\Filament\Concerns;

use Modules\Insurance\Services\PatientInsuranceService;
use Modules\Patient\Models\Patient;

trait SyncsPatientInsurance
{
    protected function syncPatientInsurance(Patient $patient, array $data): void
    {
        if (! config('insurance.enabled', true) || ! app()->bound(PatientInsuranceService::class)) {
            return;
        }

        app(PatientInsuranceService::class)->syncFromFormData($patient->id, $data);
    }
}
