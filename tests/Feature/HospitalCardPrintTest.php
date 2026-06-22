<?php

namespace Modules\Patient\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use Modules\Core\Database\Factories\BranchFactory;
use Modules\Core\Tests\Support\AssertsOfflinePrintHtml;
use Modules\Patient\Database\Factories\PatientFactory;
use Modules\Patient\Models\Patient;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class HospitalCardPrintTest extends TestCase
{
    use AssertsOfflinePrintHtml;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient']);
    }

    public function test_hospital_card_print_view_uses_only_local_assets(): void
    {
        Permission::firstOrCreate(['name' => 'print_hospital_card', 'guard_name' => 'web']);

        $branch = BranchFactory::new()->create();
        Context::add('current_branch_id', $branch->id);

        $patient = Patient::withoutEvents(
            fn () => PatientFactory::new()->create([
                'branch_id' => $branch->id,
                'mrn' => 'MRN-TEST-001',
            ])
        );

        $user = User::factory()->create();
        $user->givePermissionTo('print_hospital_card');

        $response = $this->actingAs($user)->get(route('patients.hospital-card', $patient));

        $response->assertOk();
        $html = (string) $response->getContent();
        $this->assertPrintHtmlIsOffline($html);
        $this->assertStringContainsString('css/print/id-card.css', $html);
        $this->assertFileExists(public_path('fonts/LibreBarcode128-Regular.ttf'));
        $css = (string) file_get_contents(public_path('css/print/id-card.css'));
        $this->assertStringContainsString('LibreBarcode128-Regular.ttf', $css);
        $this->assertStringContainsString('MRN-TEST-001', $html);
    }

    public function test_hospital_card_returns_403_without_permission(): void
    {
        Permission::firstOrCreate(['name' => 'print_hospital_card', 'guard_name' => 'web']);

        $branch = BranchFactory::new()->create();
        Context::add('current_branch_id', $branch->id);

        $patient = Patient::withoutEvents(
            fn () => PatientFactory::new()->create(['branch_id' => $branch->id])
        );

        $response = $this->actingAs(User::factory()->create())
            ->get(route('patients.hospital-card', $patient));

        $response->assertForbidden();
    }
}
