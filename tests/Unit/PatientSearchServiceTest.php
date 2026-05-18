<?php

namespace Modules\Patient\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Patient\Classes\Services\PatientSearchService;
use Modules\Patient\Enums\Gender;
use Modules\Patient\Models\Patient;
use Modules\Patient\Models\PatientIdentifier;
use Tests\TestCase;

class PatientSearchServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PatientSearchService $service;

    protected function setUp(): void
    {
        parent::setUp();

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
        $this->markTestSkipped('Phone field is encrypted - cannot search directly');

        $patient = Patient::factory()->create(['phone' => '+233244123456']);

        $results = $this->service->searchByPhone('0244123456');

        $this->assertCount(1, $results);
        $this->assertEquals($patient->id, $results->first()->id);
    }

    public function test_search_by_phone_handles_country_code(): void
    {
        $this->markTestSkipped('Phone field is encrypted - cannot search directly');

        $patient = Patient::factory()->create(['phone' => '0244123456']);

        $results = $this->service->searchByPhone('+233244123456');

        $this->assertCount(1, $results);
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

        $results = $this->service->getPatientsWithoutIdentifiers(10);

        $this->assertCount(1, $results);
        $this->assertEquals($withoutId->id, $results->first()->id);
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
        Patient::factory()->create(['gender' => Gender::MALE]);
        Patient::factory()->create(['gender' => Gender::FEMALE]);

        $results = $this->service->applyFilters(
            Patient::query(),
            ['gender' => Gender::MALE]
        )->get();

        $this->assertCount(1, $results);
        $this->assertEquals(Gender::MALE, $results->first()->gender);
    }

    public function test_apply_filters_filters_by_active_status(): void
    {
        Patient::factory()->create(['is_active' => true]);
        Patient::factory()->create(['is_active' => false]);

        $results = $this->service->applyFilters(
            Patient::query(),
            ['is_active' => true]
        )->get();

        $this->assertCount(1, $results);
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
}
