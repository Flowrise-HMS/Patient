<?php

namespace Modules\Patient\Tests\Unit;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Patient\Filament\Clusters\Patient\Resources\Patients\Schemas\PatientForm;
use Tests\TestCase;

class PatientWorkspaceFormTest extends TestCase
{
    use DatabaseTransactions;

    public function test_get_steps_returns_full_patient_wizard(): void
    {
        $steps = PatientForm::getSteps();

        $this->assertNotEmpty($steps);
    }
}
