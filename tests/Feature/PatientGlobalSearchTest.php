<?php

namespace Modules\Patient\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Modules\Core\Models\Branch;
use Modules\Patient\Filament\Clusters\Patient\Resources\Patients\Pages\ListPatients;
use Modules\Patient\Filament\Clusters\Patient\Resources\Patients\PatientResource;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

/**
 * Global search and the Patients table find patients by their encrypted
 * phone/email through the blind indexes.
 */
class PatientGlobalSearchTest extends TestCase
{
    use DatabaseTransactions;

    protected Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient']);
        Gate::before(fn (): bool => true);

        $this->branch = Branch::factory()->create();
        $this->setCurrentBranch($this->branch);
        $this->actingAs(User::factory()->create(['branch_id' => $this->branch->id]));
    }

    public function test_global_search_finds_patients_by_phone_and_email(): void
    {
        $patient = Patient::factory()->create(['branch_id' => $this->branch->id, 'first_name' => 'Ama', 'last_name' => 'Mensah', 'phone' => '0244123456', 'email' => 'ama@example.com']);
        Patient::factory()->create(['branch_id' => $this->branch->id, 'first_name' => 'Kofi', 'last_name' => 'Mensah', 'phone' => '0244999999', 'email' => 'kofi@example.com']);

        foreach (['+233244123456', 'AMA@example.com', $patient->mrn] as $term) {
            $results = PatientResource::getGlobalSearchResults($term);

            $this->assertCount(1, $results, "Term {$term}");
            $this->assertStringContainsString(PatientResource::getUrl('view', ['record' => $patient]), $results->first()->url, "Term {$term}");
        }
    }

    public function test_patients_table_search_finds_a_patient_by_phone(): void
    {
        $patient = Patient::factory()->create(['branch_id' => $this->branch->id, 'first_name' => 'Ama', 'last_name' => 'Mensah', 'phone' => '0244123456']);
        $other = Patient::factory()->create(['branch_id' => $this->branch->id, 'first_name' => 'Kofi', 'last_name' => 'Mensah', 'phone' => '0244999999']);

        Livewire::test(ListPatients::class)
            ->searchTable('0244123456')
            ->assertCanSeeTableRecords([$patient])
            ->assertCanNotSeeTableRecords([$other])
            ->searchTable('Mensah')
            ->assertCanSeeTableRecords([$patient, $other]);
    }
}
