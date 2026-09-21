<?php

namespace Modules\Patient\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Core\Support\BlindIndex;
use Modules\Patient\Models\EmergencyContact;
use Modules\Patient\Models\Patient;
use Modules\Patient\Models\PatientIdentifier;
use Tests\TestCase;

class PatientBlindIndexTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient']);
    }

    public function test_indexes_are_written_on_save_and_follow_changes(): void
    {
        $patient = Patient::factory()->create(['phone' => '0244123456', 'email' => 'Ama@Example.com']);

        $this->assertSame(BlindIndex::phone('+233244123456'), $patient->fresh()->phone_index);
        $this->assertSame(BlindIndex::email('ama@example.com'), $patient->fresh()->email_index);

        $patient->update(['phone' => '0200000000', 'email' => null]);

        $this->assertSame(BlindIndex::phone('0200000000'), $patient->fresh()->phone_index);
        $this->assertNull($patient->fresh()->email_index);
    }

    public function test_emergency_contacts_and_identifiers_are_indexed_too(): void
    {
        $patient = Patient::factory()->create();
        $contact = EmergencyContact::factory()->create(['patient_id' => $patient->id, 'phone' => '0555555555', 'alternate_phone' => '0244000000', 'email' => 'kin@example.com']);
        $identifier = PatientIdentifier::factory()->create(['patient_id' => $patient->id, 'value' => 'gha-1234-5678']);

        $this->assertSame(BlindIndex::phone('0555555555'), $contact->fresh()->phone_index);
        $this->assertSame(BlindIndex::phone('0244000000'), $contact->fresh()->alternate_phone_index);
        $this->assertSame(BlindIndex::email('kin@example.com'), $contact->fresh()->email_index);
        $this->assertSame(BlindIndex::identifier('GHA12345678'), $identifier->fresh()->value_index);
    }

    public function test_rebuild_command_backfills_rows_saved_without_events(): void
    {
        $patient = Patient::withoutEvents(fn () => Patient::factory()->create(['phone' => '0244123456', 'email' => 'ama@example.com']));
        $contact = EmergencyContact::withoutEvents(fn () => EmergencyContact::factory()->create(['patient_id' => $patient->id, 'phone' => '0555555555']));
        $identifier = PatientIdentifier::withoutEvents(fn () => PatientIdentifier::factory()->create(['patient_id' => $patient->id, 'value' => 'GHA-1']));

        $this->assertNull($patient->fresh()->phone_index);
        $this->assertNull($contact->fresh()->phone_index);
        $this->assertNull($identifier->fresh()->value_index);

        $this->artisan('patients:rebuild-search-indexes')->assertSuccessful();

        $this->assertSame(BlindIndex::phone('0244123456'), $patient->fresh()->phone_index);
        $this->assertSame(BlindIndex::email('ama@example.com'), $patient->fresh()->email_index);
        $this->assertSame(BlindIndex::phone('0555555555'), $contact->fresh()->phone_index);
        $this->assertSame(BlindIndex::identifier('GHA-1'), $identifier->fresh()->value_index);
    }
}
