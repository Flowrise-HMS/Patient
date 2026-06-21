<?php

namespace Modules\Patient\Tests\Unit;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Patient\Classes\Services\PatientSchoolService;
use Modules\Patient\Models\Patient;
use Modules\Patient\Models\PatientSchool;
use Tests\TestCase;

class PatientSchoolServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected PatientSchoolService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient']);

        $this->service = new PatientSchoolService;
    }

    public function test_all_returns_all_schools_for_patient(): void
    {
        $patient = Patient::factory()->create();
        PatientSchool::factory()->count(3)->for($patient, 'patient')->create();

        $schools = $this->service->all($patient);

        $this->assertCount(3, $schools);
    }

    public function test_all_orders_by_is_current_first(): void
    {
        $patient = Patient::factory()->create();
        $current = PatientSchool::factory()->for($patient, 'patient')->create(['is_current' => true]);
        $previous = PatientSchool::factory()->for($patient, 'patient')->create(['is_current' => false]);

        $schools = $this->service->all($patient);

        $this->assertEquals($current->id, $schools->first()->id);
    }

    public function test_find_returns_school_by_id(): void
    {
        $school = PatientSchool::factory()->create();

        $found = $this->service->find($school->id);

        $this->assertNotNull($found);
        $this->assertEquals($school->id, $found->id);
    }

    public function test_find_returns_null_for_nonexistent(): void
    {
        $found = $this->service->find('nonexistent-id');

        $this->assertNull($found);
    }

    public function test_find_by_patient_returns_school_belonging_to_patient(): void
    {
        $patient = Patient::factory()->create();
        $school = PatientSchool::factory()->for($patient, 'patient')->create();

        $found = $this->service->findByPatient($patient, $school->id);

        $this->assertNotNull($found);
        $this->assertEquals($school->id, $found->id);
    }

    public function test_get_current_school_returns_current_school(): void
    {
        $patient = Patient::factory()->create();
        $current = PatientSchool::factory()->for($patient, 'patient')->create(['is_current' => true]);
        PatientSchool::factory()->for($patient, 'patient')->create(['is_current' => false]);

        $found = $this->service->getCurrentSchool($patient);

        $this->assertNotNull($found);
        $this->assertEquals($current->id, $found->id);
    }

    public function test_get_current_school_returns_null_when_none(): void
    {
        $patient = Patient::factory()->create();

        $found = $this->service->getCurrentSchool($patient);

        $this->assertNull($found);
    }

    public function test_get_active_schools_returns_only_active(): void
    {
        $patient = Patient::factory()->create();
        PatientSchool::factory()->for($patient, 'patient')->create(['is_active' => true]);
        PatientSchool::factory()->for($patient, 'patient')->create(['is_active' => false]);

        $schools = $this->service->getActiveSchools($patient);

        $this->assertCount(1, $schools);
    }

    public function test_get_by_level_filters_by_level(): void
    {
        $patient = Patient::factory()->create();
        PatientSchool::factory()->for($patient, 'patient')->create(['level' => 'Primary 1']);
        PatientSchool::factory()->for($patient, 'patient')->create(['level' => 'Primary 2']);
        PatientSchool::factory()->for($patient, 'patient')->create(['level' => 'JHS 1']);

        $schools = $this->service->getByLevel($patient, 'Primary 1');

        $this->assertCount(1, $schools);
        $this->assertEquals('Primary 1', $schools->first()->level);
    }

    public function test_get_by_type_filters_by_school_type(): void
    {
        $patient = Patient::factory()->create();
        PatientSchool::factory()->for($patient, 'patient')->create(['school_type' => 'primary']);
        PatientSchool::factory()->for($patient, 'patient')->create(['school_type' => 'university']);

        $schools = $this->service->getByType($patient, 'university');

        $this->assertCount(1, $schools);
        $this->assertEquals('university', $schools->first()->school_type);
    }

    public function test_create_generates_school_record(): void
    {
        $patient = Patient::factory()->create();

        $school = $this->service->create($patient, [
            'school_name' => 'Test University',
            'school_type' => 'university',
            'course' => 'Computer Science',
        ]);

        $this->assertInstanceOf(PatientSchool::class, $school);
        $this->assertEquals('Test University', $school->school_name);
        $this->assertEquals($patient->id, $school->patient_id);
    }

    public function test_create_clears_current_flag_when_setting_new_current(): void
    {
        $patient = Patient::factory()->create();
        $oldSchool = PatientSchool::factory()->for($patient, 'patient')->create(['is_current' => true]);

        $newSchool = $this->service->create($patient, [
            'school_name' => 'New School',
            'school_type' => 'primary',
            'is_current' => true,
        ]);

        $this->assertFalse($oldSchool->fresh()->is_current);
        $this->assertTrue($newSchool->is_current);
    }

    public function test_update_modifies_school_data(): void
    {
        $school = PatientSchool::factory()->create(['school_name' => 'Old Name']);

        $updated = $this->service->update($school, [
            'school_name' => 'New Name',
        ]);

        $this->assertEquals('New Name', $updated->school_name);
    }

    public function test_delete_removes_school(): void
    {
        $school = PatientSchool::factory()->create();

        $result = $this->service->delete($school);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('patient_schools', ['id' => $school->id]);
    }

    public function test_delete_promotes_next_school_when_deleting_current(): void
    {
        $patient = Patient::factory()->create();
        $current = PatientSchool::factory()->for($patient, 'patient')->create(['is_current' => true]);
        $next = PatientSchool::factory()->for($patient, 'patient')->create(['is_current' => false]);

        $this->service->delete($current);

        $this->assertTrue($next->fresh()->is_current);
    }

    public function test_set_as_current_changes_current_flag(): void
    {
        $school = PatientSchool::factory()->create(['is_current' => false]);

        $result = $this->service->setAsCurrent($school);

        $this->assertTrue($result->is_current);
    }

    public function test_set_as_current_clears_other_current_flags(): void
    {
        $patient = Patient::factory()->create();
        $oldCurrent = PatientSchool::factory()->for($patient, 'patient')->create(['is_current' => true]);
        $newCurrent = PatientSchool::factory()->for($patient, 'patient')->create(['is_current' => false]);

        $this->service->setAsCurrent($newCurrent);

        $this->assertFalse($oldCurrent->fresh()->is_current);
        $this->assertTrue($newCurrent->fresh()->is_current);
    }

    public function test_complete_current_school_marks_as_completed(): void
    {
        $patient = Patient::factory()->create();
        $school = PatientSchool::factory()->for($patient, 'patient')->create(['is_current' => true]);

        $result = $this->service->completeCurrentSchool($patient);

        $this->assertFalse($result->is_current);
        $this->assertFalse($result->is_active);
        $this->assertNotNull($result->graduation_date);
    }

    public function test_complete_current_school_returns_null_when_no_current(): void
    {
        $patient = Patient::factory()->create();

        $result = $this->service->completeCurrentSchool($patient);

        $this->assertNull($result);
    }

    public function test_transition_to_new_school_completes_old_and_creates_new(): void
    {
        $patient = Patient::factory()->create();
        $oldSchool = PatientSchool::factory()->for($patient, 'patient')->create([
            'is_current' => true,
            'school_name' => 'Old School',
        ]);

        $newSchool = $this->service->transitionToNewSchool($patient, [
            'school_name' => 'New School',
            'school_type' => 'university',
        ]);

        $this->assertFalse($oldSchool->fresh()->is_current);
        $this->assertFalse($oldSchool->fresh()->is_active);
        $this->assertEquals('New School', $newSchool->school_name);
        $this->assertTrue($newSchool->is_current);
    }

    public function test_is_currently_enrolled_returns_true_when_enrolled(): void
    {
        $patient = Patient::factory()->create();
        PatientSchool::factory()->for($patient, 'patient')->create([
            'is_current' => true,
            'is_active' => true,
        ]);

        $result = $this->service->isCurrentlyEnrolled($patient);

        $this->assertTrue($result);
    }

    public function test_is_currently_enrolled_returns_false_when_not_enrolled(): void
    {
        $patient = Patient::factory()->create();

        $result = $this->service->isCurrentlyEnrolled($patient);

        $this->assertFalse($result);
    }

    public function test_get_education_timeline_orders_by_admission_date(): void
    {
        $patient = Patient::factory()->create();
        $school1 = PatientSchool::factory()->for($patient, 'patient')->create([
            'admission_date' => '2020-01-01',
        ]);
        $school2 = PatientSchool::factory()->for($patient, 'patient')->create([
            'admission_date' => '2022-01-01',
        ]);

        $timeline = $this->service->getEducationTimeline($patient);

        $this->assertEquals($school1->id, $timeline->first()->id);
        $this->assertEquals($school2->id, $timeline->last()->id);
    }

    public function test_bulk_create_creates_multiple_schools(): void
    {
        $patient = Patient::factory()->create();

        $schools = $this->service->bulkCreate($patient, [
            ['school_name' => 'School 1', 'school_type' => 'primary'],
            ['school_name' => 'School 2', 'school_type' => 'jhs'],
        ]);

        $this->assertCount(2, $schools);
    }

    public function test_bulk_create_sets_first_as_current(): void
    {
        $patient = Patient::factory()->create();

        $schools = $this->service->bulkCreate($patient, [
            ['school_name' => 'School 1', 'school_type' => 'primary'],
            ['school_name' => 'School 2', 'school_type' => 'jhs'],
        ]);

        $this->assertEquals(2, $schools->count());

        $firstSchool = $schools->first();
        $lastSchool = $schools->last();

        $this->assertNotNull($firstSchool, 'First school should not be null');
        $this->assertNotNull($lastSchool, 'Last school should not be null');

        $this->assertTrue($firstSchool->is_current);
        $this->assertFalse($lastSchool->is_current);
    }

    public function test_to_array_returns_correct_structure(): void
    {
        $school = PatientSchool::factory()->create([
            'school_name' => 'Test School',
            'school_type' => 'primary',
            'level' => 'Primary 1',
            'school_phone' => '+233244123456',
        ]);

        $array = $this->service->toArray($school);

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('school_name', $array);
        $this->assertArrayHasKey('school_type', $array);
        $this->assertArrayHasKey('school_phone', $array);
        $this->assertEquals('Test School', $array['school_name']);
    }
}
