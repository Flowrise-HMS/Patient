<?php

namespace Modules\Patient\Filament\Clusters\Patient\Resources\Patients\Pages;

use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Modules\Core\Support\OptionalClass;
use Modules\Core\Support\SuperAdmin;
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
        $clinicalActions = OptionalClass::when(
            'Modules\\Clinical\\Classes\\Actions\\PatientActions',
            fn (string $actionsClass) => app($actionsClass)->forPatient($this->getRecord()),
            'Clinical',
        );

        $actions = [
            Action::make('activities')
                ->visible(fn (): bool => SuperAdmin::check())
                ->label('Activities')
                ->icon('heroicon-o-bell-alert')
                ->url(fn () => PatientResource::getUrl('activities', ['record' => $this->getRecord()])),
        ];

        if ($clinicalActions !== null) {
            $actions = [
                ...$actions,
                $clinicalActions->printHospitalCardAction(),
                $clinicalActions->assignToWardAction(),
                $clinicalActions->transferInternalAction(),
                $clinicalActions->transferOutAction(),
                $clinicalActions->dischargeAction(),
                $clinicalActions->medicationOrder(),
                $clinicalActions->profileAction(),
                $clinicalActions->timelineAction(),
            ];
        }

        $actions[] = EditAction::make();

        return $actions;
    }

    #[Override]
    protected function getHeaderWidgets(): array
    {
        $widgetClass = OptionalClass::resolve(
            'Modules\\Clinical\\Filament\\Widgets\\PatientVitalsChartWidget',
            'Clinical',
        );

        if ($widgetClass === null) {
            return [];
        }

        return [
            $widgetClass::make([
                'patientId' => $this->getRecord()->id,
            ]),
        ];
    }
}
