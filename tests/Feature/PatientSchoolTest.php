<?php

namespace Modules\Patient\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Patient\Models\Patient;
use Modules\Patient\Models\PatientSchool;
use Tests\TestCase;

class PatientSchoolTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('module:migrate', ['module' => 'Core', '--force' => true]);
        $this->artisan('module:migrate', ['module' => 'Patient', '--force' => true]);
    }

    public function test_patient_has_schools_relationship(): void
    {
        $patient = Patient::factory()->create();
        PatientSchool::factory()->count(2)->create(['patient_id' => $patient->id]);

        $this->assertCount(2, $patient->schools);
    }

    public function test_patient_has_current_school_scope(): void
    {
        $patient = Patient::factory()->create();
        PatientSchool::factory()->create(['patient_id' => $patient->id, 'is_current' => true]);
        PatientSchool::factory()->create(['patient_id' => $patient->id, 'is_current' => false]);

        $this->assertCount(1, $patient->currentSchool);
        $this->assertTrue($patient->currentSchool->first()->is_current);
    }

    public function test_student_status_derived_from_schools(): void
    {
        $withSchool = Patient::factory()->create();
        PatientSchool::factory()->create(['patient_id' => $withSchool->id, 'is_current' => true]);

        $withoutSchool = Patient::factory()->create();

        $this->assertTrue($withSchool->schools()->exists());
        $this->assertFalse($withoutSchool->schools()->exists());
    }

    public function test_patient_school_factory_creates_record(): void
    {
        $school = PatientSchool::factory()->create();

        $this->assertTrue($school->exists);
        $this->assertNotNull($school->patient_id);
        $this->assertNotNull($school->school_name);
        $this->assertNotNull($school->school_type);
    }

    public function test_patient_school_display_name(): void
    {
        $school = PatientSchool::factory()->make([
            'school_name' => 'Accra Academy',
            'level' => 'SHS 2',
            'class_name' => 'Gold Track',
        ]);

        $this->assertStringContainsString('Accra Academy', $school->display_name);
        $this->assertStringContainsString('SHS 2', $school->display_name);
        $this->assertStringContainsString('Gold Track', $school->display_name);
    }

    public function test_patient_school_display_name_with_course(): void
    {
        $school = PatientSchool::factory()->make([
            'school_name' => 'University of Ghana',
            'level' => 'Year 3',
            'course' => 'Computer Science',
        ]);

        $this->assertStringContainsString('Computer Science', $school->display_name);
    }

    public function test_patient_school_display_name_minimal(): void
    {
        $school = PatientSchool::factory()->make([
            'school_name' => 'Some School',
            'level' => null,
            'class_name' => null,
        ]);

        $this->assertEquals('Some School', $school->display_name);
    }

    public function test_patient_school_belongs_to_patient(): void
    {
        $patient = Patient::factory()->create();
        $school = PatientSchool::factory()->create(['patient_id' => $patient->id]);

        $this->assertTrue($school->patient->is($patient));
    }

    public function test_patient_can_have_multiple_schools_only_one_current(): void
    {
        $patient = Patient::factory()->create();

        PatientSchool::factory()->count(3)->create(['patient_id' => $patient->id, 'is_current' => false]);
        PatientSchool::factory()->create(['patient_id' => $patient->id, 'is_current' => true]);

        $this->assertCount(4, $patient->schools);
        $this->assertCount(1, $patient->currentSchool);
    }

    public function test_patient_school_factory_states_produce_valid_records(): void
    {
        $school = PatientSchool::factory()->primary()->create();
        $this->assertEquals('primary', $school->school_type);

        $school = PatientSchool::factory()->jhs()->create();
        $this->assertEquals('junior_high', $school->school_type);

        $school = PatientSchool::factory()->shs()->create();
        $this->assertEquals('senior_high', $school->school_type);

        $school = PatientSchool::factory()->university()->create();
        $this->assertEquals('university', $school->school_type);
        $this->assertNotNull($school->course);

        $school = PatientSchool::factory()->tertiary()->create();
        $this->assertEquals('tertiary', $school->school_type);
    }

    public function test_patient_school_completed_state(): void
    {
        $school = PatientSchool::factory()->completed()->create();

        $this->assertFalse($school->is_current);
        $this->assertFalse($school->is_active);
        $this->assertNotNull($school->graduation_date);
    }

    public function test_patient_school_current_state(): void
    {
        $school = PatientSchool::factory()->current()->create();

        $this->assertTrue($school->is_current);
        $this->assertTrue($school->is_active);
        $this->assertNull($school->graduation_date);
    }

    public function test_patient_school_scope_current(): void
    {
        PatientSchool::factory()->create(['is_current' => true]);
        PatientSchool::factory()->count(2)->create(['is_current' => false]);

        $current = PatientSchool::current()->get();

        $this->assertCount(1, $current);
    }

    public function test_patient_school_scope_active(): void
    {
        PatientSchool::factory()->create(['is_active' => true]);
        PatientSchool::factory()->count(2)->create(['is_active' => false]);

        $active = PatientSchool::active()->get();

        $this->assertCount(1, $active);
    }

    public function test_patient_school_for_patient_state(): void
    {
        $patient = Patient::factory()->create();
        $school = PatientSchool::factory()->forPatient($patient)->create();

        $this->assertTrue($school->patient->is($patient));
    }

    public function test_patient_school_graduated_on_state(): void
    {
        $date = now()->subYear();
        $school = PatientSchool::factory()->graduatedOn($date)->create();

        $this->assertFalse($school->is_current);
        $this->assertNotNull($school->graduation_date);
    }

    public function test_patient_school_admitted_on_state(): void
    {
        $date = now()->subYears(2);
        $school = PatientSchool::factory()->admittedOn($date)->create();

        $this->assertEquals($date->format('Y-m-d'), $school->admission_date->format('Y-m-d'));
    }

    public function test_patient_school_cascades_on_patient_force_delete(): void
    {
        $patient = Patient::factory()->create();
        PatientSchool::factory()->count(3)->create(['patient_id' => $patient->id]);

        $this->assertCount(3, PatientSchool::where('patient_id', $patient->id)->get());

        $patient->forceDelete();

        $this->assertCount(0, PatientSchool::where('patient_id', $patient->id)->get());
    }

    public function test_patient_without_school_has_no_current_school(): void
    {
        $patient = Patient::factory()->create();

        $this->assertCount(0, $patient->currentSchool);
        $this->assertFalse($patient->schools()->exists());
    }
}
