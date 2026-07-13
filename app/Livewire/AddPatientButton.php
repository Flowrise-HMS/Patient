<?php

namespace Modules\Patient\Livewire;

use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Modules\Clinical\Filament\Clusters\Workspace\Pages\PatientProfile;
use Modules\Core\Classes\Services\BranchService;
use Modules\Insurance\Services\PatientInsuranceService;
use Modules\Patient\Events\PatientRegistered;
use Modules\Patient\Filament\Clusters\Patient\Resources\Patients\Schemas\PatientForm;
use Modules\Patient\Models\Patient;

class AddPatientButton extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions, InteractsWithSchemas;

    public function render(): View
    {
        return view('patient::livewire.add-patient-button');
    }

    public function addPatientAction(): Action
    {
        return CreateAction::make('addPatient')
            ->model(Patient::class)
            ->color('info')
            ->slideOver()
            ->tooltip(__('Add Patient'))
            ->modalHeading('Quick Patient Registration')
            ->modalDescription('Fast registration for emergency cases')
            ->icon('heroicon-o-user-plus')
            ->closeModalByClickingAway(false)
            ->authorize(Gate::allows('create', Patient::class))
            ->schema([
                ...PatientForm::getSteps(),
                Toggle::make('print_card')
                    ->label(__('Print hospital card'))
                    ->visible(fn (): bool => Auth::check() && Auth::user()?->can('print_hospital_card')),
            ])
            ->mutateDataUsing(function (array $data): array {
                $data['branch_id'] = $data['branch_id'] ?? app(BranchService::class)->getDefaultBranchId();
                $data['created_by'] = Auth::id();
                if (function_exists('generate_global_uuid')) {
                    $data['global_uuid'] = generate_global_uuid();
                }

                return $data;
            })
            ->after(function (Patient $record, array $data): void {
                if (config('insurance.enabled', true) && app()->bound(PatientInsuranceService::class)) {
                    app(PatientInsuranceService::class)->syncFromFormData($record->id, $data);
                }

                if (class_exists(PatientRegistered::class)) {
                    event(new PatientRegistered($record));
                }

                Log::info('Patient registered via quick add', [
                    'patient_id' => $record->id,
                    'mrn' => $record->mrn,
                    'branch_id' => $record->branch_id,
                    'registered_by' => Auth::id(),
                ]);

                Notification::make()
                    ->title(__('Patient has been added successfully'))
                    ->body("MRN: {$record->mrn}")
                    ->success()
                    ->send();

                $this->dispatch('patientCreated', patientId: $record->id);

                if (! empty($data['print_card'])) {
                    $this->redirect(route('patients.hospital-card', $record));
                } elseif (PatientProfile::canAccess()) {
                    $this->redirect(PatientProfile::getUrl(['patient' => $record?->id]));
                }
            });
    }
}
