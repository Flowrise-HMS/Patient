<?php

namespace Modules\Patient\Filament\Clusters\Patient\Resources\Patients\Pages;

use Modules\Core\Filament\Pages\Concerns\RestrictsActivitiesToSuperAdmin;
use Modules\Patient\Filament\Clusters\Patient\Resources\Patients\PatientResource;
use pxlrbt\FilamentActivityLog\Pages\ListActivitiesBySubject;

class ListPatientActivities extends ListActivitiesBySubject
{
    use RestrictsActivitiesToSuperAdmin;

    protected static string $resource = PatientResource::class;
}
