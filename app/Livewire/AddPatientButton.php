<?php

namespace Modules\Patient\Livewire;

use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Modules\Core\Classes\Services\BranchService;
use Modules\Insurance\Services\PatientInsuranceService;
use Modules\Patient\Classes\Services\PatientService;
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
        return Action::make('addPatient')
            ->color('info')
            ->slideOver()
            ->tooltip(__('Add Patient'))
            ->modalHeading('Quick Patient Registration')
            ->modalDescription('Fast registration for emergency cases')
            ->icon('heroicon-o-user-plus')
            ->authorize(Gate::allows('create', Patient::class))
            ->schema([
                ...PatientForm::simpleForm(),
                Toggle::make('print_card')
                    ->label(__('Print hospital card'))
                    ->visible(fn (): bool => Auth::check() && Auth::user()?->can('print_hospital_card')),
            ])
            ->action(function (array $data): void {
                $service = app(PatientService::class);

                $patient = $service->create([
                    'first_name' => isset($data['first_name']) ? $data['first_name'] : null,
                    'last_name' => isset($data['last_name']) ? $data['last_name'] : null,
                    'date_of_birth' => isset($data['date_of_birth']) ? $data['date_of_birth'] : null,
                    'gender' => isset($data['gender']) ? $data['gender'] : null,
                    'phone' => isset($data['phone']) ? $data['phone'] : null,
                    'branch_id' => isset($data['branch_id']) ? $data['branch_id'] : app(BranchService::class)->getDefaultBranchId(),
                ]);

                if ($patient) {
                    if (config('insurance.enabled', true) && app()->bound(PatientInsuranceService::class)) {
                        app(PatientInsuranceService::class)->createPolicyFromData($patient->id, $data);
                    }

                    Notification::make()
                        ->title(__('Patient has been added successfully'))
                        ->body("MRN: {$patient->mrn}")
                        ->success()
                        ->send();

                    $this->dispatch('patientCreated', patientId: $patient->id);

                    if (! empty($data['print_card'])) {
                        $this->redirect(route('patients.hospital-card', $patient));
                    }
                } else {
                    Notification::make()
                        ->title(__('Patient was not added'))
                        ->danger()
                        ->send();
                }
            });
    }
}
