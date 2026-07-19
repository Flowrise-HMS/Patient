<?php

namespace Modules\Patient\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Organization;
use Modules\Patient\Models\Patient;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PatientApiTest extends TestCase
{
    use DatabaseTransactions;

    private Organization $organization;

    private Branch $branchA;

    private Branch $branchB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::create(['name' => 'ViewAny Patient', 'guard_name' => 'web']);
        Permission::create(['name' => 'View Patient', 'guard_name' => 'web']);

        $this->organization = Organization::create([
            'name' => 'Test Org',
            'display_name' => 'Test Org',
            'slug' => 'test-org',
            'is_active' => true,
        ]);

        $this->branchA = Branch::create([
            'organization_id' => $this->organization->id,
            'name' => 'Branch A',
            'display_name' => 'Branch A',
            'code' => 'BR-A',
            'is_active' => true,
        ]);

        $this->branchB = Branch::create([
            'organization_id' => $this->organization->id,
            'name' => 'Branch B',
            'display_name' => 'Branch B',
            'code' => 'BR-B',
            'is_active' => true,
        ]);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->getJson('/api/v1/patients');
        $response->assertStatus(401);
    }

    public function test_authenticated_request_returns_paginated_patients(): void
    {
        $user = User::factory()->create(['branch_id' => $this->branchA->id]);
        $user->givePermissionTo('ViewAny Patient');

        Patient::factory()->count(3)->create(['branch_id' => $this->branchA->id]);

        $response = $this->actingAs($user)->getJson('/api/v1/patients');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                '*' => ['id', 'first_name', 'last_name'],
            ],
            'meta' => ['current_page', 'per_page', 'total', 'last_page'],
        ]);
        $response->assertJson(['success' => true]);
    }

    public function test_show_returns_single_patient(): void
    {
        $user = User::factory()->create(['branch_id' => $this->branchA->id]);
        $user->givePermissionTo('View Patient');

        $patient = Patient::factory()->create(['branch_id' => $this->branchA->id]);

        $response = $this->actingAs($user)->getJson("/api/v1/patients/{$patient->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => ['id', 'first_name', 'last_name'],
        ]);
        $response->assertJsonPath('data.id', $patient->id);
    }

    public function test_response_json_only_contains_allowed_fields(): void
    {
        $user = User::factory()->create(['branch_id' => $this->branchA->id]);
        $user->givePermissionTo('View Patient');

        $patient = Patient::factory()->create(['branch_id' => $this->branchA->id]);

        $response = $this->actingAs($user)->getJson("/api/v1/patients/{$patient->id}");

        $response->assertStatus(200);

        $allowedFields = [
            'id', 'mrn', 'first_name', 'last_name',
            'date_of_birth', 'gender', 'phone', 'email',
            'address', 'is_active', 'created_at', 'updated_at',
        ];

        $dataKeys = array_keys($response->json('data'));
        foreach ($dataKeys as $key) {
            $this->assertContains($key, $allowedFields, "Field '{$key}' is not in the allowed fields list.");
        }
    }

    public function test_branch_scope_prevents_cross_branch_patient_access(): void
    {
        $user = User::factory()->create(['branch_id' => $this->branchA->id]);
        $user->givePermissionTo('View Patient');

        $patientInBranchB = Patient::factory()->create(['branch_id' => $this->branchB->id]);

        $response = $this->actingAs($user)->getJson("/api/v1/patients/{$patientInBranchB->id}");

        $response->assertStatus(404);
    }
}
