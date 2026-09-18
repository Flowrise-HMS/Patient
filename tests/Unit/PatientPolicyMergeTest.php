<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Patient\Models\Patient;
use Modules\Patient\Policies\PatientPolicy;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, DatabaseTransactions::class);

beforeEach(function () {
    $this->migrateModules(['Core', 'Patient']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('allows merging only with the merge_patients permission', function () {
    $policy = new PatientPolicy;
    $patient = new Patient;

    $plain = User::factory()->create();
    expect($policy->merge($plain, $patient))->toBeFalse()
        ->and($plain->can('merge', $patient))->toBeFalse();

    $role = Role::findOrCreate('records_admin', 'web');
    $role->givePermissionTo(Permission::findOrCreate('merge_patients', 'web'));
    $granted = tap(User::factory()->create())->assignRole($role);

    expect($policy->merge($granted, $patient))->toBeTrue();

    Role::findOrCreate('super_admin', 'web');
    $superAdmin = tap(User::factory()->create())->assignRole('super_admin');

    expect($superAdmin->can('merge', $patient))->toBeTrue();
});
