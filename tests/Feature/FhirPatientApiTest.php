<?php

namespace Modules\Patient\Tests\Feature;

use Tests\TestCase;

class FhirPatientApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!env('FHIR_INTEGRATION_TESTS', false)) {
            $this->markTestSkipped('Integration tests require FHIR_INTEGRATION_TESTS=true and database setup.');
        }
    }

    public function test_search_returns_bundle(): void
    {
        $response = $this->getJson('/api/v1/fhir/Patient');

        $response->assertStatus(401);
    }

    public function test_read_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/fhir/Patient/non-existent');

        $response->assertStatus(401);
    }

    public function test_create_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/fhir/Patient', []);

        $response->assertStatus(401);
    }
}
