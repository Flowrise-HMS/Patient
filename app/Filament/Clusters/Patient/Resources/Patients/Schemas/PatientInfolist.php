<?php

namespace Modules\Patient\Filament\Clusters\Patient\Resources\Patients\Schemas;

use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Modules\Billing\Services\PatientBalanceQueryService;
use Modules\Core\Filament\Infolists\Components\CurrencyEntry;
use Modules\Patient\Filament\Clusters\Patient\Resources\Patients\PatientResource;
use Modules\Patient\Models\Patient;

class PatientInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Merged profile'))
                    ->icon('heroicon-o-exclamation-triangle')
                    ->iconColor('warning')
                    ->visible(fn (Patient $record): bool => $record->isMerged())
                    ->schema([
                        TextEntry::make('merged_notice')
                            ->hiddenLabel()
                            ->state(fn (Patient $record): string => __('This profile was merged into :name (MRN :mrn) and is kept for reference only. Open the surviving record to continue care.', [
                                'name' => $record->mergedInto?->full_name ?? '-',
                                'mrn' => $record->mergedInto?->mrn ?? '-',
                            ]))
                            ->color('warning')
                            ->url(fn (Patient $record): ?string => $record->merged_into_patient_id
                                ? PatientResource::getUrl('view', ['record' => $record->merged_into_patient_id])
                                : null),
                    ]),

                Section::make('Personal Information')
                    ->columns(4)
                    ->schema([
                        ImageEntry::make('photo')
                            ->label('Photo')
                            ->square()
                            ->imageSize(150),
                        TextEntry::make('full_name')
                            ->label('Full Name'),
                        TextEntry::make('mrn')
                            ->label('MRN')
                            ->copyable(),
                        TextEntry::make('old_hospital_number')
                            ->label('Old Hospital No.')
                            ->placeholder('-')
                            ->copyable(),
                    ]),

                ...static::billingAccountSection(),

                PatientSchoolInfolist::getCurrentSchoolSection(),
            ]);
    }

    /**
     * @return array<int, Section>
     */
    protected static function billingAccountSection(): array
    {
        if (! class_exists(PatientBalanceQueryService::class)) {
            return [];
        }

        return [
            Section::make('Account')
                ->visible(fn (): bool => Auth::user()?->can('view_patient_balance') ?? false)
                ->schema([
                    CurrencyEntry::make('pending_balance')
                        ->label('Outstanding balance')
                        ->state(fn ($record): string => app(PatientBalanceQueryService::class)->openBalanceForPatient((string) $record->id))
                        ->badge()
                        ->color(fn ($record): string => bccomp(
                            app(PatientBalanceQueryService::class)->openBalanceForPatient((string) $record->id),
                            '0',
                            2
                        ) > 0 ? 'danger' : 'gray'),
                    CurrencyEntry::make('deposit_balance')
                        ->label('Deposit balance')
                        ->state(fn ($record): string => app(PatientBalanceQueryService::class)->depositBalanceForPatient((string) $record->id))
                        ->badge()
                        ->color(fn ($record): string => bccomp(
                            app(PatientBalanceQueryService::class)->depositBalanceForPatient((string) $record->id),
                            '0',
                            2
                        ) > 0 ? 'success' : 'gray'),
                ]),
        ];
    }
}
