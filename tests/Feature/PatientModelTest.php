<?php

namespace Modules\Patient\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Core\Models\Branch;
use Modules\Patient\Models\EmergencyContact;
use Modules\Patient\Models\Patient;
use Modules\Patient\Models\PatientIdentifier;
use Tests\TestCase;

class PatientModelTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient']);
    }

    public function test_patient_factory_creates_patient(): void
    {
        $patient = Patient::factory()->create();
        $this->assertTrue($patient->exists);
        $this->assertNotNull($patient->id);
    }

    public function test_patient_has_identifiers(): void
    {
        $patient = Patient::factory()->create();
        PatientIdentifier::factory()->count(2)->create(['patient_id' => $patient->id]);

        $this->assertCount(2, $patient->identifiers);
    }

    public function test_patient_has_emergency_contacts(): void
    {
        $patient = Patient::factory()->create();
        EmergencyContact::factory()->count(2)->create(['patient_id' => $patient->id]);

        $this->assertCount(2, $patient->emergencyContacts);
    }

    public function test_patient_identifier_factory(): void
    {
        $identifier = PatientIdentifier::factory()->create();
        $this->assertTrue($identifier->exists);
    }

    public function test_emergency_contact_factory(): void
    {
        $contact = EmergencyContact::factory()->create();
        $this->assertTrue($contact->exists);
    }
}
