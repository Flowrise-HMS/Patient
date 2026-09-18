<?php

declare(strict_types=1);

use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use Livewire\Livewire;
use Modules\Core\Database\Factories\BranchFactory;
use Modules\Patient\Database\Factories\PatientFactory;
use Modules\Patient\Filament\Clusters\Patient\Resources\Patients\Pages\ViewPatient;
use Modules\Patient\Models\Patient;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, DatabaseTransactions::class);

beforeEach(function () {
    $this->requireModule('Clinical');
    $this->migrateModules(['Core', 'Patient', 'Clinical']);

    $this->branch = BranchFactory::new()->create();
    $this->patient = Patient::withoutEvents(fn () => PatientFactory::new()->create([
        'branch_id' => $this->branch->id,
        'mrn' => 'MRN-HDR-1',
    ]));

    Role::findOrCreate('super_admin', 'web');
    $this->admin = tap(User::factory()->create(['branch_id' => $this->branch->id]))->assignRole('super_admin');
});

afterEach(function () {
    Context::forget('current_branch_id');
});

it('shows a compact primary row with the clinical workspace link and groups the rest', function () {
    // Resolved by name so this Patient-module test never hard-imports Clinical.
    $workspaceClass = 'Modules\\Clinical\\Filament\\Clusters\\Workspace\\Pages\\ClinicalWorkspace';

    $page = Livewire::actingAs($this->admin)
        ->test(ViewPatient::class, ['record' => $this->patient->getRouteKey()])
        ->assertOk()
        ->assertActionExists('open_clinical_workspace')
        ->assertActionHasUrl('open_clinical_workspace', $workspaceClass::getUrl(['patientId' => $this->patient->id]))
        ->assertActionExists('view_timeline')
        ->assertActionExists('view_profile')
        ->assertActionExists('edit')
        ->assertActionVisible('merge');

    $header = $page->instance()->getCachedHeaderActions();

    $topLevel = collect($header)
        ->map(fn ($action): string => $action instanceof Action ? $action->getName() : 'group:'.$action->getLabel())
        ->values()
        ->all();

    expect($topLevel)->toBe(['activities', 'open_clinical_workspace', 'view_timeline', 'view_profile', 'group:More Actions', 'edit']);

    $group = collect($header)->first(fn ($action): bool => $action instanceof ActionGroup);
    $grouped = collect($group->getActions())->map(fn (Action $action): string => $action->getName())->all();

    expect($grouped)->toContain('print_hospital_card', 'upload_documents', 'transfer_internal', 'transfer_out', 'discharge_patient', 'merge')
        ->and($grouped)->toBe(array_values(array_unique($grouped)));
});
