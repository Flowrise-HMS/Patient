<?php

namespace Modules\Patient\Filament\Clusters\Patient\Resources\Patients\Pages;

use Modules\Patient\Filament\Clusters\Patient\Resources\Patients\PatientResource;
use pxlrbt\FilamentActivityLog\Pages\ListActivitiesBySubject;

class ListPatientActivities extends ListActivitiesBySubject
{
    protected static string $resource = PatientResource::class;
}
