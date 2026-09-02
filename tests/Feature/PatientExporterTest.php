<?php

namespace Modules\Patient\Tests\Feature;

use App\Models\User;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Core\Database\Factories\BranchFactory;
use Modules\Patient\Database\Factories\PatientFactory;
use Modules\Patient\Filament\Clusters\Patient\Resources\Patients\Pages\ListPatients;
use Modules\Patient\Filament\Exports\PatientExporter;
use Modules\Patient\Models\Patient;
use OpenSpout\Reader\XLSX\Reader;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PatientExporterTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateModules(['Core', 'Patient']);

        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        Context::forget('current_branch_id');
        parent::tearDown();
    }

    private function superAdmin(): User
    {
        Role::findOrCreate('super_admin', 'web');

        return tap(User::factory()->create())->assignRole('super_admin');
    }

    public function test_superadmin_can_export_patients_to_xlsx(): void
    {
        $branch = BranchFactory::new()->create();
        Patient::withoutEvents(fn () => PatientFactory::new()->create([
            'branch_id' => $branch->id,
            'mrn' => 'MRN-XL-001',
            'phone' => '0244000111',
        ]));

        Livewire::actingAs($this->superAdmin())
            ->test(ListPatients::class)
            ->callAction('export')
            ->assertHasNoActionErrors();

        $export = Export::query()->latest('id')->first();

        $this->assertNotNull($export);
        $this->assertNotNull($export->completed_at);
        $this->assertSame(1, $export->successful_rows);

        $path = "filament_exports/{$export->id}/{$export->file_name}.xlsx";
        $disk = Storage::disk($export->file_disk);
        $this->assertTrue($disk->exists($path));

        $cells = [];
        $reader = new Reader;
        $reader->open($disk->path($path));
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $cells[] = array_map(fn ($cell) => (string) $cell->getValue(), $row->getCells());
            }
        }
        $reader->close();

        $this->assertContains('Mrn', $cells[0]);
        $this->assertTrue(collect($cells)->contains(fn (array $row): bool => in_array('MRN-XL-001', $row, true)));
        $this->assertTrue(
            collect($cells)->contains(fn (array $row): bool => in_array('0244000111', $row, true)),
            'The encrypted phone column should export as its decrypted value.',
        );
    }

    public function test_export_action_is_hidden_and_unmountable_for_non_superadmins(): void
    {
        Permission::findOrCreate('ViewAny Patient', 'web');
        $user = User::factory()->create()->givePermissionTo('ViewAny Patient');

        Livewire::actingAs($user)
            ->test(ListPatients::class)
            ->assertActionHidden('export')
            ->call('mountAction', 'export');

        $this->assertSame(0, Export::query()->count());
    }

    public function test_export_respects_the_current_branch_scope(): void
    {
        $branchA = BranchFactory::new()->create();
        $branchB = BranchFactory::new()->create();

        Patient::withoutEvents(function () use ($branchA, $branchB): void {
            PatientFactory::new()->count(2)->create(['branch_id' => $branchA->id]);
            PatientFactory::new()->create(['branch_id' => $branchB->id]);
        });

        $superAdmin = $this->superAdmin();
        $superAdmin->update(['branch_id' => $branchA->id]);

        Livewire::actingAs($superAdmin)
            ->test(ListPatients::class)
            ->callAction('export');

        $export = Export::query()->latest('id')->first();

        $this->assertSame(2, $export->successful_rows, 'Only the acting branch\'s rows should export.');
    }

    public function test_exporter_columns_cover_the_expected_fields(): void
    {
        $names = collect(PatientExporter::getColumns())->map(fn ($column) => $column->getName())->all();

        foreach (['mrn', 'first_name', 'last_name', 'phone', 'email', 'branch.name'] as $expected) {
            $this->assertContains($expected, $names);
        }
    }
}
