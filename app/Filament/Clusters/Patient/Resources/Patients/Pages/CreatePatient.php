<?php

namespace Modules\Patient\Filament\Clusters\Patient\Resources\Patients\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Patient\Filament\Clusters\Patient\Resources\Patients\PatientResource;

class CreatePatient extends CreateRecord
{
    protected static string $resource = PatientResource::class;
}
