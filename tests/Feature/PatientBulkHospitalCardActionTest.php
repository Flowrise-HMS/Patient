<?php

declare(strict_types=1);

use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Modules\Core\Models\Branch;
use Modules\Core\Settings\FeatureSettings;
use Modules\Patient\Filament\Clusters\Patient\Resources\Patients\Pages\ListPatients;
use Modules\Patient\Models\Patient;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, DatabaseTransactions::class);

beforeEach(function (): void {
    $this->requireModule('Patient');
    $this->migrateModules(['Core', 'Patient']);
    $this->branch = Branch::factory()->create();
    $this->setCurrentBranch($this->branch);

    $this->admin = User::factory()->create(['branch_id' => $this->branch->id]);
    $this->admin->assignRole(Role::findOrCreate('super_admin', 'web'));
});

it('offers a print sheet link for the selected patients', function (): void {
    $patients = Patient::withoutEvents(fn () => Patient::factory()->count(2)->create(['branch_id' => $this->branch->id]));

    Livewire::actingAs($this->admin)
        ->test(ListPatients::class)
        ->selectTableRecords($patients->modelKeys())
        ->mountAction(TestAction::make('print_hospital_cards')->table()->bulk())
        ->assertMountedActionModalSee('Open print sheet')
        ->assertMountedActionModalSeeHtml(['patients/hospital-cards?ids', ...$patients->modelKeys()]);
});

it('hides the bulk print action when hospital cards are disabled', function (): void {
    FeatureSettings::fake(['patient_hospital_card_enabled' => false]);

    Livewire::actingAs($this->admin)
        ->test(ListPatients::class)
        ->assertActionHidden(TestAction::make('print_hospital_cards')->table()->bulk());
});
