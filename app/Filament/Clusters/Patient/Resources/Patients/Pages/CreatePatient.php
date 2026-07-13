<?php

namespace Modules\Patient\Filament\Clusters\Patient\Resources\Patients\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Patient\Filament\Clusters\Patient\Resources\Patients\PatientResource;
use Modules\Patient\Filament\Concerns\SyncsPatientInsurance;

class CreatePatient extends CreateRecord
{
    use SyncsPatientInsurance;

    protected static string $resource = PatientResource::class;

    protected function afterCreate(): void
    {
        $this->syncPatientInsurance($this->record, $this->data);
    }
}
