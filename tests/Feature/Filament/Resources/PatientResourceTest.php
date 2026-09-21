<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Core\Models\Branch;
use Modules\Patient\Enums\Gender;
use Modules\Patient\Filament\Clusters\Patient\Resources\Patients\Pages\CreatePatient;
use Modules\Patient\Filament\Clusters\Patient\Resources\Patients\Pages\EditPatient;
use Modules\Patient\Filament\Clusters\Patient\Resources\Patients\Pages\ListPatients;
use Modules\Patient\Filament\Clusters\Patient\Resources\Patients\Pages\ViewPatient;
use Modules\Patient\Filament\Clusters\Patient\Resources\Patients\PatientResource;
use Modules\Patient\Models\Patient;
use Tests\Support\FilamentResourceTestSuite;
use Tests\TestCase;

uses(TestCase::class, DatabaseTransactions::class);

beforeEach(function (): void {
    $this->requireModule('Patient');
    $this->migrateModules(['Core', 'Patient']);
    $this->branch = Branch::factory()->create();
    $this->setCurrentBranch($this->branch);
});

FilamentResourceTestSuite::register([
    'resource' => PatientResource::class,
    'subject' => 'Patient',
    'model' => Patient::class,
    'listPage' => ListPatients::class,
    'createPage' => CreatePatient::class,
    'editPage' => EditPatient::class,
    'viewPage' => ViewPatient::class,
    'searchColumn' => 'mrn',
    'sortColumn' => 'mrn',
    'filter' => [
        'name' => 'gender',
        'value' => [Gender::FEMALE->value],
        'attribute' => 'gender',
    ],
    'hasBulkDelete' => true,
    'hasRecordDelete' => true,
    'softDeletes' => true,
    'userAttributes' => fn (TestCase $test): array => ['branch_id' => $test->branch->id],
    'makeRecord' => fn (TestCase $test, array $attributes = []): Patient => Patient::factory()->female()->create([
        'branch_id' => $test->branch->id,
        ...$attributes,
    ]),
    'makeRecords' => function (TestCase $test, int $count) {
        $records = collect([
            Patient::factory()->female()->create(['branch_id' => $test->branch->id]),
            Patient::factory()->male()->create(['branch_id' => $test->branch->id]),
        ]);

        for ($index = 2; $index < $count; $index++) {
            $records->push(Patient::factory()->female()->create(['branch_id' => $test->branch->id]));
        }

        return $records;
    },
    'createForm' => fn (): array => [
        'first_name' => 'Ama',
        'last_name' => 'Boateng',
        'gender' => Gender::FEMALE->value,
        'date_of_birth' => '1990-05-12',
        'phone' => '+233244000001',
        'nationality' => null,
        'address' => [
            'country' => null,
            'region' => null,
        ],
    ],
    'updateForm' => fn (): array => [
        'first_name' => 'Updated',
        'last_name' => 'Patient',
        'nationality' => null,
        'address' => [
            'country' => null,
            'region' => null,
        ],
    ],
    'schemaState' => fn (mixed $test, Patient $record): array => [
        'first_name' => $record->first_name,
        'last_name' => $record->last_name,
        'gender' => $record->gender,
    ],
    'requiredValidation' => [
        'first name is required' => [['first_name' => null], ['first_name' => 'required']],
        'last name is required' => [['last_name' => null], ['last_name' => 'required']],
        'gender is required' => [['gender' => null], ['gender' => 'required']],
        'phone is required' => [['phone' => null], ['phone' => 'required']],
    ],
    'databaseHasOnCreate' => fn (mixed $test, array $payload): array => [
        'first_name' => $payload['first_name'],
        'last_name' => $payload['last_name'],
        'gender' => $payload['gender'],
    ],
]);
