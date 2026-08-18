<?php

namespace Modules\Patient\Tests\Unit;

use Modules\Core\Classes\Support\RelationManagersRegistry;
use Modules\Core\Support\ModuleAvailability;
use Modules\Patient\Filament\Clusters\Patient\Resources\Patients\PatientResource;
use Nwidart\Modules\Facades\Module;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PatientResourceRelationManagersSoftBoundaryTest extends TestCase
{
    #[Test]
    public function it_includes_billing_relation_managers_when_billing_is_enabled(): void
    {
        $this->requireModule('Billing');

        $relations = PatientResource::getRelations();

        $this->assertContains(
            'Modules\\Billing\\Filament\\RelationManagers\\PatientInvoicesRelationManager',
            $relations,
        );
        $this->assertContains(
            'Modules\\Billing\\Filament\\RelationManagers\\PatientPaymentsRelationManager',
            $relations,
        );
        $this->assertContains(
            'Modules\\Billing\\Filament\\RelationManagers\\PatientDepositsRelationManager',
            $relations,
        );
    }

    #[Test]
    public function it_skips_billing_relation_managers_when_billing_is_disabled(): void
    {
        $this->requireModule('Billing');

        $module = Module::find('Billing');
        $this->assertNotNull($module);

        try {
            $module->disable();
            $this->assertFalse(ModuleAvailability::billingEnabled());

            $relations = PatientResource::getRelations();

            $this->assertNotContains(
                'Modules\\Billing\\Filament\\RelationManagers\\PatientInvoicesRelationManager',
                $relations,
            );
            $this->assertNotContains(
                'Modules\\Billing\\Filament\\RelationManagers\\PatientPaymentsRelationManager',
                $relations,
            );
            $this->assertNotContains(
                'Modules\\Billing\\Filament\\RelationManagers\\PatientDepositsRelationManager',
                $relations,
            );
        } finally {
            $module->enable();
        }
    }

    #[Test]
    public function it_includes_appointment_relation_manager_when_appointment_is_enabled(): void
    {
        $this->requireModule('Appointment');

        $relations = PatientResource::getRelations();

        $this->assertContains(
            'Modules\\Appointment\\Filament\\RelationManagers\\PatientAppointmentsRelationManager',
            $relations,
        );
    }

    #[Test]
    public function it_skips_appointment_relation_manager_when_appointment_is_disabled(): void
    {
        $this->requireModule('Appointment');

        $module = Module::find('Appointment');
        $this->assertNotNull($module);

        try {
            $module->disable();

            $relations = PatientResource::getRelations();

            $this->assertNotContains(
                'Modules\\Appointment\\Filament\\RelationManagers\\PatientAppointmentsRelationManager',
                $relations,
            );
        } finally {
            $module->enable();
        }
    }

    #[Test]
    public function it_skips_missing_insurance_relation_manager(): void
    {
        $relations = PatientResource::getRelations();

        $this->assertNotContains(
            'Modules\\Insurance\\Filament\\RelationManagers\\PatientPoliciesRelationManager',
            $relations,
        );
    }

    #[Test]
    public function it_merges_relation_managers_from_registry(): void
    {
        $registry = app(RelationManagersRegistry::class);
        $registry->register(PatientResource::class, fn (): array => [
            PatientResourceRelationManagersSoftBoundaryTest::class,
        ], 100);

        $relations = PatientResource::getRelations();

        $this->assertContains(PatientResourceRelationManagersSoftBoundaryTest::class, $relations);
    }
}
