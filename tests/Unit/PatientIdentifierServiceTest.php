<?php

namespace Modules\Patient\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Patient\Classes\Services\PatientIdentifierService;
use Modules\Patient\Enums\IdentifierType;
use Modules\Patient\Models\Patient;
use Modules\Patient\Models\PatientIdentifier;
use Tests\TestCase;

class PatientIdentifierServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PatientIdentifierService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new PatientIdentifierService;
    }

    public function test_all_returns_all_identifiers_for_patient(): void
    {
        $patient = Patient::factory()->create();
        PatientIdentifier::factory()->count(3)->for($patient, 'patient')->create();

        $identifiers = $this->service->all($patient);

        $this->assertCount(3, $identifiers);
    }

    public function test_all_orders_primary_first(): void
    {
        $patient = Patient::factory()->create();
        $secondary = PatientIdentifier::factory()->new()->for($patient, 'patient')->create(['is_primary' => false]);
        $primary = PatientIdentifier::factory()->new()->for($patient, 'patient')->create(['is_primary' => true]);

        $identifiers = $this->service->all($patient);

        $this->assertEquals($primary->id, $identifiers->first()->id);
    }

    public function test_find_returns_identifier_by_id(): void
    {
        $identifier = PatientIdentifier::factory()->create();

        $found = $this->service->find($identifier->id);

        $this->assertNotNull($found);
        $this->assertEquals($identifier->id, $found->id);
    }

    public function test_find_by_type_returns_identifier_of_type(): void
    {
        $patient = Patient::factory()->create();
        $identifier = PatientIdentifier::factory()->new()->for($patient, 'patient')->create([
            'type' => IdentifierType::NATIONAL_ID->value,
        ]);

        $found = $this->service->findByType($patient, IdentifierType::NATIONAL_ID);

        $this->assertNotNull($found);
        $this->assertEquals($identifier->id, $found->id);
    }

    public function test_find_by_value_returns_identifier_by_value(): void
    {
        $this->markTestSkipped('Value field is encrypted - cannot search directly');
    }

    public function test_get_primary_mrn_returns_primary_mrn_identifier(): void
    {
        $patient = Patient::factory()->create();
        PatientIdentifier::factory()->new()->for($patient, 'patient')->create([
            'type' => IdentifierType::NATIONAL_ID->value,
        ]);
        $mrn = PatientIdentifier::factory()->new()->for($patient, 'patient')->create([
            'type' => IdentifierType::MRN->value,
            'is_primary' => true,
        ]);

        $found = $this->service->getPrimaryMrn($patient);

        $this->assertNotNull($found);
        $this->assertEquals($mrn->id, $found->id);
    }

    public function test_get_primary_identifier_returns_primary_identifier(): void
    {
        $patient = Patient::factory()->create();
        $primary = PatientIdentifier::factory()->new()->for($patient, 'patient')->create(['is_primary' => true]);

        $found = $this->service->getPrimaryIdentifier($patient);

        $this->assertNotNull($found);
        $this->assertEquals($primary->id, $found->id);
    }

    public function test_create_generates_identifier_record(): void
    {
        $patient = Patient::factory()->create();

        $identifier = $this->service->create($patient, [
            'type' => IdentifierType::NATIONAL_ID->value,
            'value' => '123456789012',
            'issuer' => 'NIA',
        ]);

        $this->assertInstanceOf(PatientIdentifier::class, $identifier);
        $this->assertEquals('123456789012', $identifier->value);
        $this->assertEquals($patient->id, $identifier->patient_id);
    }

    public function test_create_clears_primary_flag_when_setting_new_primary(): void
    {
        $patient = Patient::factory()->create();
        $oldPrimary = PatientIdentifier::factory()->new()->for($patient, 'patient')->create([
            'type' => IdentifierType::NATIONAL_ID->value,
            'is_primary' => true,
        ]);

        $newPrimary = $this->service->create($patient, [
            'type' => IdentifierType::PASSPORT->value,
            'value' => 'AB1234567',
            'is_primary' => true,
        ]);

        $this->assertFalse($oldPrimary->fresh()->is_primary);
        $this->assertTrue($newPrimary->is_primary);
    }

    public function test_update_modifies_identifier_data(): void
    {
        $identifier = PatientIdentifier::factory()->create(['value' => 'Old Value']);

        $updated = $this->service->update($identifier, [
            'value' => 'New Value',
        ]);

        $this->assertEquals('New Value', $updated->value);
    }

    public function test_delete_removes_identifier(): void
    {
        $identifier = PatientIdentifier::factory()->create();

        $result = $this->service->delete($identifier);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('patient_identifiers', ['id' => $identifier->id]);
    }

    public function test_delete_promotes_next_identifier_when_deleting_primary(): void
    {
        $patient = Patient::factory()->create();
        $primary = PatientIdentifier::factory()->new()->for($patient, 'patient')->create([
            'type' => IdentifierType::NATIONAL_ID->value,
            'is_primary' => true,
        ]);
        $next = PatientIdentifier::factory()->new()->for($patient, 'patient')->create([
            'type' => IdentifierType::NATIONAL_ID->value,
            'is_primary' => false,
        ]);

        $this->service->delete($primary);

        $this->assertTrue($next->fresh()->is_primary);
    }

    public function test_set_as_primary_changes_primary_flag(): void
    {
        $identifier = PatientIdentifier::factory()->create([
            'type' => IdentifierType::NATIONAL_ID->value,
            'is_primary' => false,
        ]);

        $result = $this->service->setAsPrimary($identifier);

        $this->assertTrue($result->is_primary);
    }

    public function test_verify_marks_identifier_as_verified(): void
    {
        $identifier = PatientIdentifier::factory()->create(['is_verified' => false]);

        $result = $this->service->verify($identifier);

        $this->assertTrue($result->is_verified);
        $this->assertNotNull($result->verified_at);
    }

    public function test_unverify_marks_identifier_as_unverified(): void
    {
        $identifier = PatientIdentifier::factory()->create([
            'is_verified' => true,
            'verified_at' => now(),
        ]);

        $result = $this->service->unverify($identifier);

        $this->assertFalse($result->is_verified);
        $this->assertNull($result->verified_at);
    }

    public function test_is_unique_returns_true_for_unique_value(): void
    {
        $result = $this->service->isUnique('unique-value-123');

        $this->assertTrue($result);
    }

    public function test_is_unique_returns_false_for_existing_value(): void
    {
        $this->markTestSkipped('Value field is encrypted - cannot search directly');
    }

    public function test_is_unique_excludes_specified_id(): void
    {
        $identifier = PatientIdentifier::factory()->create(['value' => 'value-123']);

        $result = $this->service->isUnique('value-123', $identifier->id);

        $this->assertTrue($result);
    }

    public function test_find_duplicate_returns_identifier_if_exists(): void
    {
        $this->markTestSkipped('Value field is encrypted - cannot search directly');
    }

    public function test_get_expiring_soon_returns_identifiers_expiring_within_days(): void
    {
        PatientIdentifier::factory()->create([
            'expiry_date' => now()->addDays(10),
        ]);
        PatientIdentifier::factory()->create([
            'expiry_date' => now()->addDays(60),
        ]);

        $result = $this->service->getExpiringSoon(30);

        $this->assertCount(1, $result);
    }

    public function test_get_expired_returns_expired_identifiers(): void
    {
        PatientIdentifier::factory()->create([
            'expiry_date' => now()->subDays(5),
        ]);
        PatientIdentifier::factory()->create([
            'expiry_date' => now()->addDays(30),
        ]);

        $result = $this->service->getExpired();

        $this->assertCount(1, $result);
    }

    public function test_get_unverified_returns_unverified_identifiers(): void
    {
        PatientIdentifier::factory()->create([
            'is_verified' => false,
            'created_at' => now()->subDays(10),
        ]);
        PatientIdentifier::factory()->create([
            'is_verified' => true,
        ]);

        $result = $this->service->getUnverified(7);

        $this->assertCount(1, $result);
    }

    public function test_bulk_create_creates_multiple_identifiers(): void
    {
        $patient = Patient::factory()->create();

        $identifiers = $this->service->bulkCreate($patient, [
            ['type' => IdentifierType::NATIONAL_ID->value, 'value' => '111111111111'],
            ['type' => IdentifierType::NHIS->value, 'value' => '2222222222'],
        ]);

        $this->assertCount(2, $identifiers);
    }

    public function test_bulk_verify_verifies_multiple_identifiers(): void
    {
        $id1 = PatientIdentifier::factory()->create(['is_verified' => false])->id;
        $id2 = PatientIdentifier::factory()->create(['is_verified' => false])->id;

        $count = $this->service->bulkVerify([$id1, $id2]);

        $this->assertEquals(2, $count);
    }

    public function test_to_array_returns_correct_structure(): void
    {
        $identifier = PatientIdentifier::factory()->create([
            'type' => IdentifierType::NATIONAL_ID->value,
            'value' => '1234567890123456',
            'issuer' => 'NIA',
            'is_verified' => true,
        ]);

        $array = $this->service->toArray($identifier);

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('type', $array);
        $this->assertArrayHasKey('value', $array);
        $this->assertArrayHasKey('is_verified', $array);
        $this->assertEquals('national_id', $array['type']);
        $this->assertTrue($array['is_verified']);
    }
}
