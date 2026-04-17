<?php

namespace Modules\Patient\Filament\Clusters\Patient\Resources\Patients\Pages;

use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Modules\Patient\Filament\Clusters\Patient\Resources\Patients\PatientResource;
use Modules\Patient\Models\Patient;

class ViewPatient extends ViewRecord
{
    protected static string $resource = PatientResource::class;

    public function getRecord(): Patient
    {
        return parent::getRecord()->load(['identifiers', 'emergencyContacts', 'schools']);
    }

    protected function getHeaderActions(): array
    {
        return [
            PatientResource::profileAction(),
            PatientResource::timelineAction(),
            EditAction::make(),
        ];
    }
}
