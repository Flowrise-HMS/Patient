<?php

namespace Modules\Patient\Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Clinical\Database\Factories\DiagnosisCodeFactory;
use Modules\Clinical\Database\Factories\EncounterDiagnosisFactory;
use Modules\Clinical\Models\DiagnosisCode;
use Modules\Clinical\Models\EncounterDiagnosis;
use Modules\Core\Database\Factories\BranchFactory;
use Modules\Patient\Classes\Services\PatientAnalyticsService;
use Modules\Patient\Database\Factories\PatientFactory;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

class PatientAnalyticsServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Clinical', 'Appointment']);
    }

    public function test_it_builds_registration_summary_counts(): void
    {
        Carbon::setTestNow('2026-06-16 12:00:00');

        $branch = BranchFactory::new()->create();

        Patient::withoutEvents(fn () => PatientFactory::new()->create([
            'branch_id' => $branch->id,
            'created_at' => '2026-06-16 08:00:00',
            'is_active' => true,
            'is_deceased' => false,
        ]));

        Patient::withoutEvents(fn () => PatientFactory::new()->create([
            'branch_id' => $branch->id,
            'created_at' => '2026-06-15 08:00:00',
            'is_active' => true,
            'is_deceased' => false,
        ]));

        Patient::withoutEvents(fn () => PatientFactory::new()->inactive()->create([
            'branch_id' => $branch->id,
            'created_at' => '2026-06-01 08:00:00',
        ]));

        $summary = app(PatientAnalyticsService::class)->getRegistrationSummary((string) $branch->id);

        $this->assertSame(1, $summary['today']);
        $this->assertSame(2, $summary['this_week']);
        $this->assertSame(3, $summary['this_month']);
        $this->assertSame(2, $summary['total_active']);
    }

    public function test_it_groups_monthly_registrations(): void
    {
        Carbon::setTestNow('2026-06-15 12:00:00');

        $branch = BranchFactory::new()->create();

        Patient::withoutEvents(fn () => PatientFactory::new()->create([
            'branch_id' => $branch->id,
            'created_at' => '2026-05-10 08:00:00',
        ]));

        Patient::withoutEvents(fn () => PatientFactory::new()->create([
            'branch_id' => $branch->id,
            'created_at' => '2026-06-10 08:00:00',
        ]));

        Patient::withoutEvents(fn () => PatientFactory::new()->create([
            'branch_id' => $branch->id,
            'created_at' => '2026-06-12 08:00:00',
        ]));

        $series = app(PatientAnalyticsService::class)->getMonthlyRegistrations(3, (string) $branch->id);

        $this->assertSame(['Apr 2026', 'May 2026', 'Jun 2026'], $series['labels']);
        $this->assertSame([0, 1, 2], $series['counts']);
    }

    public function test_it_groups_patients_by_region(): void
    {
        $branch = BranchFactory::new()->create();

        Patient::withoutEvents(fn () => PatientFactory::new()->withAddress([
            'region' => 'Ashanti',
            'city' => 'Kumasi',
            'country' => 'GH',
        ])->create(['branch_id' => $branch->id]));

        Patient::withoutEvents(fn () => PatientFactory::new()->withAddress([
            'region' => 'Ashanti',
            'city' => 'Obuasi',
            'country' => 'GH',
        ])->create(['branch_id' => $branch->id]));

        Patient::withoutEvents(fn () => PatientFactory::new()->withAddress([
            'region' => 'Greater Accra',
            'city' => 'Accra',
            'country' => 'GH',
        ])->create(['branch_id' => $branch->id]));

        $series = app(PatientAnalyticsService::class)->getPatientsByRegion(10, (string) $branch->id);

        $this->assertSame(['Ashanti', 'Greater Accra'], $series['labels']);
        $this->assertSame([2, 1], $series['counts']);
    }

    public function test_it_scopes_metrics_to_branch(): void
    {
        Carbon::setTestNow('2026-06-15 12:00:00');

        $branchA = BranchFactory::new()->create();
        $branchB = BranchFactory::new()->create();

        Patient::withoutEvents(fn () => PatientFactory::new()->create([
            'branch_id' => $branchA->id,
            'created_at' => '2026-06-15 08:00:00',
        ]));

        Patient::withoutEvents(fn () => PatientFactory::new()->create([
            'branch_id' => $branchB->id,
            'created_at' => '2026-06-15 09:00:00',
        ]));

        $summary = app(PatientAnalyticsService::class)->getRegistrationSummary((string) $branchA->id);

        $this->assertSame(1, $summary['today']);
    }

    public function test_it_ranks_top_diagnoses(): void
    {
        Carbon::setTestNow('2026-06-15 12:00:00');

        $branch = BranchFactory::new()->create();
        $patient = Patient::withoutEvents(fn () => PatientFactory::new()->create(['branch_id' => $branch->id]));

        $malaria = DiagnosisCode::withoutEvents(fn () => DiagnosisCodeFactory::new()->create([
            'description' => 'Malaria',
        ]));

        $hypertension = DiagnosisCode::withoutEvents(fn () => DiagnosisCodeFactory::new()->create([
            'description' => 'Hypertension',
        ]));

        EncounterDiagnosis::withoutEvents(fn () => EncounterDiagnosisFactory::new()->create([
            'patient_id' => $patient->id,
            'diagnosis_code_id' => $malaria->id,
            'description' => 'Malaria',
            'created_at' => '2026-06-10 08:00:00',
        ]));

        EncounterDiagnosis::withoutEvents(fn () => EncounterDiagnosisFactory::new()->create([
            'patient_id' => $patient->id,
            'diagnosis_code_id' => $malaria->id,
            'description' => 'Malaria',
            'created_at' => '2026-06-11 08:00:00',
        ]));

        EncounterDiagnosis::withoutEvents(fn () => EncounterDiagnosisFactory::new()->create([
            'patient_id' => $patient->id,
            'diagnosis_code_id' => $hypertension->id,
            'description' => 'Hypertension',
            'created_at' => '2026-06-12 08:00:00',
        ]));

        $series = app(PatientAnalyticsService::class)->getTopDiagnoses(5, 90, (string) $branch->id);

        $this->assertSame(['Malaria', 'Hypertension'], $series['labels']);
        $this->assertSame([2, 1], $series['counts']);
    }

    public function test_it_returns_recent_registrations_for_last_seven_days(): void
    {
        Carbon::setTestNow('2026-06-15 12:00:00');

        $branch = BranchFactory::new()->create();

        $recent = Patient::withoutEvents(fn () => PatientFactory::new()->withAddress([
            'region' => 'Ashanti',
            'country' => 'GH',
        ])->create([
            'branch_id' => $branch->id,
            'first_name' => 'Ama',
            'last_name' => 'Mensah',
            'created_at' => '2026-06-14 08:00:00',
        ]));

        Patient::withoutEvents(fn () => PatientFactory::new()->create([
            'branch_id' => $branch->id,
            'created_at' => '2026-06-01 08:00:00',
        ]));

        $rows = app(PatientAnalyticsService::class)->getRecentRegistrations(10, (string) $branch->id);

        $this->assertCount(1, $rows);
        $this->assertSame((string) $recent->id, $rows[0]['id']);
        $this->assertSame('Ashanti', $rows[0]['region']);
        $this->assertStringContainsString('Ama', $rows[0]['name']);
    }
}
