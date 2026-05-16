<?php

namespace Modules\Patient\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PatientCustomPermissionSeeder extends Seeder
{
    /** @var array<string, string[]> permission name => web-guard roles */
    protected array $matrix = [
        'print_hospital_card' => ['super_admin', 'receptionist', 'admissions_staff', 'nurse', 'doctor'],
        'discharge_patient' => ['super_admin', 'doctor', 'nursing_supervisor'],
        'view_patient_balance' => ['super_admin', 'billing_clerk', 'finance_officer', 'receptionist'],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->matrix as $name => $roles) {
            $perm = Permission::query()->where(['name' => $name, 'guard_name' => 'web'])->first();
            if (! $perm) {
                continue;
            }

            foreach ($roles as $roleName) {
                Role::query()
                    ->where(['name' => $roleName, 'guard_name' => 'web'])
                    ->first()
                    ?->givePermissionTo($perm);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
