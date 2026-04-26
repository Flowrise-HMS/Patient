<?php

namespace Modules\Patient\Filament\Clusters\Patient\Resources\Patients\Pages;

use Modules\Patient\Filament\Clusters\Patient\Resources\Patients\PatientResource;
use pxlrbt\FilamentActivityLog\Pages\ListActivities;

class ListPatientActivities extends ListActivities
{
    protected static string $resource = PatientResource::class;
}
