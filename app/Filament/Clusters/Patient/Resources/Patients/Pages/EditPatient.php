<?php

namespace Modules\Patient\Filament\Clusters\Patient\Resources\Patients\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Insurance\Services\PatientInsuranceService;
use Modules\Patient\Filament\Clusters\Patient\Resources\Patients\PatientResource;
use Modules\Patient\Filament\Concerns\SyncsPatientInsurance;

class EditPatient extends EditRecord
{
    use SyncsPatientInsurance;

    protected static string $resource = PatientResource::class;

    protected function authorizeAccess(): void
    {
        parent::authorizeAccess();

        abort_if($this->getRecord()->isMerged(), 403, __('This profile was merged into another patient and can no longer be edited.'));
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $this->syncPatientInsurance($this->record, $this->data);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data = parent::mutateFormDataBeforeFill($data);

        if (config('insurance.enabled', true) && app()->bound(PatientInsuranceService::class)) {
            $policy = $this->record->insurancePolicies()
                ->where('is_active', true)
                ->orderByDesc('is_primary')
                ->first();

            $insuranceData = app(PatientInsuranceService::class)->formDataFromPolicy($policy);
            $data = array_merge($data, $insuranceData);
        }

        return $data;
    }
}
