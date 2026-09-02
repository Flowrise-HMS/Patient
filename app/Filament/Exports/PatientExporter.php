<?php

namespace Modules\Patient\Filament\Exports;

use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Modules\Patient\Models\Patient;

class PatientExporter extends Exporter
{
    protected static ?string $model = Patient::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id'),
            ExportColumn::make('mrn'),
            ExportColumn::make('title'),
            ExportColumn::make('first_name'),
            ExportColumn::make('middle_name'),
            ExportColumn::make('last_name'),
            ExportColumn::make('date_of_birth'),
            ExportColumn::make('gender'),
            ExportColumn::make('blood_type'),
            ExportColumn::make('marital_status'),
            ExportColumn::make('nationality'),
            ExportColumn::make('occupation'),
            ExportColumn::make('phone'),
            ExportColumn::make('email'),
            ExportColumn::make('preferred_language'),
            ExportColumn::make('is_active'),
            ExportColumn::make('is_deceased'),
            ExportColumn::make('deceased_at'),
            ExportColumn::make('branch.name'),
            ExportColumn::make('created_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your patient export has completed and '.number_format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
