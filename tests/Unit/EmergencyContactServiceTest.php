<?php

namespace Modules\Patient\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Patient\Classes\Services\EmergencyContactService;
use Modules\Patient\Enums\RelationshipType;
use Modules\Patient\Models\EmergencyContact;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

class EmergencyContactServiceTest extends TestCase
{
    use RefreshDatabase;

    protected EmergencyContactService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new EmergencyContactService;
    }

    public function test_all_returns_all_contacts_for_patient(): void
    {
        $patient = Patient::factory()->create();
        EmergencyContact::factory()->count(3)->for($patient, 'patient')->create();

        $contacts = $this->service->all($patient);

        $this->assertCount(3, $contacts);
    }

    public function test_all_orders_primary_contact_first(): void
    {
        $patient = Patient::factory()->create();
        $secondary = EmergencyContact::factory()->for($patient, 'patient')->create(['is_primary' => false]);
        $primary = EmergencyContact::factory()->for($patient, 'patient')->create(['is_primary' => true]);

        $contacts = $this->service->all($patient);

        $this->assertEquals($primary->id, $contacts->first()->id);
    }

    public function test_find_returns_contact_by_id(): void
    {
        $contact = EmergencyContact::factory()->create();

        $found = $this->service->find($contact->id);

        $this->assertNotNull($found);
        $this->assertEquals($contact->id, $found->id);
    }

    public function test_find_returns_null_for_nonexistent(): void
    {
        $found = $this->service->find('nonexistent-id');

        $this->assertNull($found);
    }

    public function test_find_by_patient_and_relationship(): void
    {
        $patient = Patient::factory()->create();
        $contact = EmergencyContact::factory()->new()->for($patient, 'patient')->create([
            'relationship' => RelationshipType::SPOUSE->value,
        ]);

        $found = $this->service->findByPatientAndRelationship($patient, RelationshipType::SPOUSE);

        $this->assertNotNull($found);
        $this->assertEquals($contact->id, $found->id);
    }

    public function test_get_primary_contact_returns_primary(): void
    {
        $patient = Patient::factory()->create();
        $primary = EmergencyContact::factory()->new()->for($patient, 'patient')->create(['is_primary' => true]);
        EmergencyContact::factory()->new()->for($patient, 'patient')->create(['is_primary' => false]);

        $found = $this->service->getPrimaryContact($patient);

        $this->assertNotNull($found);
        $this->assertEquals($primary->id, $found->id);
    }

    public function test_create_generates_contact_record(): void
    {
        $patient = Patient::factory()->create();

        $contact = $this->service->create($patient, [
            'name' => 'Jane Doe',
            'relationship' => RelationshipType::SPOUSE->value,
            'phone' => '+233244123456',
        ]);

        $this->assertInstanceOf(EmergencyContact::class, $contact);
        $this->assertEquals('Jane Doe', $contact->name);
        $this->assertEquals($patient->id, $contact->patient_id);
    }

    public function test_create_clears_primary_flag_when_setting_new_primary(): void
    {
        $patient = Patient::factory()->create();
        $oldPrimary = EmergencyContact::factory()->new()->for($patient, 'patient')->create(['is_primary' => true]);

        $newPrimary = $this->service->create($patient, [
            'name' => 'New Contact',
            'relationship' => RelationshipType::PARENT->value,
            'phone' => '+233244123456',
            'is_primary' => true,
        ]);

        $this->assertFalse($oldPrimary->fresh()->is_primary);
        $this->assertTrue($newPrimary->is_primary);
    }

    public function test_update_modifies_contact_data(): void
    {
        $contact = EmergencyContact::factory()->create(['name' => 'Old Name']);

        $updated = $this->service->update($contact, [
            'name' => 'New Name',
        ]);

        $this->assertEquals('New Name', $updated->name);
    }

    public function test_update_primary_contact_updates_existing_or_creates(): void
    {
        $patient = Patient::factory()->create();
        $existing = EmergencyContact::factory()->new()->for($patient, 'patient')->create([
            'is_primary' => true,
            'name' => 'Old Primary',
        ]);

        $updated = $this->service->updatePrimaryContact($patient, [
            'name' => 'Updated Primary',
            'relationship' => RelationshipType::SPOUSE->value,
            'phone' => '+233244123456',
        ]);

        $this->assertEquals('Updated Primary', $updated->name);
        $this->assertTrue($updated->is_primary);
    }

    public function test_delete_removes_contact(): void
    {
        $contact = EmergencyContact::factory()->create();

        $result = $this->service->delete($contact);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('emergency_contacts', ['id' => $contact->id]);
    }

    public function test_delete_promotes_next_contact_when_deleting_primary(): void
    {
        $patient = Patient::factory()->create();
        $primary = EmergencyContact::factory()->new()->for($patient, 'patient')->create(['is_primary' => true]);
        $next = EmergencyContact::factory()->new()->for($patient, 'patient')->create(['is_primary' => false]);

        $this->service->delete($primary);

        $this->assertTrue($next->fresh()->is_primary);
    }

    public function test_set_as_primary_changes_primary_flag(): void
    {
        $contact = EmergencyContact::factory()->create(['is_primary' => false]);

        $result = $this->service->setAsPrimary($contact);

        $this->assertTrue($result->is_primary);
    }

    public function test_has_medical_decision_maker_returns_true_when_exists(): void
    {
        $patient = Patient::factory()->create();
        EmergencyContact::factory()->new()->for($patient, 'patient')->create([
            'can_make_medical_decisions' => true,
        ]);

        $result = $this->service->hasMedicalDecisionMaker($patient);

        $this->assertTrue($result);
    }

    public function test_has_medical_decision_maker_returns_false_when_none(): void
    {
        $patient = Patient::factory()->create();

        $result = $this->service->hasMedicalDecisionMaker($patient);

        $this->assertFalse($result);
    }

    public function test_get_medical_decision_makers_returns_contacts_with_authority(): void
    {
        $patient = Patient::factory()->create();
        $withAuthority = EmergencyContact::factory()->new()->for($patient, 'patient')->create([
            'can_make_medical_decisions' => true,
        ]);
        EmergencyContact::factory()->new()->for($patient, 'patient')->create([
            'can_make_medical_decisions' => false,
        ]);

        $makers = $this->service->getMedicalDecisionMakers($patient);

        $this->assertCount(1, $makers);
        $this->assertEquals($withAuthority->id, $makers->first()->id);
    }

    public function test_get_by_relationship_filters_by_relationship(): void
    {
        $patient = Patient::factory()->create();
        EmergencyContact::factory()->new()->for($patient, 'patient')->create([
            'relationship' => RelationshipType::SPOUSE->value,
        ]);
        EmergencyContact::factory()->new()->for($patient, 'patient')->create([
            'relationship' => RelationshipType::PARENT->value,
        ]);

        $contacts = $this->service->getByRelationship($patient, RelationshipType::SPOUSE);

        $this->assertCount(1, $contacts);
    }

    public function test_get_immediate_family_contacts(): void
    {
        $patient = Patient::factory()->create();
        $spouse = EmergencyContact::factory()->new()->for($patient, 'patient')->create([
            'relationship' => RelationshipType::SPOUSE->value,
        ]);
        $parent = EmergencyContact::factory()->new()->for($patient, 'patient')->create([
            'relationship' => RelationshipType::PARENT->value,
        ]);
        EmergencyContact::factory()->new()->for($patient, 'patient')->create([
            'relationship' => RelationshipType::FRIEND->value,
        ]);

        $contacts = $this->service->getImmediateFamilyContacts($patient);

        $this->assertCount(2, $contacts);
    }

    public function test_can_receive_sms_returns_contacts_that_can(): void
    {
        $patient = Patient::factory()->create();
        EmergencyContact::factory()->new()->for($patient, 'patient')->create([
            'can_receive_sms' => true,
            'phone' => '+233244123456',
        ]);
        EmergencyContact::factory()->new()->for($patient, 'patient')->create([
            'can_receive_sms' => false,
        ]);

        $contacts = $this->service->canReceiveSms($patient);

        $this->assertCount(1, $contacts);
    }

    public function test_bulk_create_creates_multiple_contacts(): void
    {
        $patient = Patient::factory()->create();

        $contacts = $this->service->bulkCreate($patient, [
            ['name' => 'Contact 1', 'relationship' => RelationshipType::SPOUSE->value, 'phone' => '+233244123456'],
            ['name' => 'Contact 2', 'relationship' => RelationshipType::PARENT->value, 'phone' => '+233244123457'],
        ]);

        $this->assertCount(2, $contacts);
    }

    public function test_bulk_create_sets_first_as_primary(): void
    {
        $patient = Patient::factory()->create();

        $contacts = $this->service->bulkCreate($patient, [
            ['name' => 'Contact 1', 'relationship' => RelationshipType::SPOUSE->value, 'phone' => '+233244123456'],
            ['name' => 'Contact 2', 'relationship' => RelationshipType::PARENT->value, 'phone' => '+233244123457'],
        ]);

        $this->assertTrue($contacts->first()->is_primary);
    }

    public function test_reorder_priority_changes_primary_order(): void
    {
        $patient = Patient::factory()->create();
        $contact1 = EmergencyContact::factory()->new()->for($patient, 'patient')->create(['is_primary' => true]);
        $contact2 = EmergencyContact::factory()->new()->for($patient, 'patient')->create(['is_primary' => false]);

        $this->service->reorderPriority($patient, [$contact2->id, $contact1->id]);

        $this->assertTrue($contact2->fresh()->is_primary);
        $this->assertFalse($contact1->fresh()->is_primary);
    }

    public function test_to_array_returns_correct_structure(): void
    {
        $contact = EmergencyContact::factory()->create([
            'name' => 'Test Contact',
            'phone' => '+233244123456',
        ]);

        $array = $this->service->toArray($contact);

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('phone', $array);
        $this->assertEquals('Test Contact', $array['name']);
    }
}
