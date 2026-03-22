<?php

namespace Modules\Patient\Tests\Unit;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Patient\Classes\Services\EmergencyContactService;
use Modules\Patient\Classes\Services\PatientIdentifierService;
use Modules\Patient\Classes\Services\PatientSchoolService;
use Modules\Patient\Classes\Services\PatientSearchService;
use Modules\Patient\Classes\Services\PatientService;
use Modules\Patient\Enums\Gender;
use Modules\Patient\Models\Patient;
use Modules\Patient\Models\PatientSchool;
use Tests\TestCase;

class PatientServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PatientService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new PatientService(
            new PatientIdentifierService,
            new EmergencyContactService,
            new PatientSearchService,
            new PatientSchoolService
        );
    }

    protected function getService(): PatientService
    {
        return $this->service;
    }

    public function test_all_returns_all_patients(): void
    {
        Patient::factory()->count(3)->create();

        $patients = $this->getService()->all();

        $this->assertCount(3, $patients);
    }

    public function test_get_active_returns_only_active_patients(): void
    {
        Patient::factory()->count(2)->create(['is_active' => true]);
        Patient::factory()->count(3)->create(['is_active' => false]);

        $patients = $this->getService()->getActive();

        $this->assertCount(2, $patients);
    }

    public function test_get_trashed_returns_only_deleted_patients(): void
    {
        Patient::factory()->count(2)->create();
        $deleted = Patient::factory()->count(3)->create();

        foreach ($deleted as $patient) {
            $patient->delete();
        }

        $patients = $this->getService()->getTrashed();

        $this->assertCount(3, $patients);
    }

    public function test_find_returns_patient_by_id(): void
    {
        $patient = Patient::factory()->create();

        $found = $this->getService()->find($patient->id);

        $this->assertNotNull($found);
        $this->assertEquals($patient->id, $found->id);
    }

    public function test_find_returns_null_for_nonexistent_id(): void
    {
        $found = $this->getService()->find('nonexistent-id');

        $this->assertNull($found);
    }

    public function test_find_by_mrn_returns_patient(): void
    {
        $patient = Patient::factory()->create(['mrn' => 'FR-20260320-00001']);

        $found = $this->getService()->findByMrn('FR-20260320-00001');

        $this->assertNotNull($found);
        $this->assertEquals($patient->id, $found->id);
    }

    public function test_find_by_global_uuid_returns_patient(): void
    {
        $patient = Patient::factory()->create();

        $found = $this->getService()->findByGlobalUuid($patient->global_uuid);

        $this->assertNotNull($found);
        $this->assertEquals($patient->id, $found->id);
    }

    public function test_find_or_fail_throws_exception_for_nonexistent(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->getService()->findOrFail('nonexistent-id');
    }

    public function test_create_generates_global_uuid(): void
    {
        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'gender' => Gender::MALE,
            'date_of_birth' => '1990-01-15',
        ];

        $patient = $this->getService()->create($data);

        $this->assertNotNull($patient->global_uuid);
        $this->assertTrue(str()->isUuid($patient->global_uuid));
    }

    public function test_update_changes_patient_data(): void
    {
        $patient = Patient::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $updated = $this->getService()->update($patient, [
            'first_name' => 'Jane',
        ]);

        $this->assertEquals('Jane', $updated->first_name);
    }

    public function test_delete_soft_deletes_patient(): void
    {
        $patient = Patient::factory()->create();

        $result = $this->getService()->delete($patient);

        $this->assertTrue($result);
        $this->assertSoftDeleted('patients', ['id' => $patient->id]);
    }

    public function test_delete_with_force_permanently_deletes(): void
    {
        $patient = Patient::factory()->create();

        $result = $this->getService()->delete($patient, force: true);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('patients', ['id' => $patient->id]);
    }

    public function test_restore_recovers_deleted_patient(): void
    {
        $patient = Patient::factory()->create();
        $patient->delete();

        $restored = $this->getService()->restore($patient->id);

        $this->assertNotNull($restored->fresh());
        $this->assertNull($restored->deleted_at);
    }

    public function test_deactivate_sets_is_active_to_false(): void
    {
        $patient = Patient::factory()->create(['is_active' => true]);

        $result = $this->getService()->deactivate($patient);

        $this->assertFalse($result->is_active);
    }

    public function test_activate_sets_is_active_to_true(): void
    {
        $patient = Patient::factory()->create(['is_active' => false]);

        $result = $this->getService()->activate($patient);

        $this->assertTrue($result->is_active);
    }

    public function test_mark_as_deceased_sets_deceased_flags(): void
    {
        $patient = Patient::factory()->create(['is_active' => true]);

        $result = $this->getService()->markAsDeceased($patient);

        $this->assertTrue($result->is_deceased);
        $this->assertFalse($result->is_active);
        $this->assertNotNull($result->deceased_at);
    }

    public function test_add_school_creates_school_record(): void
    {
        $patient = Patient::factory()->create();

        $school = $this->getService()->addSchool($patient, [
            'school_name' => 'Test School',
            'school_type' => 'primary',
        ]);

        $this->assertInstanceOf(PatientSchool::class, $school);
        $this->assertDatabaseHas('patient_schools', [
            'patient_id' => $patient->id,
            'school_name' => 'Test School',
        ]);
    }

    public function test_delete_school_removes_school(): void
    {
        $patient = Patient::factory()->create();
        $school = PatientSchool::factory()->for($patient, 'patient')->create();

        $result = $this->getService()->deleteSchool($school);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('patient_schools', ['id' => $school->id]);
    }

    public function test_get_demographics_returns_correct_structure(): void
    {
        $patient = Patient::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'middle_name' => 'Michael',
            'gender' => Gender::MALE,
            'date_of_birth' => '1990-01-15',
        ]);

        $demographics = $this->getService()->getDemographics($patient);

        $this->assertArrayHasKey('id', $demographics);
        $this->assertArrayHasKey('mrn', $demographics);
        $this->assertArrayHasKey('full_name', $demographics);
        $this->assertArrayHasKey('gender', $demographics);
        $this->assertArrayHasKey('age', $demographics);
        $this->assertEquals('John', $demographics['first_name']);
        $this->assertEquals('Doe', $demographics['last_name']);
        $this->assertEquals('male', $demographics['gender']);
    }

    public function test_paginate_returns_paginator(): void
    {
        Patient::factory()->count(20)->create();

        $result = $this->getService()->paginate(10);

        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertEquals(10, $result->count());
        $this->assertEquals(20, $result->total());
    }

    public function test_paginate_with_filters(): void
    {
        Patient::factory()->count(5)->create(['gender' => Gender::MALE]);
        Patient::factory()->count(3)->create(['gender' => Gender::FEMALE]);

        $result = $this->getService()->paginate(10, [
            'gender' => Gender::MALE,
        ]);

        $this->assertEquals(5, $result->total());
    }

    public function test_search_returns_matching_patients(): void
    {
        $john = Patient::factory()->create(['first_name' => 'John', 'last_name' => 'Doe']);
        Patient::factory()->create(['first_name' => 'Jane', 'last_name' => 'Smith']);

        $results = $this->getService()->search('John');

        $this->assertTrue($results->contains('id', $john->id));
    }
}
