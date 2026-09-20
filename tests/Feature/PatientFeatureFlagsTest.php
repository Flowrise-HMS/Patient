<?php

namespace Modules\Patient\Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Modules\Core\Settings\FeatureSettings;
use Modules\Patient\Filament\Clusters\Patient\Resources\Patients\Pages\ListPatients;
use Tests\TestCase;

/**
 * The Features page toggles for the quick-add button and patient import used
 * to be stored without ever being read.
 */
class PatientFeatureFlagsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient']);
        Gate::before(fn () => true);
        $this->actingAs(User::factory()->create());
        Filament::setCurrentPanel(Filament::getPanel('corepanel'));
    }

    public function test_import_action_follows_the_patient_import_flag(): void
    {
        FeatureSettings::fake(['patient_import_enabled' => false]);
        Livewire::test(ListPatients::class)->assertActionHidden('import');

        FeatureSettings::fake(['patient_import_enabled' => true]);
        Livewire::test(ListPatients::class)->assertActionVisible('import');
    }

    public function test_quick_add_button_render_hook_follows_the_flag(): void
    {
        FeatureSettings::fake(['patient_quick_add_enabled' => false]);
        $this->assertStringNotContainsString('AddPatientButton', (string) FilamentView::renderHook(PanelsRenderHook::GLOBAL_SEARCH_BEFORE));

        FeatureSettings::fake(['patient_quick_add_enabled' => true]);
        $this->assertStringContainsString('AddPatientButton', (string) FilamentView::renderHook(PanelsRenderHook::GLOBAL_SEARCH_BEFORE));
    }
}
