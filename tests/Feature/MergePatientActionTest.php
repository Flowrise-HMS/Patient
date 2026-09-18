<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use Livewire\Livewire;
use Modules\Core\Database\Factories\BranchFactory;
use Modules\Patient\Database\Factories\PatientFactory;
use Modules\Patient\Filament\Clusters\Patient\Resources\Patients\Pages\EditPatient;
use Modules\Patient\Filament\Clusters\Patient\Resources\Patients\Pages\ViewPatient;
use Modules\Patient\Filament\Clusters\Patient\Resources\Patients\PatientResource;
use Modules\Patient\Models\Patient;
use Modules\Patient\Models\PatientMerge;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, DatabaseTransactions::class);

beforeEach(function () {
    $this->migrateModules(['Core', 'Patient']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->branch = BranchFactory::new()->create();
    $this->source = Patient::withoutEvents(fn () => PatientFactory::new()->create(['branch_id' => $this->branch->id, 'mrn' => 'MRN-A-DUP', 'middle_name' => null]));
    $this->target = Patient::withoutEvents(fn () => PatientFactory::new()->create(['branch_id' => $this->branch->id, 'mrn' => 'MRN-A-KEEP', 'middle_name' => null]));
});

afterEach(function () {
    Context::forget('current_branch_id');
});

function patientUserWith(array $permissions): User
{
    $role = Role::findOrCreate('merge_test_'.md5(implode(',', $permissions)), 'web');
    foreach ($permissions as $permission) {
        $role->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    return tap(User::factory()->create(['branch_id' => test()->branch->id]))->assignRole($role);
}

it('lets a user with merge_patients merge from the view page', function () {
    Livewire::actingAs(patientUserWith(['ViewAny Patient', 'View Patient', 'merge_patients']))
        ->test(ViewPatient::class, ['record' => $this->source->getRouteKey()])
        ->assertActionVisible('merge')
        ->callAction('merge', data: ['target_patient_id' => $this->target->id, 'reason' => 'double registration'])
        ->assertHasNoActionErrors()
        ->assertNotified('Patients merged')
        ->assertRedirect(PatientResource::getUrl('view', ['record' => $this->target]));

    expect(Patient::withTrashed()->find($this->source->id)->merged_into_patient_id)->toBe($this->target->id)
        ->and(PatientMerge::where('source_patient_id', $this->source->id)->value('reason'))->toBe('double registration');
});

it('hides the merge action without the permission', function () {
    Livewire::actingAs(patientUserWith(['ViewAny Patient', 'View Patient']))
        ->test(ViewPatient::class, ['record' => $this->source->getRouteKey()])
        ->assertOk()
        ->assertActionHidden('merge');
});

it('requires a surviving patient', function () {
    Livewire::actingAs(patientUserWith(['ViewAny Patient', 'View Patient', 'merge_patients']))
        ->test(ViewPatient::class, ['record' => $this->source->getRouteKey()])
        ->callAction('merge', data: ['target_patient_id' => null])
        ->assertHasActionErrors(['target_patient_id' => 'required']);
});

it('shows a merged profile read-only with a link to the survivor', function () {
    $user = patientUserWith(['ViewAny Patient', 'View Patient', 'Update Patient', 'merge_patients']);

    Livewire::actingAs($user)
        ->test(ViewPatient::class, ['record' => $this->source->getRouteKey()])
        ->callAction('merge', data: ['target_patient_id' => $this->target->id])
        ->assertHasNoActionErrors();

    Livewire::actingAs($user)
        ->test(ViewPatient::class, ['record' => $this->source->getRouteKey()])
        ->assertOk()
        ->assertActionVisible('open_survivor')
        ->assertActionDoesNotExist('merge')
        ->assertActionDoesNotExist('edit')
        ->assertSee('Merged profile');

    Livewire::actingAs($user)
        ->test(EditPatient::class, ['record' => $this->source->getRouteKey()])
        ->assertForbidden();
});
