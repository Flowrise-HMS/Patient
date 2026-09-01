<?php

namespace Modules\Patient\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Organization;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

/**
 * FHIR import for Patient.
 *
 * These assertions exist because the write path used to be a facade:
 * FhirController::create() called fromFhir(), discarded the result, and answered
 * 201 with an empty Location. The only prior coverage asserted a 401 on an
 * unauthenticated POST, so nothing contradicted it.
 *
 * Every test here therefore checks the database, not the status code.
 */
class FhirPatientImportTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'FHIR']);

        $organization = Organization::factory()->create([
            'name' => 'Test Org',
            'display_name' => 'Test Org',
            'is_active' => true,
        ]);

        $this->branch = Branch::factory()->create([
            'organization_id' => $organization->id,
            'name' => 'Branch A',
            'display_name' => 'Branch A',
            'is_active' => true,
        ]);

        $this->user = User::factory()->create(['branch_id' => $this->branch->id]);

        Context::add('current_branch_id', $this->branch->id);
    }

    protected function tearDown(): void
    {
        Context::forget('current_branch_id');

        parent::tearDown();
    }

    private function fhirPatient(array $overrides = []): array
    {
        return array_merge([
            'resourceType' => 'Patient',
            'name' => [[
                'use' => 'official',
                'family' => 'Asante',
                'given' => ['Akosua'],
            ]],
            'gender' => 'female',
            'birthDate' => '1990-01-15',
        ], $overrides);
    }

    private function postFhir(array $resource)
    {
        return $this->actingAs($this->user)
            ->withHeaders([
                'Content-Type' => 'application/fhir+json',
                'Accept' => 'application/fhir+json',
            ])
            ->postJson('/api/v1/fhir/Patient', $resource);
    }

    public function test_creating_a_patient_persists_it(): void
    {
        $response = $this->postFhir($this->fhirPatient());

        $response->assertStatus(201);

        $this->assertDatabaseHas('patients', [
            'first_name' => 'Akosua',
            'last_name' => 'Asante',
        ]);
    }

    public function test_the_created_id_is_returned_and_resolvable(): void
    {
        $response = $this->postFhir($this->fhirPatient());

        $id = $response->json('id');

        $this->assertNotEmpty($id, 'Response carried no id for the created resource.');
        $this->assertNotNull(Patient::find($id), 'Returned id does not resolve to a record.');

        $response->assertHeader('Location', "/fhir/Patient/{$id}");
    }

    public function test_birth_date_is_persisted(): void
    {
        $response = $this->postFhir($this->fhirPatient(['birthDate' => '1985-06-30']));

        $patient = Patient::findOrFail($response->json('id'));

        $this->assertSame(
            '1985-06-30',
            $patient->date_of_birth?->format('Y-m-d'),
            'birthDate was dropped — the mapper emitted a key that is not fillable.',
        );
    }

    public function test_update_persists_changes(): void
    {
        $created = $this->postFhir($this->fhirPatient());
        $id = $created->json('id');

        $this->actingAs($this->user)
            ->withHeaders([
                'Content-Type' => 'application/fhir+json',
                'Accept' => 'application/fhir+json',
            ])
            ->putJson("/api/v1/fhir/Patient/{$id}", $this->fhirPatient([
                'id' => $id,
                'name' => [[
                    'use' => 'official',
                    'family' => 'Mensah',
                    'given' => ['Akosua'],
                ]],
            ]))
            ->assertStatus(200);

        $this->assertDatabaseHas('patients', [
            'id' => $id,
            'last_name' => 'Mensah',
        ]);
    }

    public function test_a_read_only_resource_rejects_create_with_405(): void
    {
        $this->actingAs($this->user)
            ->withHeaders([
                'Content-Type' => 'application/fhir+json',
                'Accept' => 'application/fhir+json',
            ])
            ->postJson('/api/v1/fhir/CarePlan', ['resourceType' => 'CarePlan', 'status' => 'active'])
            ->assertStatus(405);
    }
}
