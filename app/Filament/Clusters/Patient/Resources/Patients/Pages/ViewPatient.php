<?php

namespace Modules\Patient\Filament\Clusters\Patient\Resources\Patients\Pages;

use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Modules\Clinical\Classes\Actions\PatientActions;
use Modules\Clinical\Filament\Widgets\PatientVitalsChartWidget;
use Modules\Patient\Filament\Clusters\Patient\Resources\Patients\PatientResource;
use Modules\Patient\Models\Patient;
use Override;

class ViewPatient extends ViewRecord
{
    protected static string $resource = PatientResource::class;

    public function getRecord(): Patient
    {
        return parent::getRecord()->load(['identifiers', 'emergencyContacts', 'schools']);
    }

    protected function getHeaderActions(): array
    {
        $actions = app(PatientActions::class)->forPatient($this->getRecord());

        return [
            $actions->printHospitalCardAction(),
            $actions->assignToWardAction(),
            $actions->dischargeAction(),
            $actions->medicationOrder(),
            $actions->profileAction(),
            $actions->timelineAction(),
            EditAction::make(),
        ];
    }

    #[Override]
    protected function getHeaderWidgets(): array
    {
        return [
            PatientVitalsChartWidget::make([
                'patientId' => $this->getRecord()->id,
            ]),
        ];
    }
}
