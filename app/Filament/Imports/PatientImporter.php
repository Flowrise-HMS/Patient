<?php

namespace Modules\Patient\Filament\Imports;

use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;
use Modules\Patient\Models\Patient;
use Modules\Patient\Classes\Services\PatientService;

class PatientImporter extends Importer
{
    protected static ?string $model = Patient::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('global_uuid')
                ->rules(['max:36']),
            ImportColumn::make('user')
                ->relationship(),
            ImportColumn::make('branch_id')
                ->rules(['max:36']),
            ImportColumn::make('mrn')
                ->rules(['max:255']),
            ImportColumn::make('title')
                ->rules(['max:255']),
            ImportColumn::make('first_name')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('middle_name')
                ->rules(['max:255']),
            ImportColumn::make('last_name')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('date_of_birth')
                ->rules(['datetime']),
            ImportColumn::make('is_date_of_birth_estimated')
                ->requiredMapping()
                ->boolean()
                ->rules(['required', 'boolean']),
            ImportColumn::make('gender')
                ->rules(['max:255']),
            ImportColumn::make('blood_type')
                ->rules(['max:255']),
            ImportColumn::make('marital_status')
                ->rules(['max:255']),
            ImportColumn::make('education_level')
                ->rules(['max:255']),
            ImportColumn::make('occupation')
                ->rules(['max:255']),
            ImportColumn::make('nationality')
                ->rules(['max:255']),
            ImportColumn::make('phone')
                ->rules(['max:500']),
            ImportColumn::make('email')
                ->rules(['email', 'max:500']),
            ImportColumn::make('preferred_language')
                ->rules(['max:255']),
            ImportColumn::make('photo')
                ->rules(['max:255']),
            ImportColumn::make('is_active')
                ->requiredMapping()
                ->boolean()
                ->rules(['required', 'boolean']),
            ImportColumn::make('is_deceased')
                ->requiredMapping()
                ->boolean()
                ->rules(['required', 'boolean']),
            ImportColumn::make('deceased_at')
                ->rules(['datetime']),
            ImportColumn::make('encrypted_fields'),
            ImportColumn::make('address'),
            ImportColumn::make('contact'),
            ImportColumn::make('meta'),
            ImportColumn::make('created_by')
                ->numeric()
                ->rules(['integer']),
            ImportColumn::make('updated_by')
                ->numeric()
                ->rules(['integer']),
        ];
    }

    public function resolveRecord(): ?Patient
    {
        if (isset($this->data['global_uuid'])) {
            $patient = Patient::where('global_uuid', $this->data['global_uuid'])->first();
            if ($patient) return $patient;
        }

        if (isset($this->data['mrn'])) {
            $patient = Patient::where('mrn', $this->data['mrn'])->first();
            if ($patient) return $patient;
        }

        return new Patient();
    }

    public function saveRecord(): void
    {
        $patientService = app(PatientService::class);
        $data = $this->record->getAttributes();
        
        // Include any custom data keys that might not map directly to attributes
        if (isset($this->data['identifiers'])) {
            $data['identifiers'] = $this->data['identifiers'];
        }
        if (isset($this->data['emergency_contact'])) {
            $data['emergency_contact'] = $this->data['emergency_contact'];
        }

        if ($this->record->exists) {
            $this->record = $patientService->update($this->record, $data);
        } else {
            $this->record = $patientService->create($data);
        }
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your patient import has completed and ' . Number::format($import->successful_rows) . ' ' . str('row')->plural($import->successful_rows) . ' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . Number::format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to import.';
        }

        return $body;
    }
}
