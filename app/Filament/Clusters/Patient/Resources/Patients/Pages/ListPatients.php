<?php

namespace Modules\Patient\Filament\Clusters\Patient\Resources\Patients\Pages;

use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Classes\Support\PageHeaderActionsRegistry;
use Modules\Core\Filament\Support\SuperAdminExportAction;
use Modules\Core\Settings\FeatureSettings;
use Modules\Patient\Filament\Clusters\Patient\Resources\Patients\PatientResource;
use Modules\Patient\Filament\Exports\PatientExporter;
use Modules\Patient\Filament\Imports\PatientImporter;

class ListPatients extends ListRecords
{
    protected static string $resource = PatientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportAction::make()
                ->importer(PatientImporter::class)
                ->visible(fn () => app(FeatureSettings::class)->patient_import_enabled
                    && Auth::user()?->can('import_patients'))
                ->color('info'),
            SuperAdminExportAction::make(PatientExporter::class),
            CreateAction::make(),

            /*
             * Actions contributed by other modules — currently the FHIR export.
             * Going through the registry keeps Patient from importing FHIR, which
             * the module boundary rule forbids in the general case and which would
             * make an optional module a hard dependency of this page.
             */
            ...app(PageHeaderActionsRegistry::class)->for(static::class, $this),
        ];
    }
}
