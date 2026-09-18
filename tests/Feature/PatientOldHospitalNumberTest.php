<?php

namespace Modules\Patient\Tests\Feature;

use App\Models\User;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use Livewire\Livewire;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Encounters\EncounterResource;
use Modules\Core\Database\Factories\BranchFactory;
use Modules\Patient\Classes\Services\PatientSearchService;
use Modules\Patient\Database\Factories\PatientFactory;
use Modules\Patient\Filament\Clusters\Patient\Resources\Patients\Pages\ListPatients;
use Modules\Patient\Filament\Clusters\Patient\Resources\Patients\PatientResource;
use Modules\Patient\Filament\Imports\PatientImporter;
use Modules\Patient\Models\Patient;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PatientOldHospitalNumberTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateModules(['Core', 'Patient']);
    }

    protected function tearDown(): void
    {
        Context::forget('current_branch_id');
        parent::tearDown();
    }

    private function superAdmin(): User
    {
        Role::findOrCreate('super_admin', 'web');

        return tap(User::factory()->create())->assignRole('super_admin');
    }

    public function test_old_hospital_number_is_persisted_via_factory_state(): void
    {
        $patient = Patient::withoutEvents(fn () => PatientFactory::new()
            ->withOldHospitalNumber('OLD-123')
            ->create(['mrn' => 'MRN-OLD-1']));

        $this->assertSame('OLD-123', $patient->fresh()->old_hospital_number);
    }

    public function test_search_service_finds_and_ranks_old_hospital_number_first(): void
    {
        Patient::withoutEvents(function () {
            PatientFactory::new()->create(['mrn' => 'MRN-A', 'first_name' => 'Kwame', 'last_name' => 'OLD-777', 'middle_name' => null]);
            PatientFactory::new()->withOldHospitalNumber('OLD-777')->create(['mrn' => 'MRN-B', 'first_name' => 'Ama', 'last_name' => 'Mensah', 'middle_name' => null]);
        });

        $service = new PatientSearchService;

        $this->assertContains('old_hospital_number', $service->getSearchableFields());
        $this->assertContains('old_hospital_number', PatientResource::getGloballySearchableAttributes());

        $results = $service->search('OLD-777');

        $this->assertCount(2, $results);
        $this->assertSame('MRN-B', $results->first()->mrn);
    }

    public function test_patients_table_is_searchable_by_old_hospital_number(): void
    {
        $branch = BranchFactory::new()->create();

        [$match, $other] = Patient::withoutEvents(fn () => [
            PatientFactory::new()->withOldHospitalNumber('OLD-4242')->create(['branch_id' => $branch->id, 'mrn' => 'MRN-T-1']),
            PatientFactory::new()->create(['branch_id' => $branch->id, 'mrn' => 'MRN-T-2']),
        ]);

        Livewire::actingAs($this->superAdmin())
            ->test(ListPatients::class)
            ->searchTable('OLD-4242')
            ->assertCanSeeTableRecords([$match])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_importer_exposes_column_and_resolves_single_match(): void
    {
        $names = collect(PatientImporter::getColumns())->map(fn ($column) => $column->getName());
        $this->assertContains('old_hospital_number', $names);

        $existing = Patient::withoutEvents(fn () => PatientFactory::new()
            ->withOldHospitalNumber('OLD-IMP-1')
            ->create(['mrn' => 'MRN-IMP-1']));

        $importer = $this->makeImporter(['old_hospital_number' => 'OLD-IMP-1', 'first_name' => 'X', 'last_name' => 'Y']);

        $this->assertTrue($importer->resolveRecord()->is($existing));
    }

    public function test_importer_creates_new_record_when_old_number_is_ambiguous(): void
    {
        Patient::withoutEvents(function () {
            PatientFactory::new()->withOldHospitalNumber('OLD-DUP')->create(['mrn' => 'MRN-D-1']);
            PatientFactory::new()->withOldHospitalNumber('OLD-DUP')->create(['mrn' => 'MRN-D-2']);
        });

        $importer = $this->makeImporter(['old_hospital_number' => 'OLD-DUP']);

        $this->assertFalse($importer->resolveRecord()->exists);
    }

    public function test_encounter_global_search_includes_old_hospital_number(): void
    {
        $this->requireModule('Clinical');

        $this->assertContains('patient.old_hospital_number', EncounterResource::getGloballySearchableAttributes());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function makeImporter(array $data): PatientImporter
    {
        $import = new Import;
        $import->user_id = $this->superAdmin()->id;
        $import->file_name = 'patients.csv';
        $import->file_path = 'patients.csv';
        $import->importer = PatientImporter::class;
        $import->total_rows = 1;
        $import->save();

        $importer = new PatientImporter($import, array_keys($data), []);
        (new \ReflectionProperty($importer, 'data'))->setValue($importer, $data);

        return $importer;
    }
}
