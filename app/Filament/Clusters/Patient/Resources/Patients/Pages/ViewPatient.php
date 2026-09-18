<?php

namespace Modules\Patient\Filament\Clusters\Patient\Resources\Patients\Pages;

use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Modules\Core\Support\OptionalClass;
use Modules\Core\Support\SuperAdmin;
use Modules\Patient\Filament\Actions\MergePatientAction;
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

    public function getSubheading(): string|Htmlable|null
    {
        $record = $this->getRecord();

        if (! $record->isMerged()) {
            return parent::getSubheading();
        }

        $survivor = $record->mergedInto;

        return __('Merged into :mrn on :date', [
            'mrn' => $survivor?->mrn ?? '-',
            'date' => $record->merged_at?->format('d M Y H:i') ?? '-',
        ]);
    }

    protected function getHeaderActions(): array
    {
        $record = $this->getRecord();

        $activities = Action::make('activities')
            ->visible(fn (): bool => SuperAdmin::check())
            ->label('Activities')
            ->icon('heroicon-o-bell-alert')
            ->url(fn () => PatientResource::getUrl('activities', ['record' => $record]));

        if ($record->isMerged()) {
            return [
                $activities,
                Action::make('open_survivor')
                    ->label(__('Open surviving record'))
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('primary')
                    ->url(fn (): string => PatientResource::getUrl('view', ['record' => $record->merged_into_patient_id])),
            ];
        }

        $clinicalActions = OptionalClass::when(
            'Modules\\Clinical\\Classes\\Actions\\PatientActions',
            fn (string $actionsClass) => app($actionsClass)->forPatient($this->getRecord()),
            'Clinical',
        );

        // Primary row mirrors the clinical workspace/profile pages; everything else
        // (ADT, orders, notes, merge...) lives inside the "More Actions" group.
        if ($clinicalActions === null) {
            return [$activities, MergePatientAction::make(), EditAction::make()];
        }

        return [
            $activities,
            $clinicalActions->clinicalWorkspaceAction(),
            $clinicalActions->timelineAction(),
            $clinicalActions->profileAction(),
            $clinicalActions->patientActionGroups([MergePatientAction::make()]),
            EditAction::make(),
        ];
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
