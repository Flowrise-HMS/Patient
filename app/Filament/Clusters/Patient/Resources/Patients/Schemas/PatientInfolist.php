<?php

namespace Modules\Patient\Filament\Clusters\Patient\Resources\Patients\Schemas;

use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Modules\Billing\Services\PatientBalanceQueryService;

class PatientInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Personal Information')
                    ->columns(3)
                    ->schema([
                        ImageEntry::make('photo')
                            ->label('Photo')
                            ->square()
                            ->imageSize(150),
                        TextEntry::make('full_name')
                            ->label('Full Name'),
                        TextEntry::make('mrn')
                            ->label('MRN'),
                    ]),

                ...static::billingAccountSection(),

                Section::make('Documents')
                    ->schema([
                        RepeatableEntry::make('documents')
                            ->label('')
                            ->schema([
                                TextEntry::make('title')->label('Title'),
                                TextEntry::make('document_type')->label('Type'),
                                TextEntry::make('is_verified')
                                    ->label('Verified')
                                    ->badge()
                                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
                                TextEntry::make('expires_at')
                                    ->label('Expires')
                                    ->date(),
                            ])
                            ->columns(4),
                    ]),

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
                    TextEntry::make('pending_balance')
                        ->label('Outstanding balance')
                        ->state(fn ($record): string => app(PatientBalanceQueryService::class)->openBalanceForPatient((string) $record->id))
                        ->badge()
                        ->color(fn (string $state): string => bccomp($state, '0', 2) > 0 ? 'danger' : 'gray')
                        ->formatStateUsing(fn (string $state): string => 'GHS '.number_format((float) $state, 2)),
                ]),
        ];
    }
}
