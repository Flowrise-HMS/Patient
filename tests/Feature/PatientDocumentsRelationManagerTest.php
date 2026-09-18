<?php

declare(strict_types=1);

use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Core\Classes\Services\MediaDocumentService;
use Modules\Core\Database\Factories\BranchFactory;
use Modules\Patient\Database\Factories\PatientFactory;
use Modules\Patient\Filament\Clusters\Patient\Resources\Patients\Pages\ViewPatient;
use Modules\Patient\Filament\Clusters\Patient\Resources\Patients\PatientResource;
use Modules\Patient\Filament\Clusters\Patient\Resources\Patients\RelationManagers\PatientDocumentsRelationManager;
use Modules\Patient\Models\Patient;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, DatabaseTransactions::class);

beforeEach(function () {
    $this->migrateModules(['Core', 'Patient']);
    Storage::fake('local');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->branch = BranchFactory::new()->create();
    $this->patient = Patient::withoutEvents(fn () => PatientFactory::new()->create([
        'branch_id' => $this->branch->id,
        'mrn' => 'MRN-RM-1',
    ]));
});

function documentsUser(array $permissions): User
{
    $role = Role::findOrCreate('docs_'.md5(implode(',', $permissions)), 'web');
    foreach ($permissions as $permission) {
        $role->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    return tap(User::factory()->create(['branch_id' => test()->branch->id]))->assignRole($role);
}

it('is registered on the patient resource', function () {
    expect(PatientResource::getRelations())->toContain(PatientDocumentsRelationManager::class);
});

it('lists patient documents and uploads new ones', function () {
    $existing = app(MediaDocumentService::class)
        ->attach($this->patient, [$this->fakePdf('old.pdf')], ['document_type' => 'other'])
        ->first();

    Livewire::actingAs(documentsUser(['View Patient', 'Update Patient']))
        ->test(PatientDocumentsRelationManager::class, [
            'ownerRecord' => $this->patient,
            'pageClass' => ViewPatient::class,
        ])
        ->assertOk()
        ->assertCanSeeTableRecords([$existing])
        ->callAction(TestAction::make('upload')->table(), data: [
            'document_type' => 'consent_form',
            'title' => 'Consent',
            'files' => [$this->fakePdf('consent.pdf')],
        ])
        ->assertHasNoActionErrors()
        ->assertNotified('Document uploaded');

    $this->assertDatabaseHas('media', [
        'model_type' => $this->patient->getMorphClass(),
        'model_id' => $this->patient->id,
        'collection_name' => 'documents',
        'name' => 'Consent',
        'disk' => 'local',
    ]);
});

it('hides the upload action from users who cannot update the patient', function () {
    Livewire::actingAs(documentsUser(['View Patient']))
        ->test(PatientDocumentsRelationManager::class, [
            'ownerRecord' => $this->patient,
            'pageClass' => ViewPatient::class,
        ])
        ->assertOk()
        ->assertActionHidden(TestAction::make('upload')->table());
});

it('is not shown to users who cannot view the patient', function () {
    $this->actingAs(User::factory()->create(['branch_id' => $this->branch->id]));

    expect(PatientDocumentsRelationManager::canViewForRecord($this->patient, ViewPatient::class))->toBeFalse();
});

it('aggregates documents attached to the patients own encounters only', function () {
    $this->requireModule('Clinical');

    $encounterClass = 'Modules\\Clinical\\Models\\Encounter';
    $service = app(MediaDocumentService::class);

    $ownEncounter = $encounterClass::factory()->forPatient($this->patient)->create();
    $otherPatient = Patient::withoutEvents(fn () => PatientFactory::new()->create(['branch_id' => $this->branch->id, 'mrn' => 'MRN-RM-2']));
    $otherEncounter = $encounterClass::factory()->forPatient($otherPatient)->create();

    $patientDoc = $service->attach($this->patient, [$this->fakePdf('p.pdf')], [])->first();
    $encounterDoc = $service->attach($ownEncounter, [$this->fakePdf('e.pdf')], [])->first();
    $foreignDoc = $service->attach($otherEncounter, [$this->fakePdf('x.pdf')], [])->first();

    Livewire::actingAs(documentsUser(['View Patient']))
        ->test(PatientDocumentsRelationManager::class, [
            'ownerRecord' => $this->patient,
            'pageClass' => ViewPatient::class,
        ])
        ->assertOk()
        ->assertCanSeeTableRecords([$patientDoc, $encounterDoc])
        ->assertCanNotSeeTableRecords([$foreignDoc]);
});
