<?php

namespace Modules\Patient\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use Modules\Core\Database\Factories\BranchFactory;
use Modules\Core\Settings\FeatureSettings;
use Modules\Core\Tests\Support\AssertsOfflinePrintHtml;
use Modules\Patient\Database\Factories\PatientFactory;
use Modules\Patient\Http\Controllers\HospitalCardsBulkController;
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

    public function test_hospital_card_returns_404_when_the_feature_is_disabled(): void
    {
        FeatureSettings::fake(['patient_hospital_card_enabled' => false]);
        Permission::firstOrCreate(['name' => 'print_hospital_card', 'guard_name' => 'web']);

        $branch = BranchFactory::new()->create();
        Context::add('current_branch_id', $branch->id);
        $patient = Patient::withoutEvents(fn () => PatientFactory::new()->create(['branch_id' => $branch->id]));
        $user = User::factory()->create();
        $user->givePermissionTo('print_hospital_card');

        $this->actingAs($user)->get(route('patients.hospital-card', $patient))->assertNotFound();
    }

    public function test_bulk_hospital_cards_print_every_selected_patient_on_one_sheet(): void
    {
        Permission::firstOrCreate(['name' => 'print_hospital_card', 'guard_name' => 'web']);

        $branch = BranchFactory::new()->create();
        Context::add('current_branch_id', $branch->id);

        $patients = collect(['MRN-BULK-001', 'MRN-BULK-002', 'MRN-BULK-003'])->map(fn (string $mrn) => Patient::withoutEvents(
            fn () => PatientFactory::new()->create(['branch_id' => $branch->id, 'mrn' => $mrn])
        ));

        $user = User::factory()->create();
        $user->givePermissionTo('print_hospital_card');

        $response = $this->actingAs($user)->get(HospitalCardsBulkController::urlFor($patients->pluck('id')->all()));

        $response->assertOk();
        $html = (string) $response->getContent();
        $this->assertPrintHtmlIsOffline($html);
        $this->assertStringContainsString('print-sheet', $html);
        $this->assertSame(3, substr_count($html, 'class="id-card"'));
        $this->assertTrue(
            strpos($html, 'MRN-BULK-001') < strpos($html, 'MRN-BULK-002')
            && strpos($html, 'MRN-BULK-002') < strpos($html, 'MRN-BULK-003'),
            'Cards print in the order they were selected.',
        );
    }

    public function test_bulk_hospital_cards_are_guarded_like_the_single_card(): void
    {
        Permission::firstOrCreate(['name' => 'print_hospital_card', 'guard_name' => 'web']);

        $branch = BranchFactory::new()->create();
        Context::add('current_branch_id', $branch->id);
        $patient = Patient::withoutEvents(fn () => PatientFactory::new()->create(['branch_id' => $branch->id]));
        $url = HospitalCardsBulkController::urlFor([$patient->getKey()]);

        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();

        $user = User::factory()->create();
        $user->givePermissionTo('print_hospital_card');

        $this->actingAs($user)->get(route('patients.hospital-cards.bulk'))->assertNotFound();
        $tooMany = array_map(fn (int $i): string => 'id-'.$i, range(0, HospitalCardsBulkController::MAX_CARDS));
        $this->actingAs($user)->get(HospitalCardsBulkController::urlFor($tooMany))->assertStatus(422);

        FeatureSettings::fake(['patient_hospital_card_enabled' => false]);
        $this->actingAs($user)->get($url)->assertNotFound();
    }
}
