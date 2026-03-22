<?php

namespace Modules\Patient\Livewire;

use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Modules\Patient\Classes\Services\PatientService;
use Modules\Patient\Enums\Gender;
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
            ->schema(PatientForm::simpleForm())
            ->action(function (array $data): void {
                $service = app(PatientService::class);

                $patient = $service->create([
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'date_of_birth' => $data['date_of_birth'],
                    'gender' => $data['gender'],
                    'phone' => $data['phone'] ?? null,
                    'branch_id' => Auth::user()?->branch_id,
                ]);

                if ($patient) {
                    Notification::make()
                        ->title(__('Patient has been added successfully'))
                        ->body("MRN: {$patient->mrn}")
                        ->success()
                        ->send();

                    $this->dispatch('patientCreated', patientId: $patient->id);
                } else {
                    Notification::make()
                        ->title(__('Patient was not added'))
                        ->danger()
                        ->send();
                }
            });
    }
}
