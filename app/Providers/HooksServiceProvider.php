<?php

namespace Modules\Patient\Providers;

use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Modules\Core\Settings\FeatureSettings;
use Modules\Patient\Livewire\AddPatientButton;

class HooksServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     */
    public function register(): void {}

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [];
    }

    public function boot(): void
    {
        FilamentView::registerRenderHook(
            PanelsRenderHook::GLOBAL_SEARCH_BEFORE,
            fn (): string => app(FeatureSettings::class)->patient_quick_add_enabled
                ? Livewire::mount(AddPatientButton::class)
                : '',
        );
    }
}
