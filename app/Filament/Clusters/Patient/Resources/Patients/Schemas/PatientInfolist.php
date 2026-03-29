<?php

namespace Modules\Patient\Filament\Clusters\Patient\Resources\Patients\Schemas;

use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

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
            ]);
    }
}
