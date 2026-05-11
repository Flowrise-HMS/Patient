<?php

namespace Modules\Patient\Filament\Clusters\Patient\Resources\Patients\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Patient\Filament\Clusters\Patient\Resources\Patients\PatientResource;

class ListPatients extends ListRecords
{
    protected static string $resource = PatientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\ImportAction::make()
                ->importer(\Modules\Patient\Filament\Imports\PatientImporter::class)
                ->color('info'),
            CreateAction::make(),
        ];
    }
}
