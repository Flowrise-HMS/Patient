<?php

namespace Modules\Patient\Tests\Unit;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Patient\Classes\Services\PatientSearchService;
use Modules\Patient\Enums\Gender;
use Modules\Patient\Models\Patient;
use Modules\Patient\Models\PatientIdentifier;
use Tests\TestCase;

class PatientSearchServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected PatientSearchService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient']);

        $this->service = new PatientSearchService;
    }

    public function test_search_returns_matching_patients(): void
    {
        $john = Patient::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'phone' => '0244123456',
            'middle_name' => null,
        ]);
        $jane = Patient::factory()->create([
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane.smith@example.com',
            'phone' => '0555555555',
            'middle_name' => null,
        ]);

        $results = $this->service->search('John');

        $this->assertCount(1, $results);
        $this->assertEquals($john->id, $results->first()->id);
    }

    public function test_search_returns_multiple_matches(): void
    {
        $john1 = Patient::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john1@example.com',
            'phone' => '0244123456',
            'middle_name' => null,
        ]);
        $john2 = Patient::factory()->create([
            'first_name' => 'Johnny',
            'last_name' => 'Smith',
            'email' => 'john2@example.com',
            'phone' => '0244123457',
            'middle_name' => null,
        ]);

        $results = $this->service->search('John');

        $this->assertCount(2, $results);
    }

    public function test_search_respects_limit(): void
    {
        Patient::factory()->count(10)->create(['first_name' => 'John']);

        $results = $this->service->search('John', limit: 5);

        $this->assertCount(5, $results);
    }

    public function test_search_exact_mrn_returns_patient(): void
    {
        $patient = Patient::factory()->create(['mrn' => 'FR-20260320-00001']);

        $result = $this->service->searchExactMrn('FR-20260320-00001');

        $this->assertNotNull($result);
        $this->assertEquals($patient->id, $result->id);
    }

    public function test_search_by_phone_normalizes_and_finds(): void
    {
        $patient = Patient::factory()->create(['phone' => '+233244123456']);
        Patient::factory()->create(['phone' => '+233244123457']);

        $results = $this->service->searchByPhone('0244123456');

        $this->assertCount(1, $results);
        $this->assertEquals($patient->id, $results->first()->id);
    }

    public function test_search_by_phone_handles_country_code(): void
    {
        $patient = Patient::factory()->create(['phone' => '0244123456']);

        $results = $this->service->searchByPhone('+233 244 123 456');

        $this->assertCount(1, $results);
        $this->assertEquals($patient->id, $results->first()->id);
    }

    public function test_search_finds_a_patient_by_exact_phone_in_any_spelling(): void
    {
        $patient = Patient::factory()->create(['first_name' => 'Ama', 'last_name' => 'Mensah', 'phone' => '0244123456']);
        Patient::factory()->create(['first_name' => 'Kofi', 'last_name' => 'Mensah', 'phone' => '0244999999']);

        foreach (['0244123456', '+233244123456', '233 244 123 456', '024-412-3456'] as $term) {
            $results = $this->service->search($term);

            $this->assertCount(1, $results, "Term {$term}");
            $this->assertEquals($patient->id, $results->first()->id, "Term {$term}");
        }
    }

    public function test_search_finds_a_patient_by_exact_email_case_insensitively(): void
    {
        $patient = Patient::factory()->create(['email' => 'Ama.Mensah@Example.com']);
        Patient::factory()->create(['email' => 'kofi@example.com']);

        $results = $this->service->search('ama.mensah@example.com');

        $this->assertCount(1, $results);
        $this->assertEquals($patient->id, $results->first()->id);
        $this->assertCount(1, $this->service->searchByEmail('AMA.MENSAH@EXAMPLE.COM'));
    }

    public function test_search_finds_a_patient_by_emergency_contact_phone_or_email(): void
    {
        $patient = Patient::factory()->create(['phone' => '0200000001', 'email' => 'patient@example.com']);
        $patient->emergencyContacts()->create([
            'name' => 'Next of Kin',
            'relationship' => 'spouse',
            'phone' => '0555555555',
            'email' => 'kin@example.com',
            'is_primary' => true,
        ]);
        Patient::factory()->create(['phone' => '0200000002']);

        $byPhone = $this->service->search('+233555555555');
        $byEmail = $this->service->search('KIN@example.com');

        $this->assertCount(1, $byPhone);
        $this->assertEquals($patient->id, $byPhone->first()->id);
        $this->assertCount(1, $byEmail);
        $this->assertEquals($patient->id, $byEmail->first()->id);
    }

    public function test_search_finds_a_patient_by_identifier_value(): void
    {
        $patient = Patient::factory()->create();
        PatientIdentifier::factory()->create(['patient_id' => $patient->id, 'value' => 'GHA-123456789-0']);
        Patient::factory()->create();

        $results = $this->service->search('gha 123456789 0');

        $this->assertCount(1, $results);
        $this->assertEquals($patient->id, $results->first()->id);
    }

    public function test_partial_phone_numbers_do_not_match(): void
    {
        Patient::factory()->create(['first_name' => 'Ama', 'last_name' => 'Mensah', 'phone' => '0244123456']);

        $this->assertCount(0, $this->service->search('123456'));
        $this->assertCount(0, $this->service->searchByPhone('3456'));
    }

    public function test_get_recent_patients_returns_patients_ordered_by_created(): void
    {
        $old = Patient::factory()->create(['created_at' => now()->subDays(10)]);
        $recent = Patient::factory()->create(['created_at' => now()]);

        $results = $this->service->getRecentPatients(10);

        $this->assertEquals($recent->id, $results->first()->id);
    }

    public function test_get_patients_without_identifiers(): void
    {
        $withoutId = Patient::factory()->create();
        $withId = Patient::factory()->create();
        PatientIdentifier::factory()->for($withId, 'patient')->create();

        $results = $this->service->getPatientsWithoutIdentifiers(50);

        $this->assertTrue($results->contains('id', $withoutId->id));
        $this->assertFalse($results->contains('id', $withId->id));
        $this->assertTrue($results->every(
            fn (Patient $patient): bool => $patient->identifiers()->doesntExist()
        ));
    }

    public function test_get_duplicate_candidates_finds_potential_duplicates(): void
    {
        Patient::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'date_of_birth' => '1990-01-15',
        ]);
        Patient::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'date_of_birth' => '1990-01-15',
        ]);

        $results = $this->service->getDuplicateCandidates();

        $this->assertGreaterThanOrEqual(1, $results->count());
    }

    public function test_suggest_similar_patients(): void
    {
        $this->markTestSkipped('Needs investigation - suggestSimilarPatients test');

        Patient::factory()->create(['first_name' => 'John', 'last_name' => 'Doe']);
        Patient::factory()->create(['first_name' => 'Jonathan', 'last_name' => 'Smith']);

        $results = $this->service->suggestSimilarPatients('John', limit: 10);

        $this->assertCount(2, $results);
    }

    public function test_apply_filters_filters_by_gender(): void
    {
        $male = Patient::factory()->create(['gender' => Gender::MALE]);
        $female = Patient::factory()->create(['gender' => Gender::FEMALE]);

        $results = $this->service->applyFilters(
            Patient::query()->whereIn('id', [$male->id, $female->id]),
            ['gender' => Gender::MALE]
        )->get();

        $this->assertCount(1, $results);
        $this->assertEquals($male->id, $results->first()->id);
        $this->assertEquals(Gender::MALE, $results->first()->gender);
    }

    public function test_apply_filters_filters_by_active_status(): void
    {
        $active = Patient::factory()->create(['is_active' => true]);
        $inactive = Patient::factory()->create(['is_active' => false]);

        $results = $this->service->applyFilters(
            Patient::query()->whereIn('id', [$active->id, $inactive->id]),
            ['is_active' => true]
        )->get();

        $this->assertCount(1, $results);
        $this->assertEquals($active->id, $results->first()->id);
        $this->assertTrue($results->first()->is_active);
    }

    public function test_set_searchable_fields_changes_searchable_columns(): void
    {
        $this->service->setSearchableFields(['first_name']);

        $fields = $this->service->getSearchableFields();

        $this->assertContains('first_name', $fields);
    }

    public function test_add_searchable_field_adds_to_list(): void
    {
        $initialFields = $this->service->getSearchableFields();

        $this->service->addSearchableField('occupation');

        $updatedFields = $this->service->getSearchableFields();

        $this->assertGreaterThan(count($initialFields), count($updatedFields));
    }

    public function test_normalize_phone_removes_non_digits(): void
    {
        $normalized = $this->service->normalizePhone('+233-24-411-2345');

        $this->assertEquals('233244112345', $normalized);
    }

    public function test_normalize_term_trims_and_lowercases(): void
    {
        $normalized = $this->service->normalizeTerm('  JOHN  ');

        $this->assertEquals('john', $normalized);
    }

    public function test_filament_relation_searchable_attributes_prefix_the_relation(): void
    {
        $attributes = $this->service->getFilamentRelationSearchableAttributes('patient');

        $this->assertContains('patient.mrn', $attributes);
        $this->assertContains('patient.first_name', $attributes);
        $this->assertContains('patient.identifiers.type', $attributes);
        $this->assertNotContains('patient.identifiers.value', $attributes);
        $this->assertNotContains('patient.phone', $attributes);
        $this->assertNotContains('mrn', $attributes);
    }

    public function test_filament_searchable_attributes_are_unprefixed(): void
    {
        $attributes = $this->service->getFilamentSearchableAttributes();

        $this->assertContains('mrn', $attributes);
        $this->assertContains('first_name', $attributes);
        $this->assertContains('identifiers.type', $attributes);
        $this->assertNotContains('identifiers.value', $attributes);
        $this->assertNotContains('patient.mrn', $attributes);
    }

    public function test_filament_relation_searchable_attributes_support_nested_relations(): void
    {
        $attributes = $this->service->getFilamentRelationSearchableAttributes('serviceRequest.patient');

        $this->assertContains('serviceRequest.patient.mrn', $attributes);
        $this->assertContains('serviceRequest.patient.identifiers.type', $attributes);
    }
}
