<?php

namespace Modules\Patient\Database\Seeders;

use Database\Seeders\ShieldSeeder;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PatientShieldPermissionsSeeder extends Seeder
{
    /**
     * Grant Filament Shield widget permissions for Patient dashboard analytics.
     * Run after {@see ShieldSeeder} so permissions exist.
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $guard = 'web';

        $widgetPermissionNames = Permission::query()
            ->where('guard_name', $guard)
            ->where('name', 'like', 'View %Widget')
            ->where(function ($query): void {
                $query->where('name', 'like', '%PatientRegistration%')
                    ->orWhere('name', 'like', '%RecentPatientRegistrations%')
                    ->orWhere('name', 'like', '%PatientRegistrationsChart%')
                    ->orWhere('name', 'like', '%PatientsByRegion%')
                    ->orWhere('name', 'like', '%TopDiagnoses%');
            })
            ->pluck('name')
            ->all();

        if ($widgetPermissionNames === []) {
            return;
        }

        foreach (['super_admin', 'receptionist', 'admissions_staff'] as $roleName) {
            $this->giveNamedPermissionsToRole($roleName, $widgetPermissionNames, $guard);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * @param  list<string>  $names
     */
    protected function giveNamedPermissionsToRole(string $roleName, array $names, string $guard): void
    {
        $role = Role::query()->where('name', $roleName)->where('guard_name', $guard)->first();
        if ($role === null) {
            return;
        }

        $existing = Permission::query()
            ->where('guard_name', $guard)
            ->whereIn('name', $names)
            ->pluck('name')
            ->all();

        if ($existing === []) {
            return;
        }

        $role->givePermissionTo($existing);
    }
}
