<?php

namespace Modules\Patient\Livewire;

use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Livewire\Component;
use Modules\Patient\Filament\Clusters\Patient\Resources\Patients\Schemas\PatientForm;

class AddPatientButton extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions, InteractsWithSchemas;

    public function render()
    {
        return view('patient::livewire.add-patient-button');
    }

    public function addPatientAction(): Action
    {
        return Action::make('addPatientAction')
            ->color('info')
            ->slideOver()
            ->tooltip(__('Add Patient'))
            ->modalHeading('Quick Patient Registration')
            ->modalDescription('Fast registration for emergency cases')
            ->schema(PatientForm::simpleForm())
            ->hiddenLabel()
            // ->authorize((new PatientPolicy)->create(auth()->user()))
            ->icon('heroicon-o-user-plus')
            ->action(function (array $data) {
                $patient = null;
                // $service = new PatientService;
                // $patient = $service->createPatient(HelperMethods::getDefaultBranch()?->id, $data, $data['insurance'] ?? []);
                if ($patient) {
                    Notification::make()
                        ->title(__('Patient has been added successfully'))
                        ->success()
                        ->send();

                    // return redirect()->to(\App\Filament\Pages\PatientProfile::getUrl(['patient' => $patient->id]));
                }
                Notification::make()
                    ->title(__('Patient was not added'))
                    ->danger()
                    ->send();

            });
    }
}
