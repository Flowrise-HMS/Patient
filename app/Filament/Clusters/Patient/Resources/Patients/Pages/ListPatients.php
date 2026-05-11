<?php

namespace Modules\Patient\Filament\Clusters\Patient\Resources\Patients\Pages;

use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Patient\Filament\Clusters\Patient\Resources\Patients\PatientResource;
use Modules\Patient\Filament\Imports\PatientImporter;

class ListPatients extends ListRecords
{
    protected static string $resource = PatientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportAction::make()
                ->importer(PatientImporter::class)
                ->color('info'),
            CreateAction::make(),
        ];
    }
}
