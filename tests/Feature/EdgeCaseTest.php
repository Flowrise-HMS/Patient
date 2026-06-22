<?php

namespace Modules\Patient\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Core\Enums\Title;
use Modules\Patient\Enums\BloodType;
use Modules\Patient\Enums\DocumentType;
use Modules\Patient\Enums\EducationLevel;
use Modules\Patient\Enums\Gender;
use Modules\Patient\Enums\IdentifierType;
use Modules\Patient\Enums\MaritalStatus;
use Modules\Patient\Enums\RelationshipType;
use Modules\Patient\Enums\SchoolType;
use Modules\Patient\Models\EmergencyContact;
use Modules\Patient\Models\Patient;
use Modules\Patient\Models\PatientIdentifier;
use Modules\Patient\Models\PatientSchool;
use Tests\TestCase;

class EdgeCaseTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient']);
    }

    // ─── Patient model ──────────────────────────────────────────────────────

    public function test_patient_has_uuid(): void
    {
        $patient = Patient::factory()->create();
        $this->assertNotNull($patient->id);
    }

    public function test_patient_casts_gender_as_enum(): void
    {
        $patient = Patient::factory()->create(['gender' => Gender::MALE]);
        $this->assertTrue($patient->gender instanceof Gender);
        $this->assertSame(Gender::MALE, $patient->gender);
    }

    public function test_patient_full_name_with_title(): void
    {
        $patient = Patient::factory()->create([
            'title' => Title::MR,
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);
        $this->assertStringContainsString('John', $patient->full_name);
        $this->assertStringContainsString('Doe', $patient->full_name);
    }

    public function test_patient_age_null_without_dob(): void
    {
        $patient = Patient::factory()->create(['date_of_birth' => null]);
        $this->assertNull($patient->age);
    }

    // ─── PatientIdentifiers ─────────────────────────────────────────────────

    public function test_identifier_has_uuid(): void
    {
        $identifier = PatientIdentifier::factory()->create();
        $this->assertNotNull($identifier->id);
    }

    public function test_identifier_belongs_to_patient(): void
    {
        $identifier = PatientIdentifier::factory()->create();
        $this->assertNotNull($identifier->patient);
    }

    // ─── EmergencyContact ────────────────────────────────────────────────────

    public function test_emergency_contact_has_uuid(): void
    {
        $contact = EmergencyContact::factory()->create();
        $this->assertNotNull($contact->id);
    }

    public function test_emergency_contact_belongs_to_patient(): void
    {
        $contact = EmergencyContact::factory()->create();
        $this->assertNotNull($contact->patient);
    }

    // ─── PatientSchool ──────────────────────────────────────────────────────

    public function test_school_has_uuid(): void
    {
        $school = PatientSchool::factory()->create();
        $this->assertNotNull($school->id);
    }

    public function test_school_belongs_to_patient(): void
    {
        $school = PatientSchool::factory()->create();
        $this->assertNotNull($school->patient);
    }

    // ─── Patient Enums ──────────────────────────────────────────────────────

    public function test_gender_enum_values(): void
    {
        $values = Gender::values();
        $this->assertCount(2, $values);
        $this->assertContains('male', $values);
        $this->assertContains('female', $values);
    }

    public function test_gender_labels(): void
    {
        $this->assertSame('Male', Gender::MALE->getLabel());
        $this->assertSame('Female', Gender::FEMALE->getLabel());
    }

    public function test_blood_type_enum_values(): void
    {
        $values = BloodType::values();
        $this->assertContains('A+', $values);
        $this->assertContains('B+', $values);
        $this->assertContains('O+', $values);
        $this->assertContains('AB+', $values);
        $this->assertContains('A-', $values);
        $this->assertContains('B-', $values);
        $this->assertContains('O-', $values);
        $this->assertContains('AB-', $values);
        $this->assertCount(9, $values);
    }

    public function test_marital_status_values(): void
    {
        $values = MaritalStatus::values();
        $this->assertContains('single', $values);
        $this->assertContains('married', $values);
        $this->assertContains('divorced', $values);
        $this->assertContains('widowed', $values);
        $this->assertContains('separated', $values);
    }

    public function test_education_level_values(): void
    {
        $values = EducationLevel::values();
        $this->assertContains('none', $values);
        $this->assertContains('primary', $values);
        $this->assertContains('senior_secondary', $values);
        $this->assertContains('tertiary', $values);
        $this->assertContains('vocational', $values);
    }

    public function test_relationship_type_values(): void
    {
        $values = RelationshipType::values();
        $this->assertContains('spouse', $values);
        $this->assertContains('parent', $values);
        $this->assertContains('sibling', $values);
        $this->assertContains('child', $values);
        $this->assertContains('grandparent', $values);
        $this->assertContains('guardian', $values);
        $this->assertContains('other', $values);
    }

    public function test_identifier_type_values(): void
    {
        $values = IdentifierType::values();
        $this->assertContains('nhis', $values);
        $this->assertContains('driver_license', $values);
        $this->assertContains('passport', $values);
        $this->assertContains('voter_id', $values);
        $this->assertContains('national_id', $values);
        $this->assertContains('mrn', $values);
    }

    public function test_document_type_values(): void
    {
        $values = DocumentType::values();
        $this->assertContains('lab_result', $values);
        $this->assertContains('prescription', $values);
        $this->assertContains('referral_letter', $values);
        $this->assertContains('national_id', $values);
        $this->assertContains('consent_form', $values);
        $this->assertContains('other', $values);
    }

    public function test_school_type_values(): void
    {
        $values = SchoolType::values();
        $this->assertContains('nursery', $values);
        $this->assertContains('primary', $values);
        $this->assertContains('junior_high', $values);
        $this->assertContains('tertiary', $values);
        $this->assertContains('vocational', $values);
    }
}
